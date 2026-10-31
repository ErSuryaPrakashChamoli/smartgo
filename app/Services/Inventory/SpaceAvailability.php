<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\CustomerAvailability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceSeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The one place availability is computed (FR-INV-6; an architecture test keeps allocations out of every
 * other surface). Availability is derived from the space's status, its active seats and the allocations
 * occupying it (DATABASE_ERD §1.3), never stored. Days are whole days; an allocation occupies its space
 * from its start to its end date, both inclusive, or for ever without an end date.
 *
 * The checks used by the inventory actions (`refusalForAllocation`, `commitmentsFrom`,
 * `overCommittedAllocations`) read the allocations afresh and must run after the action has locked the
 * space's row (ADR-0004, BR-BK-5): nothing is read before the lock.
 *
 * Bookings and property closures join these rules with bookings (Phase 8, BR-AV-5).
 */
class SpaceAvailability
{
    /**
     * A space's availability on a day, for staff and as a customer would see it (BR-AV-1 to BR-AV-4).
     */
    public function on(Space $space, ?CarbonImmutable $day = null): DayAvailability
    {
        $day ??= BusinessDay::today();
        $allocations = $this->occupyingFrom($space, $day);

        return $this->compute($space, $day, $allocations);
    }

    /**
     * The availability of several spaces on a day, with two queries for all of them.
     *
     * @param  Collection<int, Space>  $spaces
     * @return Collection<int, DayAvailability> keyed by space id
     */
    public function onForMany(Collection $spaces, ?CarbonImmutable $day = null): Collection
    {
        $day ??= BusinessDay::today();
        $spaces->loadMissing('seats');
        $allocations = SpaceAllocation::query()->whereIn('space_id', $spaces->modelKeys())->occupyingBetween($day, null)->get()->groupBy('space_id');

        return $spaces->mapWithKeys(fn (Space $space): array => [$space->getKey() => $this->compute($space, $day, $allocations->get($space->getKey(), collect()))]);
    }

    /**
     * Why a new or changed allocation cannot be recorded, or null when it fits for every day it covers
     * (BR-AV-2, BR-AV-3, BR-AV-3a, BR-AV-3d, BR-AV-4). Call only with the space's row locked.
     */
    public function refusalForAllocation(Space $space, ?SpaceSeat $seat, int $seats, CarbonImmutable $from, ?CarbonImmutable $until, ?int $ignoreAllocationId = null): ?string
    {
        if ($space->operational_status !== SpaceStatus::Active) {
            return 'This space is '.strtolower($space->operational_status->label()).', so nothing can be allocated in it (BR-AV-4).';
        }

        $overlapping = SpaceAllocation::query()
            ->with('seat')
            ->where('space_id', $space->getKey())
            ->when($ignoreAllocationId !== null, fn ($query) => $query->whereKeyNot($ignoreAllocationId))
            ->occupyingBetween($from, $until)
            ->get();

        return match ($space->inventory_mode) {
            InventoryMode::Exclusive => $overlapping->isEmpty() ? null : 'The space is already allocated for some of these dates: '.$this->list($overlapping).'.',
            InventoryMode::PerSeat => $this->seatRefusal($seat, $overlapping),
            InventoryMode::Pooled => $this->poolRefusal($space->capacity_seats, $seats, $overlapping, $from, $until),
            InventoryMode::Hybrid => $seat !== null
                ? ($this->seatRefusal($seat, $overlapping) ?? $this->poolRefusal($this->activeSeatCount($space), 1, $overlapping, $from, $until))
                : $this->poolRefusal($this->activeSeatCount($space), $seats, $overlapping, $from, $until),
        };
    }

    /**
     * The allocations occupying the space on or after a day: the commitments a change of mode, a
     * change of status away from active or a removal must not break (BR-INV-3, BR-INV-4). Call only
     * with the space's row locked.
     *
     * @return Collection<int, SpaceAllocation>
     */
    public function commitmentsFrom(Space $space, CarbonImmutable $day): Collection
    {
        return $this->occupyingFrom($space, $day)->load('seat');
    }

    /**
     * The allocations that would no longer fit on or after a day if the space offered only $capacity
     * seats, plus any on a seat being deactivated (BR-INV-4: reducing supply). Call only with the
     * space's row locked.
     *
     * @param  list<int>  $deactivatedSeatIds
     * @return Collection<int, SpaceAllocation>
     */
    public function overCommittedAllocations(Space $space, int $capacity, CarbonImmutable $from, array $deactivatedSeatIds = []): Collection
    {
        $allocations = $this->commitmentsFrom($space, $from);
        $onDeactivatedSeats = $allocations->filter(fn (SpaceAllocation $allocation): bool => in_array($allocation->space_seat_id, $deactivatedSeatIds, true));
        $overDays = $this->candidateDays($allocations, $from, null)->filter(fn (CarbonImmutable $day): bool => $this->usageOn($allocations, $day) > $capacity);
        $onOverDays = $allocations->filter(fn (SpaceAllocation $allocation): bool => $overDays->contains(fn (CarbonImmutable $day): bool => $allocation->occupiesOn($day)));

        return $onDeactivatedSeats->merge($onOverDays)->unique('id')->sortBy('starts_on')->values();
    }

    /**
     * The seats a space offers: its capacity, or its active seats when they are recorded one by one.
     */
    public function capacityOf(Space $space): int
    {
        return $space->inventory_mode->hasSeats() ? $this->activeSeatCount($space) : $space->capacity_seats;
    }

    /**
     * @param  Collection<int, SpaceAllocation>  $allocations  occupying allocations from $day on
     */
    private function compute(Space $space, CarbonImmutable $day, Collection $allocations): DayAvailability
    {
        $capacity = $this->capacityOf($space);
        $wholeSpace = $space->inventory_mode === InventoryMode::Exclusive;
        $today = $allocations->filter(fn (SpaceAllocation $allocation): bool => $allocation->occupiesOn($day));
        $committed = $wholeSpace ? ($today->isNotEmpty() ? $capacity : 0) : min($capacity, (int) $today->sum('seats_allocated'));
        $unusableReason = match (true) {
            $space->operational_status !== SpaceStatus::Active => $space->operational_status->label(),
            $capacity === 0 => 'no active seats',
            default => null,
        };

        $freeSeats = $space->inventory_mode->hasSeats() && $unusableReason === null
            ? $this->freeSeatLabels($space, $today, $capacity - $committed)
            : [];
        $freeFrom = $unusableReason === null && $capacity - $committed <= 0 ? $this->firstFreeDay($space, $allocations, $day, $capacity) : null;

        return new DayAvailability($unusableReason, $capacity, $committed, $freeSeats, $freeFrom, $this->customerState($space, $unusableReason, $capacity - $committed, $freeFrom, $day));
    }

    /**
     * BR-AV-1 to BR-AV-4: hidden unless listable and usable; otherwise available now (or limited below
     * the threshold for shared zones), available from a date within the horizon, or join the waitlist.
     * The horizon and threshold are unconfirmed values (D-36) in config/inventory.php; without a
     * threshold, "Limited availability" is never shown.
     */
    private function customerState(Space $space, ?string $unusableReason, int $free, ?CarbonImmutable $freeFrom, CarbonImmutable $day): CustomerAvailability
    {
        $limitedBelow = config('inventory.limited_availability_below');

        return match (true) {
            ! $space->is_publicly_listable || $unusableReason !== null => CustomerAvailability::Hidden,
            $free > 0 && $space->inventory_mode !== InventoryMode::Exclusive && is_int($limitedBelow) && $free < $limitedBelow => CustomerAvailability::Limited,
            $free > 0 => CustomerAvailability::AvailableNow,
            $freeFrom !== null && $freeFrom->lessThanOrEqualTo($day->addDays($this->horizonDays())) => CustomerAvailability::AvailableFrom,
            default => CustomerAvailability::JoinWaitlist,
        };
    }

    /**
     * The first day after $day, within the horizon, on which the space has something free.
     *
     * @param  Collection<int, SpaceAllocation>  $allocations
     */
    private function firstFreeDay(Space $space, Collection $allocations, CarbonImmutable $day, int $capacity): ?CarbonImmutable
    {
        $horizonEnd = $day->addDays($this->horizonDays());

        return $allocations
            ->filter(fn (SpaceAllocation $allocation): bool => $allocation->ends_on !== null && $allocation->ends_on->lessThan($horizonEnd))
            ->map(fn (SpaceAllocation $allocation): CarbonImmutable => $allocation->ends_on->addDay())
            ->sort()
            ->first(fn (CarbonImmutable $candidate): bool => $space->inventory_mode === InventoryMode::Exclusive
                ? $allocations->doesntContain(fn (SpaceAllocation $allocation): bool => $allocation->occupiesOn($candidate))
                : $this->usageOn($allocations, $candidate) < $capacity);
    }

    /**
     * @param  Collection<int, SpaceAllocation>  $today
     * @return list<string>
     */
    private function freeSeatLabels(Space $space, Collection $today, int $free): array
    {
        if ($free <= 0) {
            return [];
        }

        $taken = $today->pluck('space_seat_id')->filter()->all();

        return $space->seats->where('is_active', true)->reject(fn (SpaceSeat $seat): bool => in_array($seat->getKey(), $taken, true))->pluck('label')->values()->all();
    }

    /**
     * @param  Collection<int, SpaceAllocation>  $overlapping
     */
    private function seatRefusal(?SpaceSeat $seat, Collection $overlapping): ?string
    {
        if ($seat === null) {
            return 'Choose the seat: in this zone each seat is allocated on its own.';
        }

        if (! $seat->is_active) {
            return 'Seat '.$seat->label.' is not in use, so it cannot be allocated.';
        }

        $onSeat = $overlapping->where('space_seat_id', $seat->getKey());

        return $onSeat->isEmpty() ? null : 'Seat '.$seat->label.' is already allocated for some of these dates: '.$this->list($onSeat).'.';
    }

    /**
     * Refuses when, on any day of the period, the seats already committed plus the new ones exceed the
     * capacity (BR-AV-3, BR-AV-3d).
     *
     * @param  Collection<int, SpaceAllocation>  $overlapping
     */
    private function poolRefusal(int $capacity, int $seats, Collection $overlapping, CarbonImmutable $from, ?CarbonImmutable $until): ?string
    {
        $peak = $this->candidateDays($overlapping, $from, $until)->map(fn (CarbonImmutable $day): int => $this->usageOn($overlapping, $day))->max() ?? 0;

        if ($peak + $seats <= $capacity) {
            return null;
        }

        $free = max(0, $capacity - $peak);

        return 'Only '.$free.' of '.$capacity.' '.str('seat')->plural($capacity).' '.($free === 1 ? 'is' : 'are').' free on every day of these dates, so '.$seats.' cannot be allocated. Already allocated: '.$this->list($overlapping).'.';
    }

    /**
     * The days on which usage can change within a period: its first day and every later start.
     *
     * @param  Collection<int, SpaceAllocation>  $allocations
     * @return Collection<int, CarbonImmutable>
     */
    private function candidateDays(Collection $allocations, CarbonImmutable $from, ?CarbonImmutable $until): Collection
    {
        return $allocations
            ->map(fn (SpaceAllocation $allocation): CarbonImmutable => $allocation->starts_on)
            ->filter(fn (CarbonImmutable $start): bool => $start->greaterThan($from) && ($until === null || $start->lessThanOrEqualTo($until)))
            ->push($from)
            ->unique(fn (CarbonImmutable $day): string => $day->toDateString())
            ->values();
    }

    /**
     * @param  Collection<int, SpaceAllocation>  $allocations
     */
    private function usageOn(Collection $allocations, CarbonImmutable $day): int
    {
        return (int) $allocations->filter(fn (SpaceAllocation $allocation): bool => $allocation->occupiesOn($day))->sum('seats_allocated');
    }

    /**
     * @return Collection<int, SpaceAllocation>
     */
    private function occupyingFrom(Space $space, CarbonImmutable $day): Collection
    {
        return SpaceAllocation::query()->where('space_id', $space->getKey())->occupyingBetween($day, null)->orderBy('starts_on')->get();
    }

    private function activeSeatCount(Space $space): int
    {
        return $space->relationLoaded('seats') ? $space->seats->where('is_active', true)->count() : $space->seats()->where('is_active', true)->count();
    }

    /**
     * @param  Collection<int, SpaceAllocation>  $allocations
     */
    private function list(Collection $allocations): string
    {
        return $allocations->map(fn (SpaceAllocation $allocation): string => $allocation->summary())->implode('; ');
    }

    private function horizonDays(): int
    {
        return (int) config('inventory.availability_horizon_days');
    }
}
