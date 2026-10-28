<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\MediaAsset;
use App\Models\User;

/**
 * Who may see and manage the media library. Seeing it is part of working on content; uploading,
 * describing and deleting need `media.manage`. A Property Manager works only with the photos of their
 * assigned properties: they upload photos only for those, and see, describe or delete no others.
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

    /**
     * Seeing one image, for example in a preview. Staff who review property profiles see the photos of
     * the properties within their scope without opening the library.
     */
    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        if ($user->can(Capability::ViewContent) || $user->hasCapabilityForEveryProperty(Capability::ManageMedia)) {
            return true;
        }

        $property = $mediaAsset->depictedProperty;

        return $property !== null && collect([Capability::ManageMedia, Capability::ViewProfiles])
            ->contains(fn (Capability $capability): bool => $user->hasCapabilityForProperty($capability, $property));
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageMedia);
    }

    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->managesAsset($user, $mediaAsset);
    }

    /**
     * Deleting is refused while any content or version uses the asset (DeleteMediaAsset).
     */
    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->managesAsset($user, $mediaAsset);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * An image of no particular property is managed only by staff who manage every property's media.
     */
    private function managesAsset(User $user, MediaAsset $mediaAsset): bool
    {
        $property = $mediaAsset->depictedProperty;

        return $property === null
            ? $user->hasCapabilityForEveryProperty(Capability::ManageMedia)
            : $user->hasCapabilityForProperty(Capability::ManageMedia, $property);
    }
}
