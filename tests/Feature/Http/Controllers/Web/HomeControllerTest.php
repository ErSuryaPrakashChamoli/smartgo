<?php

use App\Models\City;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/**
 * Publishes the four reference properties' profiles, as approving and publishing them leaves them.
 */
function publishReferenceProperties(): void
{
    Property::query()->each(fn (Property $property) => publishPropertyProfileForTest($property));
}

it('shows the SmartGro home page with the confirmed categories and the properties on the website', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();

    get('/')
        ->assertOk()
        ->assertSee('Work smart. Grow together.')
        ->assertSee('Flexible workspaces in Noida')
        ->assertSeeInOrder(['Private offices and cabins', 'Dedicated desks', 'Hot desks', 'Meeting rooms', 'Conference and training rooms', 'Virtual office', 'Managed offices'])
        ->assertSee('SmartGro A-70')->assertSee('SmartGro A-71')->assertSee('SmartGro A-23')->assertSee('SmartGro H-105')
        ->assertSee('A-70, Sector 2, Noida')
        ->assertSee('href="/locations/noida/h-105"', false)
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', false)
        ->assertDontSee('Laravel');
});

it('never shows prices, availability or a property that is not open', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();
    publishPropertyProfileForTest(Property::factory()->create(['name' => 'Planned P-9', 'code' => 'P-9', 'city_id' => City::query()->where('slug', 'noida')->value('id')]));

    get('/')->assertOk()->assertDontSee('Planned P-9')->assertDontSee('₹')->assertDontSee('per month')->assertDontSee('Available now');
});

it('keeps a property off the website until its profile is published', function () {
    seed(ReferenceDataSeeder::class);

    get('/')->assertOk()->assertDontSee('SmartGro A-70')->assertDontSee('id="locations"', false);

    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());

    get('/')->assertSee('SmartGro A-70')->assertDontSee('SmartGro A-71');
});

it('lists a property under a category only once its offering there is published', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();
    $property = Property::query()->where('code', 'A-23')->sole();

    get('/')->assertSee('Ask us which locations offer this.')->assertDontSee('Available at');

    offerForTest($property, 'meeting_room', published: false);

    get('/')->assertDontSee('Available at');

    offerForTest($property, 'meeting_room');

    get('/')->assertSeeInOrder(['Meeting rooms', 'Available at', 'SmartGro A-23'])->assertSee('href="/locations/noida/a-23/meeting-rooms"', false)->assertSee('href="/workspaces/meeting-rooms"', false);
});

it('hides inactive categories and their section', function () {
    seed(ReferenceDataSeeder::class);
    Product::query()->where('key', 'virtual_office')->update(['is_active' => false]);

    get('/')->assertOk()->assertDontSee('id="virtual-office"', false)->assertDontSee('A business address for your company');
});

it('shows published contact details, and says so plainly while there are none', function () {
    seed(ReferenceDataSeeder::class);

    get('/')->assertSee('Contact details are on their way')->assertDontSee('tel:');

    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['email' => 'hello@example.com'], 'published_revision_id' => Revision::factory()->create()->id])->save();

    get('/')->assertSee('href="mailto:hello@example.com"', false)->assertDontSee('Contact details are on their way');
});

it('works before any property or category exists', function () {
    get('/')->assertOk()->assertSee('Flexible workspaces')->assertDontSee('id="locations"', false)->assertDontSee('id="workspaces"', false);
});
