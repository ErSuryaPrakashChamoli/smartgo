<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\City;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    Property::query()->each(fn (Property $property) => publishPropertyProfileForTest($property));
});

it('lists the cities with properties on the website and hides the others', function () {
    get('/locations')
        ->assertOk()
        ->assertSee('Noida')
        ->assertSeeInOrder(['SmartGro A-23', 'SmartGro A-70', 'SmartGro A-71', 'SmartGro H-105'])
        ->assertDontSee('Gurugram')
        ->assertDontSee('Faridabad');
});

it('shows a city switched visible before it opens as coming soon', function () {
    City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);

    get('/locations')->assertSee('Gurugram')->assertSee('Coming soon');
    get('/locations/gurugram')->assertOk()
        ->assertSee('There is no SmartGro workspace in Gurugram yet.')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('Registering interest opens soon')
        ->assertDontSee('SmartGro A-70');
});

it('redirects an old city name and refuses hidden cities', function () {
    City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);

    get('/locations/gurgaon')->assertMovedPermanently()->assertRedirect('/locations/gurugram');
    get('/locations/delhi')->assertNotFound();
    get('/locations/atlantis')->assertNotFound();
});

it('shows a property with its address, profile and published offerings only', function () {
    $property = Property::query()->where('code', 'A-70')->sole();
    offerForTest($property, 'hot_desk');
    offerForTest($property, 'meeting_room', published: false);

    get('/locations/noida/a-70')
        ->assertOk()
        ->assertSee('SmartGro A-70</h1>', false)
        ->assertSee('Summary of SmartGro A-70.')
        ->assertSee('A-70, Sector 2, Noida')
        ->assertSee('Hot desks')
        ->assertSee('href="/locations/noida/a-70/hot-desks"', false)
        ->assertDontSee('Meeting rooms')
        ->assertSee('Confirmed on request')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/locations/noida/a-70">', false);

    get('/locations/noida/a-71')->assertOk()->assertSee('Ask SmartGro which workspaces are available at this location.');
});

it('shows no 3D model on a location page, even with one uploaded, until a scene is published (OD-118 d)', function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    $model = uploadTwinModelForTest(User::factory()->withRole(StaffRole::MarketingManager)->create(), Property::query()->where('code', 'A-70')->sole());

    get('/locations/noida/a-70')
        ->assertOk()
        ->assertSee('SmartGro A-70</h1>', false)
        ->assertDontSee(route(TwinModelFileController::ROUTE, ['twinModel' => $model]), false)
        ->assertDontSee($model->file()->file_name, false)
        ->assertDontSee('twin-viewer', false)
        ->assertDontSee('data-twin', false);
});

it('refuses a property that is closed or temporarily closed, unpublished or taken down, or asked for under the wrong city', function () {
    Property::query()->where('code', 'A-71')->update(['operational_status' => PropertyStatus::TemporarilyClosed]);
    Property::query()->where('code', 'A-23')->sole()->profile->forceFill(['status' => 'unpublished'])->save();
    Property::query()->where('code', 'H-105')->sole()->profile->forceFill(['status' => 'draft'])->save();

    get('/locations/noida/a-71')->assertNotFound();
    get('/locations/noida/a-23')->assertNotFound();
    get('/locations/noida/h-105')->assertNotFound();
    get('/locations/gurugram/a-70')->assertNotFound();
});

it('shows a planned property only once its opening date is known, as opening soon', function () {
    $property = Property::query()->where('code', 'A-71')->sole();
    $property->forceFill(['operational_status' => PropertyStatus::PreOpening])->save();

    get('/locations/noida/a-71')->assertNotFound();

    $property->forceFill(['opens_on' => '2027-03-01'])->save();

    get('/locations/noida/a-71')->assertOk()->assertSee('Opening March 2027');
});

it('shows a published offering on its own page, and nothing for one that is not published', function () {
    $property = Property::query()->where('code', 'A-70')->sole();
    offerForTest($property, 'private_office', content: ['headline' => 'Lockable cabins', 'included' => ['Wi-Fi', 'Reception'], 'not_included' => ['Parking']]);
    offerForTest($property, 'virtual_office', published: false);

    get('/locations/noida/a-70/private-cabins')
        ->assertOk()
        ->assertSee('Private offices and cabins at SmartGro A-70</h1>', false)
        ->assertSee('Lockable cabins')
        ->assertSeeInOrder(['Included', 'Wi-Fi', 'Reception', 'Not included', 'Parking'])
        ->assertSee('Availability on request')
        ->assertSee('Price on request')
        ->assertSee('href="'.e(route('enquiries.create', ['property' => $property->id, 'workspace' => 'private-cabins'])).'"', false)
        ->assertDontSee('Virtual office');

    get('/locations/noida/a-70/virtual-office')->assertNotFound();
    get('/locations/noida/a-70/hot-desks')->assertNotFound();
    get('/locations/noida/a-71/private-cabins')->assertNotFound();
});

it('shows a sector with its properties on the website, linked from the city and property pages', function () {
    get('/locations/noida/area/sector-2')
        ->assertOk()
        ->assertSee('Workspaces in Sector 2, Noida</h1>', false)
        ->assertSee('2 SmartGro locations in Sector 2.')
        ->assertSeeInOrder(['SmartGro A-70', 'SmartGro A-71'])
        ->assertDontSee('SmartGro A-23')
        ->assertSee('href="/locations/noida/area/sector-3"', false)
        ->assertSee('href="/locations/noida/area/sector-62"', false)
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/locations/noida/area/sector-2">', false);

    get('/locations/noida')->assertSeeInOrder(['href="/locations/noida/area/sector-2"', 'href="/locations/noida/area/sector-3"', 'href="/locations/noida/area/sector-62"'], false);
    get('/locations/noida/a-70')->assertSee('href="/locations/noida/area/sector-2"', false);
});

it('refuses a sector without a property on the website, an unknown one, or one under a hidden city', function () {
    Property::query()->where('code', 'A-70')->update(['operational_status' => PropertyStatus::TemporarilyClosed]);
    Property::query()->where('code', 'A-71')->sole()->profile->forceFill(['status' => 'draft'])->save();

    get('/locations/noida/area/sector-2')->assertNotFound();
    get('/locations/noida/area/sector-3')->assertOk()->assertSee('1 SmartGro location in Sector 3.')->assertDontSee('href="/locations/noida/area/sector-2"', false);
    get('/locations/noida')->assertDontSee('href="/locations/noida/area/sector-2"', false);
    get('/locations/noida/area/sector-99')->assertNotFound();
    get('/locations/noida/area')->assertNotFound();
    get('/locations/gurugram/area/sector-3')->assertNotFound();
});

it('describes a property to search engines with its address, and its coordinates once recorded (FR-DISC-7)', function () {
    $building = Property::query()->where('code', 'A-70')->sole()->primaryBuilding;

    get('/locations/noida/a-70')
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('"@type":"LocalBusiness","name":"SmartGro A-70"', false)
        ->assertSee('"addressLocality":"Noida"', false)
        ->assertDontSee('GeoCoordinates');

    $building->forceFill(['latitude' => 28.585, 'longitude' => 77.311, 'address_line_1' => 'A-70 </script><script>alert(1)</script>'])->save();

    get('/locations/noida/a-70')
        ->assertSee('"geo":{"@type":"GeoCoordinates","latitude":28.585,"longitude":77.311}', false)
        ->assertDontSee('</script><script>alert(1)', false);
});

it('narrows a city to the locations offering one workspace type, with plain links (FR-DISC-2)', function () {
    get('/locations/noida')->assertDontSee('All workspace types');

    offerForTest(Property::query()->where('code', 'A-70')->sole(), 'hot_desk');
    offerForTest(Property::query()->where('code', 'H-105')->sole(), 'hot_desk');
    offerForTest(Property::query()->where('code', 'A-23')->sole(), 'meeting_room');

    get('/locations/noida')->assertSeeInOrder(['All workspace types', 'Hot desks', 'Meeting rooms', 'SmartGro A-71']);

    get('/locations/noida?workspace=hot-desks')
        ->assertOk()
        ->assertSee('?workspace=hot-desks" aria-current="page"', false)
        ->assertSee('2 locations in Noida offer hot desks.')
        ->assertSee('href="/workspaces/hot-desks/noida"', false)
        ->assertSeeInOrder(['SmartGro A-70', 'SmartGro H-105'])
        ->assertDontSee('SmartGro A-23')
        ->assertDontSee('SmartGro A-71')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/locations/noida">', false);

    get('/locations/noida?workspace=virtual-office')->assertOk()->assertSee('SmartGro A-71')->assertSee('href="/locations/noida" aria-current="page"', false);
    get('/locations/noida?workspace=%3Cscript%3E')->assertOk()->assertDontSee('<script>', false);
});

it('compares two locations side by side from their published records, at an address that can be shared (FR-DISC-4)', function () {
    $a70 = Property::query()->where('code', 'A-70')->sole();
    offerForTest($a70, 'hot_desk');
    publishPropertyProfileForTest(Property::query()->where('code', 'H-105')->sole(), ['nearest_metro' => 'Sector 62 station', 'parking' => 'Visitor parking']);

    get('/locations/compare?locations[]=noida/a-70&locations[]=noida/h-105')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSeeInOrder([
            'SmartGro A-70', 'SmartGro H-105',
            'Area', 'Sector 2, Noida', 'Sector 62, Noida',
            'Workspace types', 'Hot desks', 'Not listed',
            'Nearest metro station', 'Not listed', 'Sector 62 station',
            'Parking', 'Not listed', 'Visitor parking',
            'Prices and availability', 'Confirmed on request', 'Confirmed on request',
        ])
        ->assertSee('href="'.e(route('enquiries.create', ['property' => $a70->id])).'"', false)
        ->assertSee('value="noida/a-70" checked="checked"', false)
        ->assertDontSee('Buses')
        ->assertDontSee('>Remove<', false);
});

it('lets a visitor drop one of three locations, refuses more than three, and leaves out those no longer on the website', function () {
    get('/locations/compare?locations[]=noida/a-70&locations[]=noida/a-71&locations[]=noida/h-105')
        ->assertOk()
        ->assertSee('href="'.e(route('locations.compare', ['locations' => ['noida/a-71', 'noida/h-105']])).'"', false);

    get('/locations/compare?locations[]=noida/a-70&locations[]=noida/a-71&locations[]=noida/a-23&locations[]=noida/h-105')
        ->assertOk()
        ->assertSee('Choose up to 3 locations to compare.')
        ->assertDontSee('Prices and availability');

    Property::query()->where('code', 'A-71')->sole()->profile->forceFill(['status' => 'draft'])->save();

    get('/locations/compare?locations[]=noida/a-70&locations[]=noida/a-71&locations[]=noida/h-105')
        ->assertOk()
        ->assertSee('One location in this link is no longer on the website, so it is left out.')
        ->assertSee('Prices and availability')
        ->assertDontSee('SmartGro A-71');

    get('/locations/compare?locations[]=noida/a-70')->assertOk()->assertSee('Choose at least two locations to compare.')->assertDontSee('Prices and availability');
    get('/locations/compare?locations=noida/a-70')->assertOk()->assertDontSee('Choose at least two locations to compare.');
    get('/locations/compare')->assertOk()->assertSee('Choose locations')->assertSee('name="locations[]"', false);
    get('/locations/compare?locations[0][]=noida/a-70&locations[1]=noida/h-105')->assertOk()->assertSee('Choose at least two locations to compare.')->assertDontSee('Prices and availability');
});

it('puts a location\'s call, WhatsApp and enquiry links in the phone action bar (FR-DISC-5, FR-DISC-6)', function () {
    $property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole(), ['phone' => '+91 120 400 0000', 'whatsapp' => '+911204000000']);
    offerForTest($property, 'hot_desk');

    foreach (['/locations/noida/a-70' => ['property' => $property->id], '/locations/noida/a-70/hot-desks' => ['property' => $property->id, 'workspace' => 'hot-desks']] as $path => $context) {
        $bar = str(get($path)->assertOk()->getContent())->afterLast('fixed inset-x-0 bottom-0')->squish()->toString();

        expect($bar)->toMatch('#href="tel:\+911204000000".*Call </a>.*href="https://wa.me/911204000000" rel="noopener".*WhatsApp </a>.*href="'.preg_quote(e(route('enquiries.create', $context)), '#').'"[^>]*> Enquire#');
    }
});

it('leaves out the getting-here section and the phone bar\'s call links while nothing is published for them', function () {
    $property = Property::query()->where('code', 'A-70')->sole();

    $html = get('/locations/noida/a-70')->assertOk()->getContent();
    $bar = str($html)->afterLast('fixed inset-x-0 bottom-0')->toString();

    expect($html)->not->toContain('id="getting-here"')
        ->and($bar)->not->toContain('tel:')
        ->and($bar)->not->toContain('wa.me')
        ->and($bar)->toContain('href="'.e(route('enquiries.create', ['property' => $property->id])).'"');
});
