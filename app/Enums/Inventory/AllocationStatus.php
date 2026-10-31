<?php

namespace App\Enums\Inventory;

/**
 * Where an allocation stands (DATABASE_ERD §3, FR-INV-3). Reserved, active and notice-period
 * allocations occupy their space or seats for their dates (BR-AV-2); ended and cancelled ones never do.
 */
enum AllocationStatus: string
{
    /** Agreed, starting later. */
    case Reserved = 'reserved';

    case Active = 'active';

    /** Notice has been given; it ends on its end date. */
    case NoticePeriod = 'notice_period';

    case Ended = 'ended';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Reserved => 'Reserved',
            self::Active => 'Active',
            self::NoticePeriod => 'Notice given',
            self::Ended => 'Ended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Reserved => 'info',
            self::Active => 'success',
            self::NoticePeriod => 'warning',
            self::Ended, self::Cancelled => 'gray',
        };
    }

    public function occupies(): bool
    {
        return in_array($this, self::occupying(), true);
    }

    /**
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Reserved, self::Active, self::NoticePeriod];
    }
}
