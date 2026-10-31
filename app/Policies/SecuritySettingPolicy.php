<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\User;

class SecuritySettingPolicy
{
    /**
     * Whether two-step login is required is part of managing staff access: matrix row "Users, roles,
     * MFA reset", Super Admin only (SECURITY_MODEL §3.1, OD-124).
     */
    public function update(User $user): bool
    {
        return $user->can(Capability::ManageUsers);
    }
}
