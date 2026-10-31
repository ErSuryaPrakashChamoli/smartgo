<?php

namespace App\Services\Pricing;

use App\Enums\Pricing\PricePlanStatus;
use App\Enums\Pricing\PriceUnit;
use App\Models\Offering;
use App\Models\PricePlan;
use App\Models\Space;
use Carbon\CarbonImmutable;

/**
 * The price that applies on a day (BR-PR-1, PRICING_CONFIGURATION_PROPOSAL §3): a space's own active
 * plan when it has one for that unit, otherwise the offering's. Only active plans count, and only on the
 * days their dates cover. Bookings and quotations will snapshot what this returns (BR-PR-6).
 */
class PriceLookup
{
    public function planFor(Offering $offering, ?Space $space, PriceUnit $unit, CarbonImmutable $day): ?PricePlan
    {
        $applying = fn (?int $spaceId): ?PricePlan => PricePlan::query()
            ->sameScope($offering->getKey(), $spaceId, $unit)
            ->where('status', PricePlanStatus::Active->value)
            ->overlapping($day, $day)
            ->first();

        return ($space !== null ? $applying($space->getKey()) : null) ?? $applying(null);
    }
}
