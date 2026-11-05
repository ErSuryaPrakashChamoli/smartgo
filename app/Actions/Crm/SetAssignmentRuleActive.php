<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Enums\Crm\AssignmentStrategy;
use App\Models\AssignmentRule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Switches an assignment rule on or off, explicitly and separately from editing it (FR-CRM-2), for the
 * Super Admin only. A rule is switched on only when it can assign: it has people, all of them active staff
 * who may work on enquiries (exactly one for a fixed-person rule), and, for least loaded, the owner has
 * said what counts as workload (A-34). Switching on or off again changes nothing. Audited.
 */
class SetAssignmentRuleActive
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssignmentRule $rule, bool $active): AssignmentRule
    {
        Gate::forUser($actor)->authorize('update', $rule);

        return DB::transaction(function () use ($actor, $rule, $active): AssignmentRule {
            $rule = AssignmentRule::query()->lockForUpdate()->findOrFail($rule->getKey());

            if ($rule->is_active === $active) {
                return $rule;
            }

            if ($active) {
                $people = $rule->assignees()->get();

                if ($people->isEmpty() || ($rule->strategy === AssignmentStrategy::FixedUser && $people->count() !== 1)) {
                    throw ValidationException::withMessages(['is_active' => $rule->strategy === AssignmentStrategy::FixedUser
                        ? 'Choose the one person this rule gives enquiries to before switching it on.'
                        : 'Add the people this rule gives enquiries to before switching it on.']);
                }

                $unable = $people->reject(fn (User $user): bool => $user->is_active && $user->can(Capability::ManageLeads));

                if ($unable->isNotEmpty()) {
                    throw ValidationException::withMessages(['is_active' => 'These people can no longer work on enquiries: '.$unable->pluck('name')->join(', ').'. Change the rule first.']);
                }

                if ($rule->strategy === AssignmentStrategy::LeastLoaded && ! is_array(config('crm.assignment.workload_statuses'))) {
                    throw ValidationException::withMessages(['is_active' => 'A least-loaded rule can be switched on once the owner has decided which enquiries count as workload (A-34).']);
                }
            }

            $rule->forceFill(['is_active' => $active])->save();

            activity('leads')
                ->performedOn($rule)
                ->causedBy($actor)
                ->event($active ? 'assignment_rule_activated' : 'assignment_rule_deactivated')
                ->withProperties(['rule_id' => $rule->getKey()])
                ->log(($active ? 'Assignment rule switched on: "' : 'Assignment rule switched off: "').$rule->name.'"');

            return $rule;
        });
    }
}
