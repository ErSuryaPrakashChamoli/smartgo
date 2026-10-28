<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\OfferingProfile;
use App\Models\User;

/**
 * Who may open workspace offering profiles in the back office (FR-CMS-6). A Property Manager sees only
 * those of their assigned properties; changes always go through revisions (RevisionPolicy). Profiles
 * are created with their offering and never deleted.
 */
class OfferingProfilePolicy
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
        return $user->can(Capability::ViewProfiles);
    }

    public function view(User $user, OfferingProfile $offeringProfile): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProfiles, $offeringProfile->owningProperty());
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, OfferingProfile $offeringProfile): bool
    {
        return $this->view($user, $offeringProfile);
    }

    public function delete(User $user, OfferingProfile $offeringProfile): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
