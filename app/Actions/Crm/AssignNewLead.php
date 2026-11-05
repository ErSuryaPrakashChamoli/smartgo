<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Enums\Crm\AssignmentOutcome;
use App\Enums\Crm\AssignmentStrategy;
use App\Models\AssignmentRule;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gives a new enquiry an owner by the first matching active assignment rule, lowest priority number
 * first (BR-CRM-3, FR-CRM-2), or leaves it in the unassigned queue, and records the decision once.
 *
 * A person is eligible as in a manual assignment (AssignLead): active, and allowed to work on enquiries
 * about the enquiry's location. Round-robin gives it to the next eligible person after the last one who
 * had a turn, in the rule's order. Fixed person gives it to the one person. Least loaded gives it to the
 * eligible person with the fewest enquiries in the statuses the owner counts as workload, a tie going to
 * whoever is higher in the rule's order.
 *
 * Not decided yet, so the conservative behaviour is kept and the reason recorded:
 * - when the matching rule has nobody eligible, the enquiry stays unassigned; whether to try the next
 *   rule is A-33;
 * - round-robin starts with the first person in the order and continues after the last person to have
 *   a turn (from the first again if that person has left the rule); A-35 may change this;
 * - least loaded cannot assign until the owner says what counts as workload (A-34,
 *   `crm.assignment.workload_statuses`).
 *
 * The enquiry, then every active rule, are locked before anything else is read, so concurrent new
 * enquiries take their turns one after another and each sees the turns and workloads the previous one
 * committed (ADR-0004). A second run for the same enquiry changes nothing. Audited without personal data;
 * no notification is sent (O-1).
 */
class AssignNewLead
{
    /**
     * Runs the rules once the enquiry is stored. A failure leaves the enquiry unassigned, as with no rule,
     * and is logged without personal data; the visitor's submission is not affected.
     */
    public function handleSafely(Lead $lead): ?LeadAssignment
    {
        try {
            return $this->handle($lead);
        } catch (Throwable $exception) {
            Log::warning('Assignment rules could not run for an enquiry; it stays unassigned.', ['lead_id' => $lead->getKey(), 'exception' => $exception::class]);

            return null;
        }
    }

    /**
     * @return LeadAssignment|null the decision, or null when the assignment tables do not exist yet
     */
    public function handle(Lead $lead): ?LeadAssignment
    {
        if (! AssignmentRule::isAvailable()) {
            return null;
        }

        return DB::transaction(function () use ($lead): LeadAssignment {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());
            $rules = AssignmentRule::query()->where('is_active', true)->orderBy('priority')->lockForUpdate()->get();

            $existing = LeadAssignment::query()->where('lead_id', $lead->getKey())->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($lead->owner_user_id !== null) {
                return $this->record($lead, null, AssignmentOutcome::AlreadyOwned);
            }

            $rule = $rules->first(fn (AssignmentRule $rule): bool => $rule->matches($lead));

            if ($rule === null) {
                return $this->record($lead, null, AssignmentOutcome::NoRuleMatched);
            }

            $people = $rule->assignees()->get();
            $eligible = $people->filter(fn (User $user): bool => $user->is_active && $lead->isWithinScopeOf($user, Capability::ManageLeads))->values();

            if ($rule->strategy === AssignmentStrategy::LeastLoaded && ! is_array(config('crm.assignment.workload_statuses'))) {
                return $this->record($lead, $rule, AssignmentOutcome::StrategyUnavailable);
            }

            $owner = match ($rule->strategy) {
                AssignmentStrategy::FixedUser => $people->count() === 1 ? $eligible->first() : null,
                AssignmentStrategy::RoundRobin => $this->nextInTurn($rule, $people, $eligible),
                AssignmentStrategy::LeastLoaded => $this->leastLoaded($eligible),
            };

            if ($owner === null) {
                return $this->record($lead, $rule, AssignmentOutcome::NoEligibleAssignee);
            }

            if ($rule->strategy === AssignmentStrategy::RoundRobin) {
                $rule->forceFill(['last_assigned_user_id' => $owner->getKey()])->save();
            }

            $lead->forceFill(['owner_user_id' => $owner->getKey()])->save();

            activity('leads')
                ->performedOn($lead)
                ->event('lead_auto_assigned')
                ->withProperties(['reference' => $lead->reference, 'rule_id' => $rule->getKey(), 'to_user_id' => $owner->getKey()])
                ->log('Enquiry assigned to '.$owner->name.' by the rule "'.$rule->name.'"');

            return $this->record($lead, $rule, AssignmentOutcome::Assigned, $owner);
        });
    }

    /**
     * The next eligible person after the last one to have a turn, in the rule's order, going round.
     *
     * @param  Collection<int, User>  $people  everyone on the rule, in order
     * @param  Collection<int, User>  $eligible
     */
    private function nextInTurn(AssignmentRule $rule, Collection $people, Collection $eligible): ?User
    {
        $last = $people->search(fn (User $user): bool => $user->getKey() === $rule->last_assigned_user_id);
        $start = $last === false ? 0 : $last + 1;
        $eligibleIds = $eligible->modelKeys();

        for ($step = 0; $step < $people->count(); $step++) {
            $candidate = $people[($start + $step) % $people->count()];

            if (in_array($candidate->getKey(), $eligibleIds, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The eligible person with the fewest enquiries in the workload statuses; the first in the rule's
     * order among equals.
     *
     * @param  Collection<int, User>  $eligible  in the rule's order
     */
    private function leastLoaded(Collection $eligible): ?User
    {
        if ($eligible->isEmpty()) {
            return null;
        }

        $workload = Lead::query()
            ->whereIn('owner_user_id', $eligible->modelKeys())
            ->whereIn('status', config('crm.assignment.workload_statuses'))
            ->groupBy('owner_user_id')
            ->selectRaw('owner_user_id, COUNT(*) AS enquiries')
            ->pluck('enquiries', 'owner_user_id');

        return $eligible->reduce(fn (?User $least, User $user): User => $least === null || (int) ($workload[$user->getKey()] ?? 0) < (int) ($workload[$least->getKey()] ?? 0) ? $user : $least);
    }

    private function record(Lead $lead, ?AssignmentRule $rule, AssignmentOutcome $outcome, ?User $owner = null): LeadAssignment
    {
        $decision = (new LeadAssignment)->forceFill([
            'lead_id' => $lead->getKey(),
            'assignment_rule_id' => $rule?->getKey(),
            'outcome' => $outcome,
            'assigned_user_id' => $owner?->getKey(),
        ]);
        $decision->save();

        return $decision;
    }
}
