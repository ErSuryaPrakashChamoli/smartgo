<?php

use App\Models\City;
use App\Models\Product;
use App\Models\Property;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    Property::query()->each(fn (Property $property) => publishPropertyProfileForTest($property));
});

it('lists every workspace type on the hub and links only those with a published offering', function () {
    offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room');

    get('/workspaces')->assertOk()
        ->assertSeeInOrder(['Private offices and cabins', 'Dedicated desks', 'Hot desks', 'Meeting rooms', 'Conference and training rooms', 'Virtual office', 'Managed offices'])
        ->assertSee('href="/workspaces/meeting-rooms"', false)
        ->assertDontSee('href="/workspaces/hot-desks"', false)
        ->assertSee('Ask SmartGro where this is available.');
});

it('shows a category page only while a published offering exists, with only the published ones', function () {
    get('/workspaces/meeting-rooms')->assertNotFound();

    offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room', content: ['included' => ['Screen']]);
    offerForTest(Property::query()->where('code', 'A-71')->sole(), 'meeting_room', published: false);

    get('/workspaces/meeting-rooms')->assertOk()
        ->assertSee('Meeting rooms</h1>', false)
        ->assertSee('SmartGro A-70')
        ->assertDontSee('SmartGro A-71')
        ->assertSee('Screen')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/workspaces/meeting-rooms">', false)
        ->assertDontSee('₹');
});

it('narrows a category to one city, and refuses a city or category without a published offering', function () {
    offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room');

    get('/workspaces/meeting-rooms/noida')->assertOk()->assertSee('Meeting rooms in Noida');
    get('/workspaces/meeting-rooms/gurugram')->assertNotFound();
    get('/workspaces/hot-desks/noida')->assertNotFound();
    get('/workspaces/unknown')->assertNotFound();
});

it('hides a category that is switched off, even with a published offering', function () {
    offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room');
    Product::query()->where('key', 'meeting_room')->update(['is_active' => false]);

    get('/workspaces/meeting-rooms')->assertNotFound();
    get('/locations/noida/a-70/meeting-rooms')->assertNotFound();
    get('/workspaces')->assertDontSee('Meeting rooms');
});

it('lists a category in a city that only becomes visible later', function () {
    $gurugram = City::query()->where('slug', 'gurugram')->sole();
    $property = publishPropertyProfileForTest(Property::factory()->open()->withPrimaryBuilding()->create(['city_id' => $gurugram->id]));
    offerForTest($property, 'hot_desk');

    get('/workspaces/hot-desks/gurugram')->assertOk();
});
