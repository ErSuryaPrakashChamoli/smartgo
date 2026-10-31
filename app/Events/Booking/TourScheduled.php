<?php

namespace App\Events\Booking;

use App\Models\TourRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A tour took a place in a slot: requested by a visitor or arranged by staff (API_CONTRACTS §3.2).
 * Dispatched after the transaction commits. Nothing listens yet: notifying the enquiry's owner and the
 * visitor waits for a verified channel (BR-TR-3; OD-111 d, input O-1).
 */
class TourScheduled implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public TourRequest $tourRequest) {}
}
