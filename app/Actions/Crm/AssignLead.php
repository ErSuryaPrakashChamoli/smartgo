<?php

namespace App\Actions\Crm;

use App\Enums\Access\Capability;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Assigns or reassigns an enquiry's owner by hand, or returns it to the unassigned queue (BR-CRM-3a).
 * The enquiry is locked, the actor's permission and property scope are checked again, and a change
 * made by someone else since the actor looked is refused rather than overwritten. The new owner must
 * be an active member of staff who may work on enquiries about this property. Audited.
 */
class AssignLead
{
    /**
     * @param  int|null  $expectedOwnerId  the owner the actor saw; a different current owner is refused
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Lead $lead, User $actor, ?User $owner, ?int $expectedOwnerId): Lead
    {
        return DB::transaction(function () use ($lead, $actor, $owner, $expectedOwnerId): Lead {
            $lead = Lead::query()->with('property')->lockForUpdate()->findOrFail($lead->getKey());
            Gate::forUser($actor)->authorize('assign', $lead);

            if ($lead->owner_user_id !== $expectedOwnerId) {
                throw ValidationException::withMessages(['owner' => 'Someone else changed who owns this enquiry. Reload it and try again.']);
            }

            if ($owner !== null && ! (User::query()->whereKey($owner->getKey())->where('is_active', true)->exists() && $lead->isWithinScopeOf($owner, Capability::ManageLeads))) {
                throw ValidationException::withMessages(['owner' => 'This person cannot work on enquiries about this location.']);
            }

            if ($lead->owner_user_id === $owner?->getKey()) {
                return $lead;
            }

            $previousOwnerId = $lead->owner_user_id;
            $lead->forceFill(['owner_user_id' => $owner?->getKey()])->save();

            activity('leads')
                ->performedOn($lead)
                ->causedBy($actor)
                ->event('lead_assigned')
                ->withProperties(['reference' => $lead->reference, 'from_user_id' => $previousOwnerId, 'to_user_id' => $owner?->getKey()])
                ->log($owner === null ? 'Enquiry returned to the unassigned queue' : 'Enquiry assigned to '.$owner->name);

            return $lead;
        });
    }

    /**
     * Staff who may own the enquiry: active, allowed to work on enquiries, within its property scope.
     *
     * @return array<int, string> user ID => name
     */
    public function candidates(Lead $lead): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (User $user): bool => $lead->isWithinScopeOf($user, Capability::ManageLeads))
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name])
            ->all();
    }
}
