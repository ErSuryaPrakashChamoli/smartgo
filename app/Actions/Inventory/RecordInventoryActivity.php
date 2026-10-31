<?php

namespace App\Actions\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Audits an inventory change with the reason staff gave (BR-INV-1, SECURITY_MODEL §7).
 */
class RecordInventoryActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function handle(Model $subject, User $actor, string $event, string $description, string $reason, array $properties = []): void
    {
        activity('inventory')
            ->performedOn($subject)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['reason' => $reason, ...$properties])
            ->log($description);
    }
}
