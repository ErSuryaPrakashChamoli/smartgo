<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\TwinModel;
use App\Models\User;

/**
 * Who may see and manage 3D models (SECURITY_MODEL §3.1 "3D model upload and anchors"): the Super Admin
 * and the Marketing Manager upload and remove them; the Operations Manager sees them. A model never
 * changes after upload; a corrected file is uploaded as a new model.
 */
class TwinModelPolicy
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
        return $user->can(Capability::ViewTwinModels);
    }

    public function view(User $user, TwinModel $twinModel): bool
    {
        return $user->can(Capability::ViewTwinModels);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageTwinModels);
    }

    public function update(User $user, TwinModel $twinModel): bool
    {
        return false;
    }

    public function delete(User $user, TwinModel $twinModel): bool
    {
        return $user->can(Capability::ManageTwinModels);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
