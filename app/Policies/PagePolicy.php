<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Page;
use App\Models\User;

/**
 * Who may see and work on website pages in the back office. Changes to a page's content always go
 * through its revisions (RevisionPolicy); a page is never edited or deleted directly.
 */
class PagePolicy
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

    public function view(User $user, Page $page): bool
    {
        return $user->can(Capability::ViewContent);
    }

    /**
     * Adding a page creates an unpublished page and its first draft.
     */
    public function create(User $user): bool
    {
        return $user->can(Capability::ViewContent) && $user->can(Capability::EditContent);
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, Page $page): bool
    {
        return $user->can(Capability::ViewContent);
    }

    /**
     * Pages are not deleted; unpublishing arrives with scheduled unpublishing (BR-CMS-5).
     */
    public function delete(User $user, Page $page): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
