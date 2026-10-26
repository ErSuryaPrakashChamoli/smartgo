<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Redirect;
use App\Models\User;

/**
 * Who may see the live redirects. They are read-only here: automatic ones follow their page
 * (BR-CMS-6) and manual ones change only by publishing the manual list (OD-66).
 */
class RedirectPolicy
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

    public function view(User $user, Redirect $redirect): bool
    {
        return $user->can(Capability::ManageRedirects);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Redirect $redirect): bool
    {
        return false;
    }

    public function delete(User $user, Redirect $redirect): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
