<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\PropertyProfile;
use App\Models\User;

/**
 * Who may open property profiles in the back office (FR-CMS-6). A Property Manager sees only the
 * profiles of their assigned properties; changes always go through revisions (RevisionPolicy), and
 * profiles are created with their property and never deleted.
 */
class PropertyProfilePolicy
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

    public function view(User $user, PropertyProfile $propertyProfile): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewProfiles, $propertyProfile->property);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, PropertyProfile $propertyProfile): bool
    {
        return $this->view($user, $propertyProfile);
    }

    public function delete(User $user, PropertyProfile $propertyProfile): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
