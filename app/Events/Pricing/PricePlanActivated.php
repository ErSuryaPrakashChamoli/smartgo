<?php

namespace App\Events\Pricing;

use App\Models\PricePlan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A price plan was activated (SYSTEM_ARCHITECTURE §3, domain events). Dispatched after the transaction
 * commits. Nothing caches prices yet; surfaces that show prices later listen here.
 */
class PricePlanActivated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public PricePlan $pricePlan) {}
}
