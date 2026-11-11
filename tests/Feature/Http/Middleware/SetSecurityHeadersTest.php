<?php

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('adds the baseline security headers to every response', function () {
    get('/up')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=()');
});

it('keeps a stricter header that a route sets itself', function () {
    Route::get('/_test/no-framing', fn () => response('ok')->header('X-Frame-Options', 'DENY'));

    get('/_test/no-framing')->assertHeader('X-Frame-Options', 'DENY');
});

it('sends HSTS over HTTPS in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    get('https://localhost/up')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('does not send HSTS outside production', function () {
    get('https://localhost/up')->assertHeaderMissing('Strict-Transport-Security');
});

/**
 * The report-only policy's directives, each with its sources.
 *
 * @return array<string, string>
 */
function reportedDirectives(TestResponse $response): array
{
    return collect(explode('; ', (string) $response->headers->get('Content-Security-Policy-Report-Only')))
        ->mapWithKeys(function (string $directive): array {
            [$name, $sources] = explode(' ', $directive, 2) + [1 => ''];

            return [$name => $sources];
        })
        ->all();
}

/**
 * @return list<string>
 */
function noncesIn(TestResponse $response): array
{
    preg_match_all('/\snonce="([^"]*)"/', (string) $response->getContent(), $matches);

    return $matches[1];
}

it('sends no content security policy and no nonces unless report mode is chosen', function (string $mode) {
    config(['security.csp_mode' => $mode]);
    Page::factory()->published()->create(['slug' => 'about']);

    foreach (['/up', '/about'] as $path) {
        $response = get($path)
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');

        expect(noncesIn($response))->toBe([]);
    }
})->with(['off', 'enforce', 'Report', 'true', '']);

it('reports, without enforcing, what the content security policy would block, beside the other headers', function () {
    config(['security.csp_mode' => 'report']);

    get('/up')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('allows the public website no inline script, no string evaluation and no inline style without the nonce', function () {
    config(['security.csp_mode' => 'report']);

    $directives = reportedDirectives(get('/up'));

    expect($directives['script-src'])->toBe("'self' 'report-sample'")
        ->and($directives['img-src'])->toBe("'self' data:")
        ->and($directives['style-src'])->toMatch("/^'self' 'nonce-[^']+' 'report-sample'$/")
        ->and($directives)->not->toHaveKeys(['style-src-attr', 'worker-src'])
        ->and($directives['object-src'])->toBe("'none'")
        ->and($directives['frame-src'])->toBe('https://www.google.com/maps https://www.google.com/maps/')
        ->and($directives['frame-ancestors'])->toBe("'self'");
});

it('gives every public page\'s inline tags the nonce of its own response, a new one each time', function () {
    config(['security.csp_mode' => 'report']);
    Page::factory()->published()->create(['slug' => 'about']);

    $seen = [];
    foreach (['/', '/about', '/locations', '/workspaces', '/no-such-page'] as $path) {
        $response = get($path);
        preg_match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy-Report-Only'), $policyNonce);

        expect($response->getContent())->toMatch('/<style nonce="'.preg_quote($policyNonce[1], '/').'">\s*@font-face/')
            ->and(array_unique(noncesIn($response)))->toBe([$policyNonce[1]]);
        $seen[] = $policyNonce[1];
    }

    expect(array_unique($seen))->toHaveCount(5);
});

it('allows the back office only what its screens need, and never framing', function () {
    config(['security.csp_mode' => 'report']);

    $response = get('/'.config('backoffice.path').'/login');
    $directives = reportedDirectives($response);
    preg_match("/'nonce-([^']+)'/", $directives['script-src'], $policyNonce);

    expect($directives['script-src'])->toBe("'self' 'nonce-{$policyNonce[1]}' 'unsafe-eval' 'wasm-unsafe-eval' 'report-sample'")
        ->and($directives['worker-src'])->toBe("'self' blob:")
        ->and($directives['style-src'])->toBe("'self' 'nonce-{$policyNonce[1]}' 'report-sample'")
        ->and($directives['style-src-attr'])->toBe("'unsafe-inline'")
        ->and($directives['img-src'])->toBe("'self' data: blob:")
        ->and($directives['frame-ancestors'])->toBe("'none'")
        ->and(noncesIn($response))->not->toBeEmpty()
        ->and(array_unique(noncesIn($response)))->toBe([$policyNonce[1]]);
});

it('leaves string evaluation out of the back office\'s policy when Livewire ships its CSP-safe Alpine', function () {
    config(['security.csp_mode' => 'report', 'livewire.csp_safe' => true]);

    $directives = reportedDirectives(get('/'.config('backoffice.path').'/login'));

    expect($directives['script-src'])->toMatch("/^'self' 'nonce-[^']+' 'wasm-unsafe-eval' 'report-sample'$/");
});

it('keeps a content security policy that a route sets itself', function () {
    config(['security.csp_mode' => 'report']);
    Route::get('/_test/own-policy', fn () => response('ok')->header('Content-Security-Policy-Report-Only', "default-src 'none'"));

    get('/_test/own-policy')->assertHeader('Content-Security-Policy-Report-Only', "default-src 'none'");
});
