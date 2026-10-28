<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\MicroMarket;
use App\Models\User;

/**
 * Who may see and change sectors (micro-markets) of a city in the back office (SECURITY_MODEL §3.1).
 */
class MicroMarketPolicy
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

    public function view(User $user, MicroMarket $microMarket): bool
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

    public function update(User $user, MicroMarket $microMarket): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ManageProperties);
    }

    /**
     * Sectors are never deleted: properties keep pointing at them.
     */
    public function delete(User $user, MicroMarket $microMarket): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
