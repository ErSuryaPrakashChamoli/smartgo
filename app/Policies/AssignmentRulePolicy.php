<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\AssignmentRule;
use App\Models\User;

/**
 * Who may keep the rules that give new enquiries an owner (FR-CRM-2): the Super Admin (OD-36,
 * SECURITY_MODEL §3.1). Rules are switched off, never deleted, so past decisions keep their rule.
 */
class AssignmentRulePolicy
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
        return $user->can(Capability::ManageAssignmentRules);
    }

    public function view(User $user, AssignmentRule $rule): bool
    {
        return $user->can(Capability::ManageAssignmentRules);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageAssignmentRules);
    }

    public function update(User $user, AssignmentRule $rule): bool
    {
        return $user->can(Capability::ManageAssignmentRules);
    }

    public function delete(User $user, AssignmentRule $rule): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
