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

it('lists every colour pairing with its contrast result on the foundations page', function () {
    get('/design/foundations')
        ->assertSee('ink on paper')
        ->assertSee('13.14 : 1')
        ->assertDontSee('Fail (');
});
