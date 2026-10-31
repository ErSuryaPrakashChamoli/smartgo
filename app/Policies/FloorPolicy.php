<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Floor;
use App\Models\User;

/**
 * Who may see and change floors (SECURITY_MODEL §3.1 "Properties, buildings, floors"): everyone who
 * sees properties sees them; operations staff add, change and remove them; a Property Manager only
 * changes their own property's floors.
 */
class FloorPolicy
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

    public function view(User $user, Floor $floor): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProperties, $floor->property);
    }

    public function create(User $user): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    public function update(User $user, Floor $floor): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageProperties, $floor->property);
    }

    public function delete(User $user, Floor $floor): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
