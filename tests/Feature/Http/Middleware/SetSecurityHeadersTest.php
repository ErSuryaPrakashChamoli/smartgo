<?php

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

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
