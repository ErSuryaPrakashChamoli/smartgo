<?php

namespace App\Enums\Inventory;

/**
 * A space's operational status (DATABASE_ERD §3), set by operations staff with a reason (FR-INV-2).
 * Only an active space can be allocated or booked (BR-AV-4).
 */
enum SpaceStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';
    case Decommissioned = 'decommissioned';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Maintenance => 'Under maintenance',
            self::OutOfService => 'Out of service',
            self::Decommissioned => 'Decommissioned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Maintenance => 'warning',
            self::OutOfService => 'danger',
            self::Decommissioned => 'gray',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
