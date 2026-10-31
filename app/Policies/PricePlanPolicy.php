<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Enums\Pricing\PricePlanStatus;
use App\Models\PricePlan;
use App\Models\User;

/**
 * Who may see and enter price plans (SECURITY_MODEL §3.1 "Price plans", "View prices"): every role that
 * sees offerings sees them (Property Managers for their own properties); Finance and the Super Admin enter
 * drafts. Activation is checked by ChangePricePlanState (four-eyes, under lock). Plans are never deleted.
 */
class PricePlanPolicy
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

    public function view(User $user, PricePlan $pricePlan): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewPrices, $pricePlan->offering->property);
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ManagePrices);
    }

    public function update(User $user, PricePlan $pricePlan): bool
    {
        return $pricePlan->status === PricePlanStatus::Draft && $user->can(Capability::ManagePrices);
    }

    public function delete(User $user, PricePlan $pricePlan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
