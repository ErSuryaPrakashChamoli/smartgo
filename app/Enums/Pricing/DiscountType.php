<?php

namespace App\Enums\Pricing;

/**
 * The kinds of promotional offer (DATABASE_ERD §4 `offers.discount_type`, PRICING_CONFIGURATION_PROPOSAL §7).
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case FreePeriod = 'free_period';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage off',
            self::Fixed => 'Fixed amount off',
            self::FreePeriod => 'Free period',
        };
    }
}
