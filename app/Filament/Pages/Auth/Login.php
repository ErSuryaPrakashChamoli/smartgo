<?php

namespace App\Filament\Pages\Auth;

use App\Actions\Access\RecordFailedTwoStepLogin;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * The back-office sign-in page. It signs staff in exactly as Filament's page does, and also audits
 * a wrong two-step login code, for which Filament fires no event (unlike a wrong password).
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $exception) {
            if ($this->isWrongTwoStepCode($exception) && ($user = $this->getUserUndertakingMultiFactorAuthentication()) instanceof User) {
                app(RecordFailedTwoStepLogin::class)->handle($user);
            }

            throw $exception;
        }
    }

    /**
     * A wrong password clears the pending two-step login before failing, so a failure that leaves it
     * pending and concerns the challenge form can only be a rejected code.
     */
    private function isWrongTwoStepCode(ValidationException $exception): bool
    {
        return filled($this->userUndertakingMultiFactorAuthentication)
            && collect(array_keys($exception->errors()))->contains(fn (string $key): bool => str_starts_with($key, 'data.multiFactor.'));
    }
}
