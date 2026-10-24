<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes sign-in, failed sign-in and sign-out events to the audit log (SECURITY_MODEL §7).
 * Submitted passwords are never recorded.
 */
class RecordAuthenticationActivity
{
    public function __construct(private Request $request) {}

    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('access')
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->event('login')
            ->withProperties($this->requestContext())
            ->log('Signed in');
    }

    public function handleFailed(Failed $event): void
    {
        activity('access')
            ->event('login_failed')
            ->withProperties([
                ...$this->requestContext(),
                'email' => Str::limit((string) ($event->credentials['email'] ?? ''), 190, ''),
            ])
            ->log('Failed sign-in attempt');
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        activity('access')
            ->performedOn($event->user)
            ->causedBy($event->user)
            ->event('logout')
            ->withProperties($this->requestContext())
            ->log('Signed out');
    }

    /**
     * @return array{ip: string|null, user_agent: string}
     */
    private function requestContext(): array
    {
        return [
            'ip' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 255, ''),
        ];
    }
}
