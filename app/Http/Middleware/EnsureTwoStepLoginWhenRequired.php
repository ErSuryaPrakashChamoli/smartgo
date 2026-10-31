<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * Filament's two-step login check, applied only while two-step login is required (OD-124). The
 * decision is read on every request, so turning the switch on or off needs no route cache rebuild.
 * Staff who set up two-step login themselves are always asked for it when they sign in.
 */
class EnsureTwoStepLoginWhenRequired extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! SecuritySetting::requiresTwoStepLogin()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
