<?php

namespace App\Enums\Booking;

/**
 * Where a tour stands (DATABASE_ERD §5, the approved tour journey). A visitor's request holds its place
 * in the slot until staff confirm or cancel it; a tour staff schedule with the visitor is confirmed at
 * once. Requested and confirmed tours count towards a slot's limit (BR-TR-1); the others never do. A
 * changed time is recorded in the tour's history rather than as a status.
 */
enum TourStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested: not yet confirmed',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Confirmed => 'success',
            self::Completed, self::NoShow, self::Cancelled => 'gray',
        };
    }

    /**
     * Whether the tour takes a place in its slot and can still be confirmed, moved or cancelled.
     */
    public function isUpcoming(): bool
    {
        return in_array($this, self::holdingPlace(), true);
    }

    /**
     * @return list<self>
     */
    public static function holdingPlace(): array
    {
        return [self::Requested, self::Confirmed];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
