<?php

namespace App\Actions\Crm;

use App\Models\Deal;
use App\Models\User;
use App\Services\Crm\DealValues;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Changes an open deal's seats, monthly value and expected close date (FR-CRM-4), as staff type them. The
 * deal is locked and the actor's permission and scope are checked again; a won or lost deal keeps what it
 * was closed with. Audited with each change.
 */
class UpdateDeal
{
    /**
     * @param  array<string, mixed>  $data  seats, monthly_value (rupees), expected_close_on
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, array $data): Deal
    {
        $columns = DealValues::columns(DealValues::validate($data));

        return DB::transaction(function () use ($actor, $deal, $columns): Deal {
            $deal = Deal::query()->with('lead.property')->lockForUpdate()->findOrFail($deal->getKey());
            Gate::forUser($actor)->authorize('update', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['seats' => 'This deal is '.strtolower($deal->outcome()->label()).', so its details stay as they were when it closed.']);
            }

            $before = ['seats' => $deal->seats, 'monthly_value_minor' => $deal->monthly_value_minor, 'expected_close_on' => $deal->expected_close_on?->toDateString()];
            $changes = collect($columns)
                ->filter(fn (int|string|null $value, string $column): bool => $value !== $before[$column])
                ->map(fn (int|string|null $value, string $column): array => ['from' => $before[$column], 'to' => $value])
                ->all();

            if ($changes === []) {
                return $deal;
            }

            $deal->forceFill($columns)->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_updated')
                ->withProperties(['deal_id' => $deal->getKey(), 'changes' => $changes])
                ->log('Deal details changed: '.implode(', ', array_map(fn (string $column): string => str_replace(['_minor', '_on', '_'], ['', '', ' '], $column), array_keys($changes))));

            return $deal;
        });
    }
}
