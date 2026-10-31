<?php

namespace App\Enums\Inventory;

/**
 * How a space's availability is counted (DATABASE_ERD §3.2, OD-10, OD-15). Every write that commits
 * inventory locks the space's own row first, for a zone too (ADR-0004).
 */
enum InventoryMode: string
{
    /** The whole space is used by one agreement at a time: a cabin, room or suite. */
    case Exclusive = 'exclusive';

    /** Each seat of the zone is tracked on its own (dedicated desks). */
    case PerSeat = 'per_seat';

    /** Agreements draw seats from the zone's capacity (hot desks). */
    case Pooled = 'pooled';

    /** Some seats are assigned individually; the remaining active seats form a pool (OD-15). */
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Exclusive => 'Whole space',
            self::PerSeat => 'Individual seats',
            self::Pooled => 'Shared pool of seats',
            self::Hybrid => 'Hybrid zone',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exclusive => 'One agreement uses the whole space at a time, such as a cabin, room or suite.',
            self::PerSeat => 'Each seat is assigned on its own, such as dedicated desks.',
            self::Pooled => 'Agreements take a number of seats from the zone, such as hot desks.',
            self::Hybrid => 'Some seats are assigned individually; the other active seats form a shared pool.',
        };
    }

    /**
     * Whether the zone's seats are recorded one by one.
     */
    public function hasSeats(): bool
    {
        return in_array($this, [self::PerSeat, self::Hybrid], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])->all();
    }
}
