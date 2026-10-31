<?php

namespace App\Enums\Pricing;

/**
 * How a plan's security deposit is set (PRICING_CONFIGURATION_PROPOSAL §4, §12). Its value is Finance's
 * to enter (PR-3); none is assumed.
 */
enum DepositType: string
{
    case Months = 'months';
    case Fixed = 'fixed';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Months => 'Months of the fee',
            self::Fixed => 'Fixed amount',
            self::None => 'No deposit',
        };
    }
}
