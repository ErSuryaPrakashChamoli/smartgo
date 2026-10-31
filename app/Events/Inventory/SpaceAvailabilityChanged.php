<?php

namespace App\Events\Inventory;

use App\Models\Space;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A space's availability may have changed: its status, mode, seats, capacity or allocations (BR-SOT-4,
 * FR-INV-2). Dispatched after the transaction commits, so listeners never see a rolled-back change.
 * Every surface reads availability live from SpaceAvailability today, so nothing is cached to purge;
 * caches added later listen here (the 60-second propagation of BR-SOT-4).
 */
class SpaceAvailabilityChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Space $space, public string $change) {}
}
