<?php

use function Pest\Laravel\get;

dataset('all design previews', [
    '/design', '/design/foundations', '/design/components', '/design/home',
    '/design/locations', '/design/locations/noida', '/design/locations/gurugram', '/design/locations/noida/a-70', '/design/sample-property',
    '/design/workspaces', '/design/workspaces/meeting-rooms', '/design/enterprise',
    '/design/flows/enquiry', '/design/flows/tour', '/design/flows/request-to-book', '/design/flows/online-booking',
]);

it('labels every design preview as placeholder content not for publication', function (string $path) {
    get($path)->assertSee('Design preview: layout and draft copy only.');
})->with('all design previews');

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
})->with('all design previews');

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

it('redirects the old city name Gurgaon permanently to Gurugram', function () {
    get('/design/locations/gurgaon')->assertStatus(301)->assertRedirect('/design/locations/gurugram');
});

it('returns 404 for a hidden city, an unknown property, workspace type or journey', function (string $path) {
    get($path)->assertNotFound();
})->with(['/design/locations/delhi', '/design/locations/noida/b-99', '/design/workspaces/phone-booths', '/design/flows/checkout']);

it('lists every Noida property by default and filters by a valid area only', function () {
    get('/design/locations/noida?area=Sector%202')
        ->assertSee('Properties in Sector 2')
        ->assertSee(route('design.property', ['noida', 'a-71']))
        ->assertDontSee(route('design.property', ['noida', 'h-105']));
    get('/design/locations/noida?area=Sector%2099')->assertSee('All properties');
});

it('shows a coming-soon page for a visible city without a property, with no inventory or prices', function () {
    get('/design/locations/gurugram')
        ->assertSee('There is no SmartGro workspace in Gurugram yet.')
        ->assertSee('Register your interest')
        ->assertDontSee('₹');
});

it('shows a real property with placeholders, never invented data', function (string $property) {
    get('/design/locations/noida/'.$property)
        ->assertSee('Opening status to be confirmed')
        ->assertSee('Workspace details are not listed yet')
        ->assertSee('Price on request')
        ->assertDontSee('₹')
        ->assertDontSee('Available now');
})->with(['a-70', 'a-71', 'a-23', 'h-105']);

it('labels the sample property as illustrative', function () {
    get('/design/sample-property')->assertSee('Sample property: every value on this page is illustrative');
});

it('serves the template for each of the seven workspace types', function (string $slug, string $action) {
    get('/design/workspaces/'.$slug)->assertOk()->assertSee($action)->assertSee('Price on request');
})->with([
    ['private-cabins', 'Request a proposal'],
    ['dedicated-desks', 'Request a proposal'],
    ['hot-desks', 'Request a day pass'],
    ['meeting-rooms', 'Request a booking'],
    ['conference-training-rooms', 'Request a booking'],
    ['virtual-office', 'Enquire'],
    ['managed-offices', 'Plan your office'],
]);

it('never presents a request as a confirmed booking or tour', function () {
    get('/design/flows/request-to-book')->assertSeeInOrder(['Booking requested: not yet confirmed', 'Booking confirmed']);
    get('/design/flows/tour')->assertSeeInOrder(['Tour requested: not yet confirmed', 'Tour confirmed']);
    get('/design/flows/enquiry')->assertSee('This is not a booking');
});

it('marks online booking as unavailable until the payment decisions are made', function () {
    get('/design/flows/online-booking')->assertSee('Not available yet')->assertSee('D-07 and D-08');
});

it('invents no enterprise customers or case studies', function () {
    get('/design/enterprise')->assertSee('Case studies appear once SmartGro publishes verified client stories');
});
