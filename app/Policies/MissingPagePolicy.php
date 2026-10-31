<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\MissingPage;
use App\Models\User;

/**
 * Who sees the addresses that answered "not found" (FR-SEO-4): those who manage redirects, who act on
 * them (SECURITY_MODEL §11 `redirects.manage`). They may dismiss an entry; nothing else changes it.
 */
class MissingPagePolicy
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
        return $user->can(Capability::ManageRedirects);
    }

    public function delete(User $user, MissingPage $missingPage): bool
    {
        return $user->can(Capability::ManageRedirects);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MissingPage $missingPage): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
