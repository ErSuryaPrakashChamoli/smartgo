<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Gives an open deal another owner (FR-CRM-4), with the same permission as reassigning its enquiry
 * (BR-CRM-3a, OD-51). The deal is locked; a change someone else made since the actor looked is refused
 * rather than overwritten. The new owner must be active staff who may work on the deal's enquiry. Audited.
 */
class AssignDeal
{
    /**
     * @param  int|null  $expectedOwnerId  the owner the actor saw
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Deal $deal, User $owner, ?int $expectedOwnerId): Deal
    {
        return DB::transaction(function () use ($actor, $deal, $owner, $expectedOwnerId): Deal {
            $deal = Deal::query()->with('lead.property')->lockForUpdate()->findOrFail($deal->getKey());
            Gate::forUser($actor)->authorize('assign', $deal);

            if (! $deal->isOpen()) {
                throw ValidationException::withMessages(['owner' => 'This deal is '.strtolower($deal->outcome()->label()).', so its owner stays as it was.']);
            }

            if ($deal->owner_user_id !== $expectedOwnerId) {
                throw ValidationException::withMessages(['owner' => 'Someone else changed who owns this deal. Reload it and try again.']);
            }

            if (! (User::query()->whereKey($owner->getKey())->where('is_active', true)->exists() && $deal->lead->isWithinScopeOf($owner, Capability::ManageLeads))) {
                throw ValidationException::withMessages(['owner' => 'This person cannot work on this deal\'s enquiry.']);
            }

            if ($deal->owner_user_id === $owner->getKey()) {
                return $deal;
            }

            $previousOwnerId = $deal->owner_user_id;
            $deal->forceFill(['owner_user_id' => $owner->getKey()])->save();

            activity('leads')
                ->performedOn($deal)
                ->causedBy($actor)
                ->event('deal_assigned')
                ->withProperties(['deal_id' => $deal->getKey(), 'from_user_id' => $previousOwnerId, 'to_user_id' => $owner->getKey()])
                ->log('Deal assigned to '.$owner->name);

            return $deal;
        });
    }
}
