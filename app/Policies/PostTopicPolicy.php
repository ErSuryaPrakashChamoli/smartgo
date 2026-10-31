<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\PostTopic;
use App\Models\User;

/**
 * Who may keep the list of insight topics: staff who edit website content (SECURITY_MODEL §3.1). A topic
 * that a published article shows can no longer be changed, and one that any article uses cannot be
 * deleted (PostTopic).
 */
class PostTopicPolicy
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

    public function view(User $user, PostTopic $topic): bool
    {
        return $user->can(Capability::ViewContent);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::EditContent);
    }

    public function update(User $user, PostTopic $topic): bool
    {
        return $user->can(Capability::EditContent) && ! $topic->isOnWebsite();
    }

    public function delete(User $user, PostTopic $topic): bool
    {
        return $user->can(Capability::EditContent) && ! $topic->isUsed();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
