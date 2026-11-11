<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers (SECURITY_MODEL §5).
 *
 * The Content Security Policy is introduced in stages (P14-16): it is sent only when `security.csp_mode`
 * is "report", as Content-Security-Policy-Report-Only, so browsers report what it would block without
 * blocking anything. Each such response gets its own nonce (Vite's, which Laravel's Vite tags, Filament
 * and Livewire put on their inline tags), set before the page is rendered. The back office may not be
 * framed at all (`frame-ancestors 'none'`). Enforcement is a later, reviewed change (OD-158).
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
     * The public website's policy: this site's own files only, no plugins, no framing by other sites. The one
     * inline style, the font faces `@fonts` writes, carries the nonce; inline scripts and style attributes
     * are not allowed. Analytics, maps or 360° tours would each add a reviewed allow-list entry
     * (SECURITY_MODEL §5): the footer's Google map, loaded only when the visitor asks for it (OD-180), may be
     * framed, from Google's map addresses only (its `/maps?…&output=embed` address answers with a redirect to
     * `/maps/embed`). 'report-sample' puts the
     * start of a blocked inline script or style in the report.
     *
     * @var array<string, string>
     */
    private const PUBLIC_POLICY = [
        'default-src' => "'self'",
        'script-src' => "'self' 'report-sample'",
        'style-src' => "'self' {nonce} 'report-sample'",
        'img-src' => "'self' data:",
        'font-src' => "'self'",
        'connect-src' => "'self'",
        'media-src' => "'self'",
        'frame-src' => 'https://www.google.com/maps https://www.google.com/maps/',
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'self'",
    ];

    /**
     * The back office's policy: the public one, never framed, plus only what the OD-157 discovery showed
     * its screens need. Filament's and Livewire's inline scripts carry the nonce; Alpine evaluates its
     * expressions as strings (`'unsafe-eval'`, only with Livewire's standard build: its `csp_safe` build ships
     * an Alpine that does not, so the policy then leaves it out); the 3D viewer's Meshopt decoder
     * compiles WebAssembly (`'wasm-unsafe-eval'`) and its Draco decoder runs in a worker made from a `blob:`
     * address; Filament sets layout values in style attributes, which cannot carry a nonce; an image chosen
     * for upload is previewed from a `blob:` address (FilePond). Inline style elements must carry the nonce,
     * so the reports show any that do not (OD-158: four in Filament's layout, one from its rich editor).
     *
     * @var array<string, string>
     */
    private const BACKOFFICE_POLICY = [
        'default-src' => "'self'",
        'script-src' => "'self' {nonce} 'unsafe-eval' 'wasm-unsafe-eval' 'report-sample'",
        'worker-src' => "'self' blob:",
        'style-src' => "'self' {nonce} 'report-sample'",
        'style-src-attr' => "'unsafe-inline'",
        'img-src' => "'self' data: blob:",
        'font-src' => "'self'",
        'connect-src' => "'self'",
        'media-src' => "'self'",
        'frame-src' => "'none'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
        'frame-ancestors' => "'none'",
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $reportsContentSecurityPolicy = config('security.csp_mode') === 'report';

        if ($reportsContentSecurityPolicy) {
            Vite::useCspNonce();
        }

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

        if ($reportsContentSecurityPolicy && ! $response->headers->has('Content-Security-Policy-Report-Only')) {
            $response->headers->set('Content-Security-Policy-Report-Only', $this->contentSecurityPolicy($request));
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $policy = $this->isBackoffice($request) ? self::BACKOFFICE_POLICY : self::PUBLIC_POLICY;
        $nonce = "'nonce-".Vite::cspNonce()."'";

        if (config('livewire.csp_safe')) {
            $policy['script-src'] = str_replace(" 'unsafe-eval'", '', $policy['script-src']);
        }

        return collect($policy)->map(fn (string $sources, string $directive): string => $directive.' '.str_replace('{nonce}', $nonce, $sources))->implode('; ');
    }

    private function isBackoffice(Request $request): bool
    {
        $path = trim((string) config('backoffice.path'), '/');

        return $request->is($path, $path.'/*');
    }
}
