<?php

namespace App\Enums\Properties;

/**
 * Where a property stands operationally (DATABASE_ERD §3). Operations staff set it; whether a property
 * is on the website also needs its published profile (marketing publication). An open property can be
 * listed; a planned or pre-opening one only as "Opening {month year}" once its opening date is known,
 * with an enquiry option and nothing bookable (BR-AV-6); a temporarily closed or closed one never.
 */
enum PropertyStatus: string
{
    case Planned = 'planned';
    case PreOpening = 'pre_opening';
    case Open = 'open';
    case TemporarilyClosed = 'temporarily_closed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::PreOpening => 'Pre-opening',
            self::Open => 'Open',
            self::TemporarilyClosed => 'Temporarily closed',
            self::Closed => 'Closed',
        };
    }

    /**
     * Whether a property in this state may appear on the website (BR-AV-6), given its opening date.
     */
    public function allowsListing(bool $hasOpeningDate): bool
    {
        return match ($this) {
            self::Open => true,
            self::Planned, self::PreOpening => $hasOpeningDate,
            self::TemporarilyClosed, self::Closed => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function listableWithoutOpeningDate(): array
    {
        return [self::Open];
    }

    /**
     * @return list<self>
     */
    public static function listableWithOpeningDate(): array
    {
        return [self::Planned, self::PreOpening];
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::PreOpening => 'info',
            self::TemporarilyClosed => 'warning',
            default => 'gray',
        };
    }
}
