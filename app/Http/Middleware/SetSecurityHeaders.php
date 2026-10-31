<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers (SECURITY_MODEL §5). A full Content Security Policy
 * is introduced once the public site's scripts are known (Phase 14 hardening).
 *
 * Search engines are told not to index anything outside production (DEPLOYMENT_RUNBOOK: staging is
 * "noindex"), nor the back office anywhere, by a response header rather than robots.txt, so the back
 * office's address is never advertised (D-30, FR-SEO-3). A page's own stricter setting is kept.
 */
class SetSecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), payment=(), usb=()',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ((! app()->isProduction() || $this->isBackoffice($request)) && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function isBackoffice(Request $request): bool
    {
        $path = trim((string) config('backoffice.path'), '/');

        return $request->is($path, $path.'/*');
    }
}
