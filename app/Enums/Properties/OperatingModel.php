<?php

namespace App\Enums\Properties;

/**
 * How SmartGro runs a property (OD-09). Always recorded, never inferred.
 */
enum OperatingModel: string
{
    case OwnedOperated = 'owned_operated';
    case LeasedOperated = 'leased_operated';

    public function label(): string
    {
        return match ($this) {
            self::OwnedOperated => 'Owned and operated',
            self::LeasedOperated => 'Leased and operated',
        };
    }
}
