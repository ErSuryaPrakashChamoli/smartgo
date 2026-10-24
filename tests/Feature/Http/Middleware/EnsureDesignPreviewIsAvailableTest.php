<?php

use function Pest\Laravel\get;

dataset('design preview pages', ['/design', '/design/foundations', '/design/components', '/design/home']);

it('serves design previews outside production and asks search engines not to index them', function (string $path) {
    get($path)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false);
})->with('design preview pages');

it('returns 404 for design previews in production', function (string $path) {
    app()->detectEnvironment(fn (): string => 'production');

    get($path)->assertNotFound();
})->with('design preview pages');
