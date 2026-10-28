<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\City;
use App\Models\User;

/**
 * Who may see and change market cities in the back office (SECURITY_MODEL §3.1).
 */
class CityPolicy
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

    public function view(User $user, City $city): bool
    {
        return $user->can(Capability::ViewProperties);
    }

    /**
     * Not for a Property Manager, whose changes reach only their assigned properties.
     */
    public function create(User $user): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    public function update(User $user, City $city): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    /**
     * Cities are never deleted: a city is hidden instead (OD-18).
     */
    public function delete(User $user, City $city): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
