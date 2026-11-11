<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Models\City;
use App\Models\Faq;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

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

/**
 * A photo uploaded through the real action, showing the property with the given code.
 *
 * @param  array<string, mixed>  $overrides
 */
function homePagePhoto(User $user, string $code, MediaOrigin $origin = MediaOrigin::ClientPhotography, array $overrides = []): MediaAsset
{
    return app(CreateMediaAsset::class)->handle($user, UploadedFile::fake()->image($code.'.jpg', 1600, 1000), [
        'title' => 'Photo of '.$code, 'origin' => $origin->value, 'alt_text' => 'Reception at '.$code,
        'not_from_old_website' => true, 'licence_holder' => 'SmartGro', 'licence_reference' => 'L-'.$code,
        'depicts_property_id' => Property::query()->where('code', $code)->value('id'), ...$overrides,
    ]);
}

it('shows the SmartGro home page with the confirmed categories and the properties on the website', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();

    get('/')
        ->assertOk()
        ->assertSeeInOrder(['A better space', 'to do your', 'best work.'])
        ->assertSee('Flexible workspaces in Noida')
        ->assertSeeInOrder(['Private offices and cabins', 'Dedicated desks', 'Hot desks', 'Meeting rooms', 'Conference and training rooms', 'Virtual office', 'Managed offices'])
        ->assertSee('SmartGro A-70')->assertSee('SmartGro A-71')->assertSee('SmartGro A-23')->assertSee('SmartGro H-105')
        ->assertSee('A-70, Sector 2, Noida')
        ->assertSee('href="/locations/noida/h-105"', false)
        ->assertSee('data-count-to="4">4</span> locations in Noida', false)
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/">', false)
        ->assertDontSee('Laravel');
});

it('offers the active workspace types and the listed locations in the workspace finder', function () {
    seed(ReferenceDataSeeder::class);
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $a70 = Property::query()->where('code', 'A-70')->sole();

    get('/')
        ->assertSee('<form id="find" method="get" action="/find-a-workspace"', false)
        ->assertSee('<select id="finder-workspace" name="workspace"', false)
        ->assertSee('<option value="">Any workspace type</option>', false)
        ->assertSee('<option value="meeting-rooms">Meeting rooms</option>', false)
        ->assertSee('<option value="'.$a70->id.'">SmartGro A-70 · Sector 2, Noida</option>', false)
        ->assertDontSee('SmartGro A-71 · ')
        ->assertDontSee('name="team_size"', false)->assertDontSee('name="move_in"', false);
});

it('never shows prices, availability or a property that is not open', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();
    publishPropertyProfileForTest(Property::factory()->create(['name' => 'Planned P-9', 'code' => 'P-9', 'city_id' => City::query()->where('slug', 'noida')->value('id')]));

    get('/')->assertOk()->assertDontSee('Planned P-9')->assertDontSee('₹')->assertDontSee('per month')->assertDontSee('Available now');
});

it('keeps a property off the website until its profile is published', function () {
    seed(ReferenceDataSeeder::class);

    get('/')->assertOk()->assertDontSee('SmartGro A-70')->assertDontSee('id="locations"', false)->assertDontSee('Our locations');

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

    get('/')->assertSeeInOrder(['id="meeting-rooms"', 'Meeting rooms', 'Available at', 'SmartGro A-23'], false)->assertSee('href="/locations/noida/a-23/meeting-rooms"', false)->assertSee('href="/workspaces/meeting-rooms"', false);
});

it('hides inactive categories and their section', function () {
    seed(ReferenceDataSeeder::class);
    Product::query()->where('key', 'virtual_office')->update(['is_active' => false]);

    get('/')->assertOk()->assertDontSee('id="virtual-office"', false)->assertDontSee('A business address for your company')->assertDontSee('value="virtual-office"', false);
});

it('shows only real, published photography of each location or labelled sample stock, never another location\'s photo or one past its licence (BR-MED-2, BR-MED-3, OD-176)', function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $property = fn (string $code): Property => Property::query()->where('code', $code)->sole();
    publishReferenceProperties();

    get('/')->assertSee('AI-generated image, not a photo of a SmartGro location')->assertSee('/images/home/coworking-interior-1024.webp', false);

    $a70 = homePagePhoto($marketing, 'A-70');
    $otherLocation = homePagePhoto($marketing, 'H-105');
    $stock = homePagePhoto($marketing, 'H-105', MediaOrigin::LicensedStock);
    $expired = homePagePhoto($marketing, 'A-23', MediaOrigin::Commissioned);
    $expired->forceFill(['licence_expires_at' => now()->subDay()])->save();
    $meetingRoom = homePagePhoto($marketing, 'A-23', MediaOrigin::Commissioned);
    publishPropertyProfileForTest($property('A-23'), ['hero_asset_id' => $expired->id]);
    publishPropertyProfileForTest($property('A-70'), ['hero_asset_id' => $a70->id]);
    publishPropertyProfileForTest($property('A-71'), ['hero_asset_id' => $otherLocation->id]);
    publishPropertyProfileForTest($property('H-105'), ['hero_asset_id' => $stock->id]);
    offerForTest($property('A-23'), 'meeting_room', content: ['hero_asset_id' => $meetingRoom->id]);

    $html = get('/')->assertOk()->getContent();

    expect($html)
        ->toContain($a70->renditionPath('w960'))->toContain('SmartGro A-70, Sector 2, Noida')->toContain('alt="Reception at A-70"')
        ->toContain($meetingRoom->renditionPath('w960'))->toContain('Meeting rooms at SmartGro A-23')
        ->not->toContain($otherLocation->renditionPath('w960'))
        ->toContain($stock->renditionPath('w960'))->toContain('Stock photo, not a SmartGro location')->not->toContain('SmartGro H-105, Sector 62')
        ->not->toContain($expired->renditionPath('w960'))
        ->not->toContain('AI-generated image, not a photo of a SmartGro location')->not->toContain('/images/home/coworking-interior');
    get($a70->renditionPath('w960'))->assertOk();
});

it('links to the enquiry and tour forms only while visitors can use them, and never calls a request a booking', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();

    get('/')->assertOk()->assertDontSee('href="/enquire', false)->assertDontSee('href="/book-a-tour"', false)->assertDontSee('not a booking');

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);

    get('/')->assertSee('href="/enquire"', false)->assertSee('href="/enquire?workspace=virtual-office"', false)
        ->assertDontSee('href="/book-a-tour"', false)->assertDontSee('Tours on request')
        ->assertSee('An enquiry or a tour request is not a booking. SmartGro confirms the details with you.');

    TourAvailabilityRule::factory()->create(['property_id' => Property::query()->where('code', 'A-70')->value('id')]);

    get('/')->assertSee('href="/book-a-tour"', false)->assertSee('Tours on request')->assertDontSee('Book now');
});

it('keeps working without the tour tables, as on the owner\'s server (OD-120)', function () {
    seed(ReferenceDataSeeder::class);
    publishReferenceProperties();
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, TourRequest::TABLES, true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (preg_match('/`(tour_availability_rules|property_closures|tour_requests)`/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });

    get('/')->assertOk()->assertSee('href="/enquire"', false)->assertDontSee('href="/book-a-tour"', false)->assertSee('Ask SmartGro to arrange a visit.');

    expect($queries)->toBe([]);
});

it('shows published contact details, and says so plainly while there are none', function () {
    seed(ReferenceDataSeeder::class);

    get('/')->assertSee('Contact details are on their way')->assertDontSee('tel:');

    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill(['values' => ['email' => 'hello@example.com'], 'published_revision_id' => Revision::factory()->create()->id])->save();

    get('/')->assertSee('href="mailto:hello@example.com"', false)->assertDontSee('Contact details are on their way');
});

it('works before any property or category exists', function () {
    get('/')->assertOk()->assertSee('Flexible workspaces')->assertDontSee('id="locations"', false)->assertDontSee('id="workspaces"', false)->assertDontSee('id="find"', false)->assertDontSee('href="#find"', false);
});

it('shows the customer segments, the newest articles and the first questions, linking a segment only to its published page (OD-143)', function () {
    seed(ReferenceDataSeeder::class);
    Page::factory()->published()->create(['title' => 'Startups', 'slug' => 'solutions/startups-and-small-businesses']);
    Page::factory()->create(['title' => 'SMEs', 'slug' => 'solutions/smes-and-growing-companies']);
    $sample = PostTopic::factory()->create(['slug' => PostTopic::SAMPLE_SLUG]);
    Post::factory()->published()->create(['title' => 'Oldest article', 'published_at' => now()->subWeeks(3)]);
    Post::factory()->published()->create(['title' => 'Older article', 'published_at' => now()->subWeeks(2)]);
    Post::factory()->published()->create(['title' => 'Example article', 'topic_ids' => [$sample->id], 'published_at' => now()->subWeek()]);
    Post::factory()->published()->create(['title' => 'Newest article', 'published_at' => now()]);
    Post::factory()->create(['title' => 'Draft article', 'slug' => 'draft-article']);
    foreach (range(1, 5) as $number) {
        Faq::factory()->published()->create(['question' => "Question {$number}?", 'sort_order' => $number]);
    }

    get('/')->assertOk()
        ->assertSeeInOrder(['Who we work with', 'Startups and small businesses', 'SMEs and growing companies', 'Latest insights', 'Newest article', 'Example article', 'Sample content', 'Older article', 'Questions', 'Question 1?', 'Question 4?'])
        ->assertSee('href="/solutions/startups-and-small-businesses"', false)
        ->assertDontSee('href="/solutions/smes-and-growing-companies"', false)
        ->assertDontSee('Oldest article')->assertDontSee('Draft article')->assertDontSee('Question 5?')
        ->assertSee('href="/insights"', false)->assertSee('href="/faqs"', false);
});

it('leaves out the articles and questions sections while nothing is published', function () {
    Post::factory()->create(['title' => 'Draft article']);
    Faq::factory()->create(['question' => 'Draft question?']);

    get('/')->assertOk()->assertDontSee('Latest insights')->assertDontSee('id="questions-heading"', false)->assertDontSee('Who we work with');
});
