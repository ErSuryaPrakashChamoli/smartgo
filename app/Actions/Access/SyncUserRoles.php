<?php

namespace App\Actions\Access;

use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a user's staff roles and records the change in the audit log.
 */
class SyncUserRoles
{
    /**
     * @param  list<StaffRole>  $roles
     *
     * @throws ValidationException when an actor would remove their own Super Admin role
     */
    public function handle(User $user, array $roles, ?User $actor = null): void
    {
        $previousRoles = $user->getRoleNames()->sort()->values()->all();
        $newRoles = collect($roles)->map(fn (StaffRole $role): string => $role->value)->unique()->sort()->values()->all();

        if ($actor?->is($user)
            && in_array(StaffRole::SuperAdmin->value, $previousRoles, true)
            && ! in_array(StaffRole::SuperAdmin->value, $newRoles, true)) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot remove the Super Admin role from your own account.',
            ]);
        }

        if ($previousRoles === $newRoles) {
            return;
        }

        $user->syncRoles($newRoles);

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('roles_changed')
            ->withProperties(['old' => $previousRoles, 'new' => $newRoles])
            ->log('Staff roles changed');
    }
}
