<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\LeadCloseReason;
use App\Models\User;

/**
 * Who may keep the lists of reasons for closing an enquiry (BR-CRM-5): the Super Admin, like assignment
 * rules and pipeline stages (SECURITY_MODEL §3.1). Reasons are switched off, never deleted.
 */
class LeadCloseReasonPolicy
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
        return $user->can(Capability::ManageLeadSettings);
    }

    public function view(User $user, LeadCloseReason $reason): bool
    {
        return $user->can(Capability::ManageLeadSettings);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageLeadSettings);
    }

    public function update(User $user, LeadCloseReason $reason): bool
    {
        return $user->can(Capability::ManageLeadSettings);
    }

    public function delete(User $user, LeadCloseReason $reason): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
