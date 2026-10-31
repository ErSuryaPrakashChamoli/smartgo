<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\AllocationStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records notice on an allocation, changes its end date, or cancels it (FR-INV-3), with a reason.
 * Allocations are never deleted (DATABASE_ERD §1.8). Every change locks the space's row first, like
 * recording one (ADR-0004): a later end date is checked like a new allocation for the days it adds,
 * while an earlier end or a cancellation frees the space at once, because availability is derived
 * (BR-BK-11). Audited and announced as an availability change.
 */
class ChangeAllocation
{
    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * @param  'notice'|'end_date'|'cancel'  $change
     * @param  array<string, mixed>  $data  notice_given_on and ends_on for notice; ends_on (or null for open-ended) for end_date; reason
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SpaceAllocation $allocation, string $change, array $data): SpaceAllocation
    {
        if (! $user->hasCapabilityForProperty(Capability::ManageAllocations, $allocation->space->property)) {
            throw new AuthorizationException('You cannot change allocations at this property.');
        }

        $today = BusinessDay::today();
        $validated = Validator::make($data, [
            'notice_given_on' => $change === 'notice' ? ['required', 'date', 'before_or_equal:'.$today->toDateString()] : ['prohibited'],
            'ends_on' => match ($change) {
                'notice' => ['required', 'date', 'after_or_equal:notice_given_on', 'after_or_equal:'.$today->toDateString()],
                'end_date' => ['nullable', 'date', 'after_or_equal:'.$today->toDateString()],
                'cancel' => ['prohibited'],
            },
            'reason' => ['required', 'string', 'max:300', 'not_regex:/[<>]/'],
        ], [
            'notice_given_on.before_or_equal' => 'Notice cannot be dated in the future.',
            'ends_on.after_or_equal' => 'The end date cannot be in the past or before the notice.',
            'reason.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['notice_given_on' => 'notice date', 'ends_on' => 'end date'])->validate();

        return DB::transaction(function () use ($user, $allocation, $change, $validated): SpaceAllocation {
            $space = Space::query()->with('property')->lockForUpdate()->findOrFail($allocation->space_id);
            $allocation = SpaceAllocation::query()->with('seat')->lockForUpdate()->findOrFail($allocation->getKey());

            if (! $allocation->status->occupies()) {
                throw ValidationException::withMessages(['allocation' => 'This allocation has already been '.strtolower($allocation->status->label()).'.']);
            }

            $before = $this->audited($allocation);
            $endsOn = filled($validated['ends_on'] ?? null) ? CarbonImmutable::parse($validated['ends_on'])->startOfDay() : null;

            if ($change !== 'cancel' && $endsOn !== null && $endsOn->lessThan($allocation->starts_on)) {
                throw ValidationException::withMessages(['ends_on' => 'The end date cannot be before the allocation starts ('.$allocation->starts_on->format('j M Y').').']);
            }

            if ($change !== 'cancel' && $this->extends($allocation, $endsOn)) {
                $addedFrom = $allocation->ends_on->addDay();
                $refusal = $this->spaceAvailability->refusalForAllocation($space, $allocation->seat, $allocation->seats_allocated, $addedFrom, $endsOn, ignoreAllocationId: $allocation->getKey());

                if ($refusal !== null) {
                    throw ValidationException::withMessages(['ends_on' => $refusal]);
                }
            }

            $allocation->forceFill(match ($change) {
                'notice' => ['notice_given_on' => CarbonImmutable::parse($validated['notice_given_on']), 'ends_on' => $endsOn, 'status' => AllocationStatus::NoticePeriod],
                'end_date' => ['ends_on' => $endsOn],
                'cancel' => ['status' => AllocationStatus::Cancelled],
            })->save();

            $this->recordInventoryActivity->handle($allocation, $user, 'allocation_'.$change, match ($change) {
                'notice' => 'Notice recorded on an allocation',
                'end_date' => 'Allocation end date changed',
                'cancel' => 'Allocation cancelled',
            }, $validated['reason'], [
                'property' => $space->property->code,
                'space' => $space->code,
                'old' => $before,
                'attributes' => $this->audited($allocation),
            ]);
            SpaceAvailabilityChanged::dispatch($space, 'allocation');

            return $allocation;
        });
    }

    /**
     * Whether the new end date adds days: a later date than the current end, or open-ended where it had an end.
     */
    private function extends(SpaceAllocation $allocation, ?CarbonImmutable $endsOn): bool
    {
        return $allocation->ends_on !== null && ($endsOn === null || $endsOn->greaterThan($allocation->ends_on));
    }

    /**
     * @return array<string, string|null>
     */
    private function audited(SpaceAllocation $allocation): array
    {
        return [
            'status' => $allocation->status->value,
            'ends_on' => $allocation->ends_on?->toDateString(),
            'notice_given_on' => $allocation->notice_given_on?->toDateString(),
        ];
    }
}
