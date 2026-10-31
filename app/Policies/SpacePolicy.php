<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Space;
use App\Models\User;

/**
 * Who may see and change spaces and seats (SECURITY_MODEL §3.1 "Spaces and seats"): everyone who sees
 * properties may see them, within their property scope; operations staff and Property Managers (their
 * own properties) change them. The inventory actions check again under the space's lock.
 */
class SpacePolicy
{
    /**
     * A deactivated account can do nothing.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ViewProperties);
    }

    public function view(User $user, Space $space): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProperties, $space->property);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageInventory);
    }

    public function update(User $user, Space $space): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageInventory, $space->property);
    }

    /**
     * Only a space never allocated may be removed (BR-INV-2); RemoveSpace checks that under lock.
     */
    public function delete(User $user, Space $space): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageInventory, $space->property);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
