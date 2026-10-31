<?php

namespace App\Actions\Access;

use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Makes two-step login compulsory for every staff member, or optional again (OD-124), audited. Only
 * local development and the tests can make it optional; elsewhere it stays required. Staff who set
 * up two-step login keep it either way.
 */
class ChangeTwoStepLoginRequirement
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, bool $isRequired): void
    {
        Gate::forUser($actor)->authorize('update', SecuritySetting::class);

        if (! $isRequired && SecuritySetting::isTwoStepLoginAlwaysRequired()) {
            throw ValidationException::withMessages([
                'two_step_login_required' => 'Two-step login is always required on this site.',
            ]);
        }

        DB::transaction(function () use ($actor, $isRequired): void {
            $setting = SecuritySetting::query()->lockForUpdate()->sole();

            if ($setting->two_step_login_required === $isRequired) {
                return;
            }

            $setting->forceFill(['two_step_login_required' => $isRequired])->save();

            activity('access')
                ->performedOn($setting)
                ->causedBy($actor)
                ->event('two_step_login_requirement_changed')
                ->withProperties(['required' => $isRequired])
                ->log($isRequired ? 'Two-step login made compulsory' : 'Two-step login made optional');
        });
    }
}
