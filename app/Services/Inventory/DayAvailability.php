<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\CustomerAvailability;
use Carbon\CarbonImmutable;

/**
 * A space's availability on one day, as staff see it (BR-AV-3: exact counts), with the state a customer
 * would see (BR-AV-1). Derived by SpaceAvailability, never stored.
 */
final readonly class DayAvailability
{
    /**
     * @param  string|null  $unusableReason  why nothing in the space can be allocated, e.g. "Under maintenance"; null when usable
     * @param  int  $capacity  seats the space offers that day: its capacity, or its active seats in a per-seat or hybrid zone
     * @param  int  $committed  seats taken by allocations that day (a whole space counts all its seats)
     * @param  list<string>  $freeSeats  labels of the free seats in a per-seat or hybrid zone
     * @param  CarbonImmutable|null  $freeFrom  when nothing is free: the first day within the horizon with something free
     */
    public function __construct(
        public ?string $unusableReason,
        public int $capacity,
        public int $committed,
        public array $freeSeats,
        public ?CarbonImmutable $freeFrom,
        public CustomerAvailability $customerState,
    ) {}

    public function usable(): bool
    {
        return $this->unusableReason === null;
    }

    public function free(): int
    {
        return max(0, $this->capacity - $this->committed);
    }

    /**
     * The staff summary, e.g. "3 of 12 seats free" or "Occupied; free from 1 Jun 2027".
     */
    public function summary(bool $wholeSpace): string
    {
        if ($this->unusableReason !== null) {
            return 'Not usable: '.$this->unusableReason;
        }

        if ($wholeSpace) {
            $state = $this->committed > 0 ? 'Occupied' : 'Free';
        } else {
            $state = $this->free().' of '.$this->capacity.' '.str('seat')->plural($this->capacity).' free';
        }

        return $state.($this->free() === 0 ? ($this->freeFrom !== null ? '; free from '.$this->freeFrom->format('j M Y') : '; nothing frees up within the horizon') : '');
    }
}
