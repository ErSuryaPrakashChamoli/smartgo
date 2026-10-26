<?php

namespace App\Actions\Access;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records a wrong two-step login code in the audit log (SECURITY_MODEL §7, sign-in failures).
 * The password was correct at this point, so the entry matters; the code entered is never stored.
 */
class RecordFailedTwoStepLogin
{
    public function __construct(private Request $request) {}

    public function handle(User $user): void
    {
        activity('access')
            ->performedOn($user)
            ->event('two_factor_failed')
            ->withProperties([
                'ip' => $this->request->ip(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 255, ''),
            ])
            ->log('Wrong two-step login code');
    }
}
