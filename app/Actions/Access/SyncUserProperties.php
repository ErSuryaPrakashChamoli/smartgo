<?php

namespace App\Actions\Access;

use App\Models\Property;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the properties a staff member is assigned to (`property_user`), and whether a Sales/CRM
 * Executive works on every property, and records each change in the audit log. A Property Manager, and
 * a Sales/CRM Executive who does not work on every property, see and handle only the assigned
 * properties and their enquiries (SECURITY_MODEL §3.1, BR-CRM-6). The change applies to the next
 * request: lists, searches and record pages check the assignments every time.
 */
class SyncUserProperties
{
    /**
     * @param  list<int>  $propertyIds
     * @param  bool|null  $hasAllPropertyAccess  null leaves it unchanged
     *
     * @throws ValidationException when a property does not exist
     */
    public function handle(User $user, array $propertyIds, ?User $actor = null, ?bool $hasAllPropertyAccess = null): void
    {
        if ($hasAllPropertyAccess !== null && $user->has_all_property_access !== $hasAllPropertyAccess) {
            $user->forceFill(['has_all_property_access' => $hasAllPropertyAccess])->save();

            activity('access')
                ->performedOn($user)
                ->causedBy($actor)
                ->event('property_reach_changed')
                ->withProperties(['all_properties' => $hasAllPropertyAccess])
                ->log($hasAllPropertyAccess ? 'Now works on every property' : 'Now limited to assigned properties');
        }

        $propertyIds = array_values(array_unique(array_map(intval(...), $propertyIds)));

        if (Property::query()->whereKey($propertyIds)->count() !== count($propertyIds)) {
            throw ValidationException::withMessages(['property_ids' => 'Choose properties from the list.']);
        }

        $changes = $user->assignedProperties()->sync(array_fill_keys($propertyIds, ['assigned_by' => $actor?->getKey()]));

        if ($changes['attached'] === [] && $changes['detached'] === []) {
            return;
        }

        $codes = fn (array $ids): array => Property::query()->whereKey($ids)->orderBy('code')->pluck('code')->all();

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('properties_assigned')
            ->withProperties(['added' => $codes($changes['attached']), 'removed' => $codes($changes['detached'])])
            ->log('Assigned properties changed');
    }
}
