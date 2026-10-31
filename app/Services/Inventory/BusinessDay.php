<?php

namespace App\Services\Inventory;

use Carbon\CarbonImmutable;

/**
 * Today's date as SmartGro's business sees it. The application clock runs in UTC, but every property is
 * in India (OD-02) and properties have no time zone of their own yet, so "today" is the calendar date in
 * the back office's time zone (Asia/Kolkata). Returned as midnight of that date, like the date columns, so
 * comparisons with them are by calendar day.
 */
final class BusinessDay
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(config('backoffice.timezone'))->toDateString());
    }
}
