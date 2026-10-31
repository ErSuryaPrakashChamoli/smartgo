<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Enums\Pricing\OfferStatus;
use App\Models\Offer;
use App\Models\User;

/**
 * Who may see and draft offers (SECURITY_MODEL §3.1 "Offers (create)", "View prices"). Approval is checked
 * by ChangeOfferState (not own, under lock). Offers are never deleted; they end or expire.
 */
class OfferPolicy
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
        return $user->can(Capability::ViewPrices);
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->can(Capability::ViewPrices);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManageOffers);
    }

    public function update(User $user, Offer $offer): bool
    {
        return $offer->status === OfferStatus::Draft && $user->can(Capability::ManageOffers);
    }

    public function delete(User $user, Offer $offer): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
