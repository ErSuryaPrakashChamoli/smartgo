<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\SpaceStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use App\Services\Inventory\SpaceAvailability;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes a space's operational status with a reason (FR-INV-2): audited with both statuses and the
 * reason, and announced as an availability change (BR-SOT-4). Taking an active space out of use
 * reduces supply, so it locks the space first and is refused, with the allocations concerned, while
 * anything is allocated in it now or later (BR-INV-4). A status someone else changed meanwhile is never
 * overwritten.
 */
class ChangeSpaceStatus
{
    public function __construct(
        private RecordInventoryActivity $recordInventoryActivity,
        private SpaceAvailability $spaceAvailability,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, Space $space, SpaceStatus $status, SpaceStatus $expected, string $reason): Space
    {
        if (! $user->hasCapabilityForProperty(Capability::ManageInventory, $space->property)) {
            throw new AuthorizationException('You cannot change the status of this property\'s spaces.');
        }

        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 300 || preg_match('/[<>]/', $reason) === 1) {
            throw ValidationException::withMessages(['reason' => 'Give the reason for the change in plain text, up to 300 characters.']);
        }

        return DB::transaction(function () use ($user, $space, $status, $expected, $reason): Space {
            $space = Space::query()->lockForUpdate()->findOrFail($space->getKey());

            if ($space->operational_status !== $expected) {
                throw ValidationException::withMessages(['status' => 'Someone else changed this space\'s status. Reload it and try again.']);
            }

            if ($status === $space->operational_status) {
                throw ValidationException::withMessages(['status' => 'The space already has this status.']);
            }

            if ($space->operational_status->isUsable() && ! $status->isUsable()
                && ($commitments = $this->spaceAvailability->commitmentsFrom($space, BusinessDay::today()))->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => 'This space has allocations now or later; move, end or cancel them first (BR-INV-4): '.$commitments->map->summary()->implode('; ').'.']);
            }

            $from = $space->operational_status;
            $space->forceFill(['operational_status' => $status])->save();

            $this->recordInventoryActivity->handle($space, $user, 'space_status_changed', 'Space status changed', $reason, [
                'property' => $space->property->code,
                'old' => ['operational_status' => $from->value],
                'attributes' => ['operational_status' => $status->value],
            ]);
            SpaceAvailabilityChanged::dispatch($space, 'status');

            return $space;
        });
    }
}
