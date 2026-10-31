<?php

namespace App\Enums\Pricing;

/**
 * What a price plan's amount is for (DATABASE_ERD §4 `price_plans.unit`, with the cabin and day-bundle
 * units added by the approved pricing structure, PRICING_CONFIGURATION_PROPOSAL §12, OD-35). Which units
 * each category may use is in config/pricing.php.
 */
enum PriceUnit: string
{
    case PerSeatMonth = 'per_seat_month';
    case PerSpaceMonth = 'per_space_month';
    case PerCabinMonth = 'per_cabin_month';
    case PerHour = 'per_hour';
    case PerHalfDay = 'per_half_day';
    case PerDay = 'per_day';
    case PerMonthService = 'per_month_service';
    case BundleDays = 'bundle_days';

    public function label(): string
    {
        return match ($this) {
            self::PerSeatMonth => 'Per seat per month',
            self::PerSpaceMonth => 'Per space per month',
            self::PerCabinMonth => 'Per cabin per month',
            self::PerHour => 'Per hour',
            self::PerHalfDay => 'Per half day',
            self::PerDay => 'Per day',
            self::PerMonthService => 'Per month (service plan)',
            self::BundleDays => 'Bundle of days',
        };
    }
}
