<?php

namespace App\Actions\Access;

use App\Models\User;

/**
 * Clears every two-step login method for a user who has lost their device. Because two-step
 * login is required in the back office, the user must set up a new method at their next sign-in.
 * The user is saved quietly so that this explicit reset entry replaces the model's generic
 * two-step login audit entry.
 */
class ResetTwoFactorAuthentication
{
    public function handle(User $user, ?User $actor = null): void
    {
        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'has_email_authentication' => false,
        ])->saveQuietly();

        activity('access')
            ->performedOn($user)
            ->causedBy($actor)
            ->event('two_factor_reset')
            ->log('Two-step login reset');
    }
}
