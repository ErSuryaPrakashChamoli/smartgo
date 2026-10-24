<?php

use function Pest\Laravel\get;

it('labels every design preview as placeholder content not for publication', function (string $path) {
    get($path)->assertSee('Design preview: layout and draft copy only.');
})->with(['/design', '/design/foundations', '/design/components', '/design/home']);

it('shows only the four confirmed Noida properties, without invented prices or availability', function () {
    get('/design/home')
        ->assertSeeInOrder(['A-70', 'A-71', 'A-23', 'H-105'])
        ->assertSee('Price on request')
        ->assertSee('Availability on request')
        ->assertDontSee('₹');
});

it('shows the seven confirmed workspace categories', function () {
    get('/design/home')->assertSeeInOrder([
        'Private cabins',
        'Dedicated desks',
        'Hot desks',
        'Meeting rooms',
        'Conference and training rooms',
        'Virtual office',
        'Managed offices',
    ]);
});

it('gives every preview one main heading, a skip link, the page language and alt text on every image', function (string $path) {
    $html = get($path)->assertOk()->getContent();

    expect(substr_count($html, '<h1'))->toBe(1)
        ->and($html)->toContain('<html lang="en-IN"')
        ->and($html)->toContain('<a href="#content"')
        ->and($html)->toContain('<main id="content">')
        ->and(preg_match_all('/<img\b/', $html))->toBe(preg_match_all('/<img\b[^>]*\balt="/', $html));
})->with(['/design', '/design/foundations', '/design/components', '/design/home']);

it('links the SmartGro tab and home-screen icons', function () {
    get('/design/home')
        ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'"', escape: false)
        ->assertSee('<link rel="apple-touch-icon" href="'.asset('brand/apple-touch-icon.png').'">', escape: false);
});

it('lists every colour pairing with its contrast result on the foundations page', function () {
    get('/design/foundations')
        ->assertSee('ink on paper')
        ->assertSee('13.14 : 1')
        ->assertDontSee('Fail (');
});
