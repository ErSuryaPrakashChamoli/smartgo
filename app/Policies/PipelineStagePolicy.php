<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;

/**
 * Who may keep the pipeline stages (FR-CRM-4): the Super Admin (OD-36, SECURITY_MODEL §3.1 "Assignment
 * rules, pipeline stages"). Stages are switched off, never deleted, so past deals keep theirs. Nothing is
 * allowed on a database without the deal tables.
 */
class PipelineStagePolicy
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
        return $user->can(Capability::ManagePipelineStages);
    }

    public function view(User $user, PipelineStage $stage): bool
    {
        return $user->can(Capability::ManagePipelineStages);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManagePipelineStages);
    }

    public function update(User $user, PipelineStage $stage): bool
    {
        return $user->can(Capability::ManagePipelineStages);
    }

    public function delete(User $user, PipelineStage $stage): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
