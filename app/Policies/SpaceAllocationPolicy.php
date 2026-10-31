<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\SpaceAllocation;
use App\Models\User;

/**
 * Who may see and record allocations (SECURITY_MODEL §3.1 "Allocations"): Super Admin, Operations
 * Manager and Property Managers (own properties) record them; Sales/CRM and Finance see them; Marketing
 * does not. Allocations are never deleted.
 */
class SpaceAllocationPolicy
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
        return $user->can(Capability::ViewAllocations);
    }

    public function view(User $user, SpaceAllocation $allocation): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewAllocations, $allocation->space->property);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageAllocations);
    }

    public function update(User $user, SpaceAllocation $allocation): bool
    {
        return $allocation->status->occupies() && $user->hasCapabilityForProperty(Capability::ManageAllocations, $allocation->space->property);
    }

    public function delete(User $user, SpaceAllocation $allocation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
