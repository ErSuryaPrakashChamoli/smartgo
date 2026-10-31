<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceSeat;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records an allocation: a space, a seat or a number of seats occupied under an agreement from a start
 * date, until an end date or open-ended (FR-INV-3). Staff with `allocations.manage` for the property
 * record them (SECURITY_MODEL §3.1 "Allocations").
 *
 * The space's row is locked before anything else is read (ADR-0004, BR-BK-5): a whole space, a
 * per-seat zone, a pool and a hybrid zone all serialise on it. Only then are the space's status and
 * the overlapping allocations read and the rules checked for every day of the period (BR-AV-2,
 * BR-AV-3, BR-AV-3a, BR-AV-3d, BR-AV-4). So two people allocating the last place at the same instant
 * can never both succeed. Audited, and announced as an availability change (BR-SOT-4).
 */
class RecordAllocation
{
    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * @param  array<string, mixed>  $data  agreement_reference, space_seat_id, seats_allocated, starts_on, ends_on, reason
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Space $space, array $data): SpaceAllocation
    {
        if (! $user->hasCapabilityForProperty(Capability::ManageAllocations, $space->property)) {
            throw new AuthorizationException('You cannot record allocations at this property.');
        }

        $today = BusinessDay::today();
        $validated = Validator::make($data, [
            'agreement_reference' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/.-]*$/'],
            'space_seat_id' => ['nullable', 'integer'],
            'seats_allocated' => ['nullable', 'integer', 'between:1,2000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'after_or_equal:'.$today->toDateString()],
            'reason' => ['required', 'string', 'max:300', 'not_regex:/[<>]/'],
        ], [
            'agreement_reference.regex' => 'Use letters, numbers, hyphens, dots and slashes, for example AG-2027-014.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date or in the past.',
            'reason.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['space_seat_id' => 'seat', 'seats_allocated' => 'seats', 'starts_on' => 'start date', 'ends_on' => 'end date'])->validate();

        return DB::transaction(function () use ($user, $space, $validated, $today): SpaceAllocation {
            $space = Space::query()->with('property')->lockForUpdate()->findOrFail($space->getKey());
            $seat = filled($validated['space_seat_id'] ?? null) ? SpaceSeat::query()->where('space_id', $space->getKey())->find($validated['space_seat_id']) : null;

            if (filled($validated['space_seat_id'] ?? null) && $seat === null) {
                throw ValidationException::withMessages(['space_seat_id' => 'Choose one of this zone\'s seats.']);
            }

            $seats = $this->seatsTaken($space, $seat, $validated);
            $from = CarbonImmutable::parse($validated['starts_on'])->startOfDay();
            $until = filled($validated['ends_on'] ?? null) ? CarbonImmutable::parse($validated['ends_on'])->startOfDay() : null;

            if (($refusal = $this->spaceAvailability->refusalForAllocation($space, $seat, $seats, $from, $until)) !== null) {
                throw ValidationException::withMessages(['starts_on' => $refusal]);
            }

            $allocation = (new SpaceAllocation)->forceFill([
                'space_id' => $space->getKey(),
                'space_seat_id' => $seat?->getKey(),
                'agreement_reference' => $validated['agreement_reference'],
                'seats_allocated' => $seats,
                'starts_on' => $from,
                'ends_on' => $until,
                'status' => $from->greaterThan($today) ? AllocationStatus::Reserved : AllocationStatus::Active,
                'source' => 'manual',
                'created_by' => $user->getKey(),
            ]);
            $allocation->save();

            $this->recordInventoryActivity->handle($allocation, $user, 'allocation_added', 'Allocation recorded', $validated['reason'], [
                'property' => $space->property->code,
                'space' => $space->code,
                'attributes' => [...$allocation->only(['agreement_reference', 'space_seat_id', 'seats_allocated']), 'starts_on' => $from->toDateString(), 'ends_on' => $until?->toDateString()],
            ]);
            SpaceAvailabilityChanged::dispatch($space, 'allocation');

            return $allocation;
        });
    }

    /**
     * How many seats the allocation takes: the whole space, one seat, or the number asked for.
     *
     * @param  array<string, mixed>  $validated
     */
    private function seatsTaken(Space $space, ?SpaceSeat $seat, array $validated): int
    {
        return match (true) {
            $space->inventory_mode === InventoryMode::Exclusive => $seat === null ? $space->capacity_seats : throw ValidationException::withMessages(['space_seat_id' => 'A whole space is allocated without choosing a seat.']),
            $seat !== null && ! $space->inventory_mode->hasSeats() => throw ValidationException::withMessages(['space_seat_id' => 'This zone\'s seats are not assigned individually: give a number of seats instead.']),
            $seat !== null => 1,
            $space->inventory_mode === InventoryMode::PerSeat => throw ValidationException::withMessages(['space_seat_id' => 'Choose the seat: in this zone each seat is allocated on its own.']),
            filled($validated['seats_allocated'] ?? null) => (int) $validated['seats_allocated'],
            default => throw ValidationException::withMessages(['seats_allocated' => 'Enter how many seats are allocated.']),
        };
    }
}
