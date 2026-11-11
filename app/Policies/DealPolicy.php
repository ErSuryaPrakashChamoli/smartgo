<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Deal;
use App\Models\User;

/**
 * Who may see and work on deals: whoever may see and work on the deal's enquiry, within the same property
 * scope ("Leads, contacts, deals" is one row of SECURITY_MODEL §3.1; BR-CRM-6), checked on every request.
 * Deals are opened from an enquiry (OpenDeal), never created blank here, and never deleted. Changing the
 * owner follows the enquiry's assignment permission (BR-CRM-3a, OD-51).
 */
class DealPolicy
{
    /**
     * A deactivated account can do nothing, and nothing exists without the deal tables.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active && Deal::isAvailable() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ViewLeads);
    }

    public function view(User $user, Deal $deal): bool
    {
        return $deal->lead->isWithinScopeOf($user, Capability::ViewLeads);
    }

    /**
     * Changing the values, moving, proposing spaces, winning and losing.
     */
    public function update(User $user, Deal $deal): bool
    {
        return $deal->lead->isWithinScopeOf($user, Capability::ManageLeads);
    }

    public function assign(User $user, Deal $deal): bool
    {
        return $deal->lead->isWithinScopeOf($user, Capability::AssignLeads);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Deal $deal): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
