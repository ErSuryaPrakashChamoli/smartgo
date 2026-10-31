<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Space;
use App\Models\SpaceType;
use App\Models\User;

/**
 * Space types are reference data like the workspace categories (DATABASE_ERD §3.1), so the staff who
 * manage categories manage them (SECURITY_MODEL §3.1 "Product categories and non-financial rules").
 * A type in use is never deleted.
 */
class SpaceTypePolicy
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

    public function view(User $user, SpaceType $spaceType): bool
    {
        return $user->can(Capability::ViewProperties);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageProducts);
    }

    public function update(User $user, SpaceType $spaceType): bool
    {
        return $user->can(Capability::ManageProducts);
    }

    public function delete(User $user, SpaceType $spaceType): bool
    {
        return $user->can(Capability::ManageProducts) && ! Space::withTrashed()->where('space_type_id', $spaceType->getKey())->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
