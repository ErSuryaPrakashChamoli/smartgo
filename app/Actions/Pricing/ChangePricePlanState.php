<?php

namespace App\Actions\Pricing;

use App\Enums\Access\Capability;
use App\Enums\Pricing\PricePlanStatus;
use App\Events\Pricing\PricePlanActivated;
use App\Models\Offering;
use App\Models\PricePlan;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a price plan through its states (FR-INV-4, BR-PR-2), each audited:
 *
 * - submit: an author sends a draft for activation;
 * - activate: a person granted `pricing.approve` who neither entered, edited nor submitted the plan
 *   (four-eyes) makes it active. The offering's row is locked first, then the plan's, and only then are
 *   its dates and the other active plans checked: a plan cannot start before today, and its dates may not
 *   overlap another active plan for the same offering, space and unit (DATABASE_ERD §4: at most one
 *   active plan per scope and unit at any instant). Two activations at the same instant are serialised,
 *   so both can never succeed;
 * - return: the approver sends it back with a reason, or an author withdraws it, as a draft;
 * - end: Finance sets the last day an active plan applies (today or later), so a new plan can follow it;
 * - retire: Finance stops an active plan at once, with a reason.
 *
 * Ending and retiring take the same offering lock. Nothing is deleted; the history stays on the plan.
 */
class ChangePricePlanState
{
    public function __construct(private RecordPricingActivity $recordPricingActivity) {}

    /**
     * @param  'submit'|'activate'|'return'|'end'|'retire'  $change
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, PricePlan $plan, string $change, ?string $reason = null, ?string $endsOn = null): PricePlan
    {
        $reason = trim((string) $reason);

        if (in_array($change, ['return', 'end', 'retire'], true) && ($reason === '' || mb_strlen($reason) > 500 || preg_match('/[<>]/', $reason) === 1)) {
            throw ValidationException::withMessages(['reason' => 'Give the reason in plain text, up to 500 characters.']);
        }

        return DB::transaction(function () use ($user, $plan, $change, $reason, $endsOn): PricePlan {
            Offering::query()->lockForUpdate()->findOrFail($plan->offering_id);
            $plan = PricePlan::query()->with(['offering.product', 'space'])->lockForUpdate()->findOrFail($plan->getKey());
            $today = BusinessDay::today();

            [$event, $description, $properties] = match ($change) {
                'submit' => $this->submit($user, $plan),
                'activate' => $this->activate($user, $plan, $today),
                'return' => $this->return($user, $plan, $reason),
                'end' => $this->end($user, $plan, $reason, $endsOn, $today),
                'retire' => $this->retire($user, $plan, $reason),
            };

            $plan->save();
            $this->recordPricingActivity->handle($plan, $user, $event, $description, $properties);

            if ($change === 'activate') {
                PricePlanActivated::dispatch($plan);
            }

            return $plan;
        });
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function submit(User $user, PricePlan $plan): array
    {
        $this->authorize($user->can(Capability::ManagePrices), 'Only Finance and the Super Admin submit prices.');
        $this->expect($plan, PricePlanStatus::Draft, 'Only a draft can be submitted.');

        $plan->forceFill(['status' => PricePlanStatus::PendingApproval, 'submitted_by' => $user->getKey(), 'submitted_at' => now()]);

        return ['price_plan_submitted', 'Price plan submitted for activation', []];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function activate(User $user, PricePlan $plan, CarbonImmutable $today): array
    {
        $this->authorize($user->can(Capability::ApprovePrices), 'Only Finance and the Super Admin activate prices.');
        $this->authorize(! in_array($user->getKey(), $plan->authorIds(), true), 'A price plan is activated by someone who did not enter, change or submit it (BR-PR-2).');
        $this->expect($plan, PricePlanStatus::PendingApproval, 'Only a plan awaiting activation can be activated.');

        if (! $plan->offering->is_active || ! in_array($plan->unit->value, config('pricing.units_by_category.'.$plan->offering->product->key, []), true)) {
            throw ValidationException::withMessages(['plan' => 'This workspace category is no longer offered here, or no longer priced in this unit. Return the plan to Finance.']);
        }

        if ($plan->space !== null && ! $plan->space->offerings()->whereKey($plan->offering_id)->exists()) {
            throw ValidationException::withMessages(['plan' => 'Space '.$plan->space->code.' is no longer sold as this category. Return the plan to Finance.']);
        }

        if ($plan->valid_from->lessThan($today)) {
            throw ValidationException::withMessages(['valid_from' => 'This plan starts on '.$plan->valid_from->format('j M Y').', before today. A price never applies to days before it was activated: return it to Finance for a new start date.']);
        }

        $overlapping = PricePlan::query()
            ->sameScope($plan->offering_id, $plan->space_id, $plan->unit)
            ->where('status', PricePlanStatus::Active->value)
            ->whereKeyNot($plan->getKey())
            ->overlapping($plan->valid_from, $plan->valid_to)
            ->orderBy('valid_from')
            ->get();

        if ($overlapping->isNotEmpty()) {
            throw ValidationException::withMessages(['valid_from' => 'Another active plan for the same category, space and unit covers some of these dates: '
                .$overlapping->map(fn (PricePlan $other): string => 'plan '.$other->getKey().', '.PricePlan::format($other->amount_minor).' from '.$other->valid_from->format('j M Y').' to '.($other->valid_to?->format('j M Y') ?? 'open-ended'))->implode('; ')
                .'. Only one can apply on any day: Finance ends that plan first, or the dates change.']);
        }

        $plan->forceFill(['status' => PricePlanStatus::Active, 'approved_by' => $user->getKey(), 'approved_at' => now()]);

        return ['price_plan_activated', 'Price plan activated', ['amount_minor' => $plan->amount_minor, 'valid_from' => $plan->valid_from->toDateString(), 'valid_to' => $plan->valid_to?->toDateString()]];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function return(User $user, PricePlan $plan, string $reason): array
    {
        $isAuthor = in_array($user->getKey(), $plan->authorIds(), true);
        $this->authorize($isAuthor ? $user->can(Capability::ManagePrices) : $user->can(Capability::ApprovePrices), 'You cannot return this plan.');
        $this->expect($plan, PricePlanStatus::PendingApproval, 'Only a plan awaiting activation can be returned.');

        $plan->forceFill(['status' => PricePlanStatus::Draft, 'submitted_by' => null, 'submitted_at' => null]);

        return [$isAuthor ? 'price_plan_withdrawn' : 'price_plan_returned', $isAuthor ? 'Price plan withdrawn' : 'Price plan returned to Finance', ['reason' => $reason]];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function end(User $user, PricePlan $plan, string $reason, ?string $endsOn, CarbonImmutable $today): array
    {
        $this->authorize($user->can(Capability::ManagePrices), 'Only Finance and the Super Admin end prices.');
        $this->expect($plan, PricePlanStatus::Active, 'Only an active plan can be ended.');
        $last = $endsOn !== null && strtotime($endsOn) !== false ? CarbonImmutable::parse($endsOn)->startOfDay() : null;

        if ($last === null || $last->lessThan($today) || $last->lessThan($plan->valid_from) || ($plan->valid_to !== null && $last->greaterThanOrEqualTo($plan->valid_to))) {
            throw ValidationException::withMessages(['ends_on' => 'Choose a last day from today, not before the plan starts and before its current end.']);
        }

        $before = $plan->valid_to?->toDateString();
        $plan->forceFill(['valid_to' => $last]);

        return ['price_plan_ended', 'Price plan end date set', ['reason' => $reason, 'old' => ['valid_to' => $before], 'attributes' => ['valid_to' => $last->toDateString()]]];
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function retire(User $user, PricePlan $plan, string $reason): array
    {
        $this->authorize($user->can(Capability::ManagePrices), 'Only Finance and the Super Admin retire prices.');
        $this->expect($plan, PricePlanStatus::Active, 'Only an active plan can be retired.');

        $plan->forceFill(['status' => PricePlanStatus::Retired, 'retired_by' => $user->getKey(), 'retired_at' => now()]);

        return ['price_plan_retired', 'Price plan retired', ['reason' => $reason]];
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(bool $allowed, string $message): void
    {
        if (! $allowed) {
            throw new AuthorizationException($message);
        }
    }

    /**
     * Refuses when the plan is no longer in the state the user saw.
     *
     * @throws ValidationException
     */
    private function expect(PricePlan $plan, PricePlanStatus $status, string $message): void
    {
        if ($plan->status !== $status) {
            throw ValidationException::withMessages(['plan' => $message.' This plan is '.strtolower($plan->status->label()).'; reload it.']);
        }
    }
}
