<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Lead;
use App\Models\User;

/**
 * Who may see and work on enquiries (BR-CRM-6, SECURITY_MODEL §11): within the property scope of each
 * capability, checked against the enquiry's property on every request. Enquiries arrive from the
 * website only; none is created or deleted in the back office.
 */
class LeadPolicy
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
        return $user->can(Capability::ViewLeads);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $lead->isWithinScopeOf($user, Capability::ViewLeads);
    }

    /**
     * Changing the status and recording notes and follow-ups.
     */
    public function update(User $user, Lead $lead): bool
    {
        return $lead->isWithinScopeOf($user, Capability::ManageLeads);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $lead->isWithinScopeOf($user, Capability::AssignLeads);
    }

    /**
     * Exports contain personal data, so only staff who may export for every property can (FR-CRM-6).
     */
    public function export(User $user): bool
    {
        return $user->hasCapabilityForEveryProperty(Capability::ExportLeads);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
