<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * The back-office "forgot password" page. Every request gets the same answer and the same empty form,
 * so the page never reveals whether an email address belongs to a staff account. The reset link is
 * still sent only to active staff (Filament checks panel access before sending).
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        parent::request();

        $this->form->fill();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        if (in_array($status, [Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return $this->getSentNotification(Password::RESET_LINK_SENT);
        }

        return parent::getFailureNotification($status);
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return Notification::make()
            ->title('If this email address belongs to a staff account, a password reset link is on its way.')
            ->success();
    }
}
