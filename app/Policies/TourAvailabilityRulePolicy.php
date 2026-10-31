<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\TourAvailabilityRule;
use App\Models\User;

/**
 * Who may see and change a property's tour hours (§3.1 "Tours"): those who see tours see them; those
 * who manage the property's tours change them (SaveTourRule checks again).
 */
class TourAvailabilityRulePolicy
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

    public function view(User $user, TourAvailabilityRule $rule): bool
    {
        return $user->hasCapabilityForProperty(Capability::ViewTours, $rule->property);
    }

    public function update(User $user, TourAvailabilityRule $rule): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageTours, $rule->property);
    }

    public function delete(User $user, TourAvailabilityRule $rule): bool
    {
        return $user->hasCapabilityForProperty(Capability::ManageTours, $rule->property);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
