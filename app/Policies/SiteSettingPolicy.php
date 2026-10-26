<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

/**
 * Who may see and work on each public settings group in the back office: contact details, social
 * links (brand), tracking IDs and legal details each have their own permissions (SECURITY_MODEL §3.1).
 * The groups are fixed; changes go through revisions (RevisionPolicy).
 */
class SiteSettingPolicy
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
        return SiteSetting::handlesVisibleTo($user) !== [];
    }

    public function view(User $user, SiteSetting $siteSetting): bool
    {
        return $user->can($siteSetting->revisionCapabilities()['view']);
    }

    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Opening the editor; what can be changed there is decided per revision.
     */
    public function update(User $user, SiteSetting $siteSetting): bool
    {
        return $user->can($siteSetting->revisionCapabilities()['view']);
    }

    public function delete(User $user, SiteSetting $siteSetting): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
