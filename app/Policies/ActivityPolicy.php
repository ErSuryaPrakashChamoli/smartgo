<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The audit log is read-only for everyone: no create, update or delete abilities exist.
 */
class ActivityPolicy
{
    /**
     * Determine whether the user can view the audit log.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ViewAuditLog);
    }

    /**
     * Determine whether the user can view an audit entry.
     */
    public function view(User $user, Activity $activity): bool
    {
        return $user->can(Capability::ViewAuditLog);
    }
}
