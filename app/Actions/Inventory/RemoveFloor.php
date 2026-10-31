<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Models\Floor;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a floor recorded by mistake, only while no space, current or removed, is on it. Operations
 * staff only (SECURITY_MODEL §3.1: Property Managers neither add nor delete floors). Audited.
 */
class RemoveFloor
{
    public function __construct(private RecordInventoryActivity $recordInventoryActivity) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Floor $floor, string $reason): void
    {
        if (! $user->hasCapabilityForEveryProperty(Capability::ManageProperties)) {
            throw new AuthorizationException('Only operations staff can remove floors.');
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give the reason for removing this floor.']);
        }

        DB::transaction(function () use ($user, $floor, $reason): void {
            $floor = Floor::query()->with('property')->lockForUpdate()->findOrFail($floor->getKey());

            if (Space::withTrashed()->where('floor_id', $floor->getKey())->exists()) {
                throw ValidationException::withMessages(['floor' => 'This floor has spaces, so it cannot be removed.']);
            }

            $this->recordInventoryActivity->handle($floor, $user, 'floor_removed', 'Floor removed', $reason, [
                'property' => $floor->property->code,
                'old' => $floor->only(['building_id', 'building_level', 'label']),
            ]);

            $floor->delete();
        });
    }
}
