<?php

namespace App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages;

use App\Actions\Crm\SaveAssignmentRule;
use App\Models\AssignmentRule;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Saves the form through SaveAssignmentRule, which checks permission and every value again, and shows
 * its errors on the fields they belong to.
 */
trait SavesAssignmentRules
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveRule(AssignmentRule $rule, array $data): AssignmentRule
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SaveAssignmentRule::class)->handle($user, $rule, [
                ...Arr::only($data, ['name', 'priority', 'strategy', 'criteria']),
                'assignee_ids' => collect($data['assignees'] ?? [])->pluck('user_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all(),
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => [match (true) {
                str_starts_with($key, 'assignee_ids') => 'data.assignees',
                str_starts_with($key, 'criteria.') => 'data.'.implode('.', array_slice(explode('.', $key), 0, 2)),
                default => 'data.'.$key,
            } => $messages]));
        }
    }
}
