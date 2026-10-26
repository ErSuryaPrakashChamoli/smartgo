<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see and add revisioned website content in the back office (posts, FAQs, testimonials, case
 * studies). Changes always go through revisions (RevisionPolicy); nothing is deleted here.
 */
abstract class PublishableContentPolicy
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

    public function view(User $user, Model $model): bool
    {
        return $user->can(Capability::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ViewContent) && $user->can(Capability::EditContent);
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->can(Capability::ViewContent);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
