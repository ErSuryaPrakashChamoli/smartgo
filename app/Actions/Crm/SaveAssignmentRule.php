<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Enums\Crm\AssignmentStrategy;
use App\Models\AssignmentRule;
use App\Models\Lead;
use App\Models\User;
use App\Services\Website\EnquiryForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or changes an assignment rule (FR-CRM-2), for the Super Admin only (OD-36). A new rule is
 * inactive; an active rule must be switched off before it is changed, so no enquiry is ever assigned by
 * a half-edited rule (SetAssignmentRuleActive switches it on again). Each priority is used once, so the
 * first matching rule is always clear. The people must be active staff who may work on enquiries; a
 * fixed-person rule has at most one. Audited with what changed, never personal data.
 */
class SaveAssignmentRule
{
    /**
     * @param  array<string, mixed>  $data  name, priority, strategy, criteria (lists by AssignmentRule::CRITERIA key), assignee_ids (in order)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, AssignmentRule $rule, array $data): AssignmentRule
    {
        $rule->exists
            ? Gate::forUser($actor)->authorize('update', $rule)
            : Gate::forUser($actor)->authorize('create', AssignmentRule::class);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'priority' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('assignment_rules', 'priority')->ignore($rule->getKey())],
            'strategy' => ['required', Rule::enum(AssignmentStrategy::class)],
            'criteria' => ['nullable', 'array:'.implode(',', array_keys(AssignmentRule::CRITERIA))],
            'criteria.city_ids' => ['nullable', 'array'],
            'criteria.city_ids.*' => ['integer', 'distinct', Rule::exists('cities', 'id')],
            'criteria.property_ids' => ['nullable', 'array'],
            'criteria.property_ids.*' => ['integer', 'distinct', Rule::exists('properties', 'id')],
            'criteria.product_ids' => ['nullable', 'array'],
            'criteria.product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
            'criteria.segment_ids' => ['nullable', 'array'],
            'criteria.segment_ids.*' => ['integer', 'distinct', Rule::exists('customer_segments', 'id')],
            'criteria.team_sizes' => ['nullable', 'array'],
            'criteria.team_sizes.*' => ['string', 'distinct', Rule::in(array_keys(EnquiryForm::TEAM_SIZES))],
            'criteria.sources' => ['nullable', 'array'],
            'criteria.sources.*' => ['string', 'distinct', Rule::in(array_keys(Lead::SOURCES))],
            'assignee_ids' => ['nullable', 'array', 'list'],
            'assignee_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ], [
            'priority.unique' => 'Another rule already has this priority. Each priority is used once, so the first matching rule is always clear.',
            'name.not_regex' => 'Use plain text: < and > are not allowed.',
        ])->after(function ($validator) use ($data): void {
            $ids = array_map('intval', is_array($data['assignee_ids'] ?? null) ? $data['assignee_ids'] : []);

            if (($data['strategy'] ?? null) === AssignmentStrategy::FixedUser->value && count($ids) > 1) {
                $validator->errors()->add('assignee_ids', 'A fixed-person rule gives enquiries to one person.');
            }

            $unable = User::query()->whereKey($ids)->get()->reject(fn (User $user): bool => $user->is_active && $user->can(Capability::ManageLeads));

            if ($unable->isNotEmpty()) {
                $validator->errors()->add('assignee_ids', 'These people cannot work on enquiries: '.$unable->pluck('name')->join(', ').'.');
            }
        })->validate();

        $criteria = collect(AssignmentRule::CRITERIA)
            ->mapWithKeys(fn (string $column, string $criterion): array => [$criterion => array_values(array_map(
                fn (mixed $value): int|string => str_ends_with($criterion, '_ids') ? (int) $value : (string) $value,
                $validated['criteria'][$criterion] ?? [],
            ))])
            ->all();
        $assigneeIds = array_values(array_map('intval', $validated['assignee_ids'] ?? []));

        return DB::transaction(function () use ($actor, $rule, $validated, $criteria, $assigneeIds): AssignmentRule {
            if ($rule->exists) {
                $rule = AssignmentRule::query()->lockForUpdate()->findOrFail($rule->getKey());

                if ($rule->is_active) {
                    throw ValidationException::withMessages(['name' => 'Switch the rule off before changing it, so no enquiry is assigned by a half-edited rule.']);
                }
            }

            $before = $rule->exists ? [...$rule->only(['name', 'priority', 'strategy', 'criteria']), 'assignee_ids' => $rule->assignees()->pluck('users.id')->all()] : null;
            $creating = ! $rule->exists;

            $rule->forceFill([
                'name' => $validated['name'],
                'priority' => (int) $validated['priority'],
                'strategy' => AssignmentStrategy::from($validated['strategy']),
                'criteria' => $criteria,
                ...($creating ? ['is_active' => false] : []),
            ])->save();

            // Replaced as a whole: changing positions one row at a time would collide on the unique position.
            $rule->assignees()->detach();
            $rule->assignees()->attach(collect($assigneeIds)->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $index + 1]])->all());

            $after = [...$rule->only(['name', 'priority', 'strategy', 'criteria']), 'assignee_ids' => $assigneeIds];
            $changed = $before === null ? array_keys($after) : array_keys(array_filter($after, fn (mixed $value, string $key): bool => $value != $before[$key], ARRAY_FILTER_USE_BOTH));

            if ($creating || $changed !== []) {
                activity('leads')
                    ->performedOn($rule)
                    ->causedBy($actor)
                    ->event($creating ? 'assignment_rule_created' : 'assignment_rule_updated')
                    ->withProperties(['rule_id' => $rule->getKey(), 'changed' => $changed])
                    ->log(($creating ? 'Assignment rule created: "' : 'Assignment rule changed: "').$rule->name.'"');
            }

            return $rule;
        });
    }
}
