<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\RedirectMap;
use App\Models\User;

/**
 * Who may open the list of redirects made by hand. It is never created or deleted here; changes go
 * through its revisions (RevisionPolicy, OD-66).
 */
class RedirectMapPolicy
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

    public function view(User $user, RedirectMap $redirectMap): bool
    {
        return $user->can(Capability::ManageRedirects);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RedirectMap $redirectMap): bool
    {
        return $user->can(Capability::ManageRedirects);
    }

    public function delete(User $user, RedirectMap $redirectMap): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
