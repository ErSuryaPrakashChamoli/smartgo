<?php

namespace App\Enums\Inventory;

/**
 * The availability states customers may see (BR-AV-1). Staff see exact counts instead (BR-AV-3).
 */
enum CustomerAvailability: string
{
    case AvailableNow = 'available_now';
    case AvailableFrom = 'available_from';
    case Limited = 'limited';
    case JoinWaitlist = 'join_waitlist';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::AvailableNow => 'Available now',
            self::AvailableFrom => 'Available from',
            self::Limited => 'Limited availability',
            self::JoinWaitlist => 'Join waitlist',
            self::Hidden => 'Hidden',
        };
    }
}
