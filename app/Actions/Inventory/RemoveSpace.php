<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Removes a space recorded by mistake. A space that has ever been allocated is never removed: it is
 * decommissioned instead (BR-INV-2). A removed space keeps its code, which is never reused. Audited
 * with the reason.
 */
class RemoveSpace
{
    public function __construct(private RecordInventoryActivity $recordInventoryActivity) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Space $space, string $reason): void
    {
        if (! $user->hasCapabilityForProperty(Capability::ManageInventory, $space->property)) {
            throw new AuthorizationException('You cannot remove this property\'s spaces.');
        }

        if (blank($reason) || mb_strlen($reason) > 300 || preg_match('/[<>]/', $reason) === 1) {
            throw ValidationException::withMessages(['reason' => 'Give the reason for removing this space in plain text, up to 300 characters.']);
        }

        DB::transaction(function () use ($user, $space, $reason): void {
            $space = Space::query()->lockForUpdate()->findOrFail($space->getKey());

            if ($space->allocations()->exists()) {
                throw ValidationException::withMessages(['space' => 'This space has been allocated, so it is kept: decommission it instead (BR-INV-2).']);
            }

            $this->recordInventoryActivity->handle($space, $user, 'space_removed', 'Space removed', trim($reason), [
                'property' => $space->property->code,
                'old' => $space->only(['code', 'name', 'floor_id', 'space_type_id', 'capacity_seats']),
            ]);

            $space->delete();
            SpaceAvailabilityChanged::dispatch($space, 'removed');
        });
    }
}
