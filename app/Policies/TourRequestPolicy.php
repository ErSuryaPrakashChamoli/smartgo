<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\TourRequest;
use App\Models\User;

/**
 * Who may see and work on tours (SECURITY_MODEL §3.1 "Tours", §11 `tours.view` and `tours.manage`):
 * within the property scope of each capability, checked against the tour's property on every request.
 * Tours are arranged through the website or from an enquiry, never created bare, and never deleted.
 */
class TourRequestPolicy
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
        return $user->can(Capability::ViewTours);
    }

    public function view(User $user, TourRequest $tourRequest): bool
    {
        return $tourRequest->isWithinScopeOf($user, Capability::ViewTours);
    }

    /**
     * Confirming, moving, cancelling, recording the outcome and naming the host.
     */
    public function update(User $user, TourRequest $tourRequest): bool
    {
        return $tourRequest->isWithinScopeOf($user, Capability::ManageTours);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, TourRequest $tourRequest): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
