<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\PropertyClosure;
use App\Models\User;

/**
 * Who may see and change a property's closures: everyone who sees properties sees them; those who
 * manage the property record them (interpretation of §3.1 "Properties, buildings, floors"; SaveClosure
 * checks again).
 */
class PropertyClosurePolicy
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

    public function view(User $user, PropertyClosure $closure): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProperties, $closure->property);
    }

    public function update(User $user, PropertyClosure $closure): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageProperties, $closure->property);
    }

    public function delete(User $user, PropertyClosure $closure): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageProperties, $closure->property);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
