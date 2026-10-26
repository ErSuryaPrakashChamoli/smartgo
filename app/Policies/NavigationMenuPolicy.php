<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\NavigationMenu;
use App\Models\User;

/**
 * Who may see and work on the website's menus in the back office. The four menus are fixed, so none is
 * created or deleted here; changes to a menu go through its revisions (RevisionPolicy).
 */
class NavigationMenuPolicy
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
        return $user->can(Capability::ViewContent);
    }

    public function view(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can(Capability::ViewContent);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, NavigationMenu $navigationMenu): bool
    {
        return $user->can(Capability::ViewContent);
    }

    public function delete(User $user, NavigationMenu $navigationMenu): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
