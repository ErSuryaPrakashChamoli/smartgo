<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Property;
use App\Models\User;

/**
 * Who may see and change properties and their buildings in the back office (SECURITY_MODEL §3.1). A
 * Property Manager sees and edits only their assigned properties and cannot add one.
 */
class PropertyPolicy
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

    public function view(User $user, Property $property): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProperties, $property);
    }

    public function create(User $user): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    public function update(User $user, Property $property): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageProperties, $property);
    }

    /**
     * Properties are never deleted: a property that stops trading is set to closed, so its history stays.
     */
    public function delete(User $user, Property $property): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
