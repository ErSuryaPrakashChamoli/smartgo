<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\MediaAsset;
use App\Models\User;

/**
 * Who may see and manage the media library. Seeing it is part of working on content; uploading,
 * describing and deleting need `media.manage`.
 */
class MediaAssetPolicy
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
        return $user->can(Capability::ViewContent) || $user->can(Capability::ManageMedia);
    }

    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageMedia);
    }

    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $user->can(Capability::ManageMedia);
    }

    /**
     * Deleting is refused while any content or version uses the asset (DeleteMediaAsset).
     */
    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $user->can(Capability::ManageMedia);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
