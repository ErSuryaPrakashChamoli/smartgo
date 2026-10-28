<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Product;
use App\Models\User;

/**
 * Who may see and change workspace categories in the back office (SECURITY_MODEL §3.1).
 */
class ProductPolicy
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

    public function view(User $user, Product $product): bool
    {
        return $user->can(Capability::ViewProperties);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageProducts);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can(Capability::ManageProducts);
    }

    /**
     * Categories are never deleted: one that is no longer sold is switched inactive.
     */
    public function delete(User $user, Product $product): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
