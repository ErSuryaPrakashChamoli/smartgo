<?php

namespace App\Actions\Pricing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits a price plan or offer change (SECURITY_MODEL §7: price plan create, update, approve, activate;
 * offer approve). The plan's or offer's page shows these entries as its history.
 */
class RecordPricingActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(Model $subject, User $actor, string $event, string $description, array $properties = []): void
    {
        activity('pricing')
            ->performedOn($subject)
            ->causedBy($actor)
            ->event($event)
            ->withProperties($properties)
            ->log($description);
    }
}
