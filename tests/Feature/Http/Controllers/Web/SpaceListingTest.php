<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Properties\PropertyStatus;
use App\Filament\Clusters\Website\Resources\SpaceListings\SpaceListingResource;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Property;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Website\SearchMetadata;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->staff = [
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::SuperAdmin)->create(),
    ];
    $this->property = fn (string $code): Property => Property::query()->where('code', $code)->sole();
    $this->type = fn (string $key): SpaceType => SpaceType::query()->where('key', $key)->sole();
    $this->listing = fn (string $code, string $type, string $name, array $attributes = []): SpaceListing => SpaceListing::factory()->published()->create([
        'property_id' => ($this->property)($code)->id, 'space_type_id' => ($this->type)($type)->id,
        'name' => $name, 'slug' => str($code.' '.$name)->slug()->toString(), ...$attributes,
    ]);
});

/**
 * Publishes a main menu through the workflow: author, approver and publisher are different people.
 *
 * @param  list<array<string, mixed>>  $items
 */
function publishSpacesMenu(array $items): void
{
    [$author, $approver, $publisher] = test()->staff;
    $draft = app(CreateDraft::class)->handle(NavigationMenu::query()->where('handle', 'main')->sole(), $author, ['items' => $items]);

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

/**
 * A main-menu link to a space type's spaces.
 *
 * @return array<string, mixed>
 */
function spaceTypeMenuLink(string $label, string $key): array
{
    return ['label' => $label, 'type' => 'space_type', 'space_type_id' => SpaceType::query()->where('key', $key)->value('id')];
}

/**
 * The page's HTML with runs of whitespace collapsed.
 */
function spacesHtml(string $path): string
{
    return (string) preg_replace('/\s+/', ' ', (string) get($path)->assertOk()->getContent());
}

it('shows a published space with its type, location, details and features, and asks about it once the enquiry form is open', function () {
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M1', [
        'summary' => 'A room for client meetings.', 'details' => "First paragraph.\n\nSecond paragraph.", 'seats' => 6, 'features' => ['Whiteboard', 'Screen'],
    ]);

    get('/spaces/a-70-meeting-room-m1')->assertOk()
        ->assertSeeInOrder(['Meeting room', 'Meeting room M1', 'SmartGro A-70', 'Sector 2, Noida', 'A room for client meetings.', 'First paragraph.', 'Second paragraph.', '6 seats', 'Whiteboard', 'Screen'])
        ->assertDontSee('Ask about this space')
        ->assertSee('href="/#contact"', false)
        ->assertDontSee('noindex');

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    publishPropertyProfileForTest(($this->property)('A-70'));

    $html = spacesHtml('/spaces/a-70-meeting-room-m1');

    expect($html)->toContain('Ask about this space')
        ->toContain('href="/enquire?workspace=meeting-rooms&amp;property='.($this->property)('A-70')->id.'"')
        ->toMatch('#<a href="/locations/noida/a-70"[^>]*>SmartGro A-70</a>#');
});

it('asks about a space without choosing a workspace type when its space type belongs to more than one', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    ($this->listing)('A-23', 'conference_training_room', 'Board room T3');

    expect(spacesHtml('/spaces/a-23-board-room-t3'))->toContain('href="/enquire"')->not->toContain('workspace=');
});

it('has no page for a space that is not published, switched off, at a location that may not be listed, or unknown', function () {
    SpaceListing::factory()->create();
    ($this->listing)('A-70', 'meeting_room', 'Switched off', ['status' => 'unpublished']);
    ($this->listing)('A-71', 'meeting_room', 'Closed location');
    ($this->property)('A-71')->forceFill(['operational_status' => PropertyStatus::Closed])->save();

    get('/spaces/a-70-switched-off')->assertNotFound();
    get('/spaces/a-71-closed-location')->assertNotFound();
    get('/spaces/no-such-space')->assertNotFound();
});

it('says an example space is an example and keeps it out of search engines', function () {
    ($this->listing)('A-70', 'private_cabin', 'Cabin C1', ['is_sample' => true]);

    get('/spaces/a-70-cabin-c1')->assertOk()
        ->assertSee('Sample content.')
        ->assertSee('Example space')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('suggests other shown spaces of the same type, those at the same location first, never itself', function () {
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M1');
    ($this->listing)('A-23', 'meeting_room', 'Meeting room M9');
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M2');
    ($this->listing)('A-70', 'private_cabin', 'Cabin C1');

    $others = str(spacesHtml('/spaces/a-70-meeting-room-m1'))->after('id="others-heading"')->toString();

    expect($others)->toMatch('#Meeting room M2.*SmartGro A-70.*Meeting room M9.*SmartGro A-23#')
        ->not->toContain('Meeting room M1')
        ->not->toContain('Cabin C1');
});

it('opens Spaces as a tab per space type, listing its shown spaces by location, unlike Solutions (OD-177)', function () {
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M2', ['sort_order' => 20]);
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M1', ['sort_order' => 10]);
    ($this->listing)('A-23', 'meeting_room', 'Meeting room M5');
    ($this->listing)('A-70', 'meeting_room', 'Old room', ['status' => 'unpublished']);
    ($this->listing)('A-71', 'private_cabin', 'Cabin C1', ['is_sample' => true]);
    SpaceListing::factory()->create();
    publishSpacesMenu([['label' => 'Spaces', 'type' => 'group', 'children' => [
        ['label' => 'All workspace types', 'type' => 'section', 'section' => 'workspace_types'],
        spaceTypeMenuLink('Meeting rooms', 'meeting_room'),
        spaceTypeMenuLink('Private cabins', 'private_cabin'),
        spaceTypeMenuLink('Office suites', 'office_suite'),
    ]]]);

    $html = spacesHtml('/');
    $desktop = str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();
    $phone = str($html)->after('id="mobile-menu"')->before('</nav>')->toString();

    expect($desktop)
        ->toContain('w-[min(58rem,calc(100vw-2.5rem))]')
        ->toMatch('#data-flyout-tab[^>]*>.*?Meeting rooms.*?>3<.*data-flyout-tab[^>]*>.*?Private cabins.*?>1<#')
        ->not->toContain('Office suites')
        ->toMatch('#id="nav-group-0-0"[^>]*data-flyout-pane.*?SmartGro A-23.*?href="/spaces/a-23-meeting-room-m5".*?SmartGro A-70.*?href="/spaces/a-70-meeting-room-m1".*?href="/spaces/a-70-meeting-room-m2".*?id="nav-group-0-1"#')
        ->toMatch('#id="nav-group-0-1"[^>]*data-flyout-pane.*?SmartGro A-71.*?Sector 2, Noida.*?href="/spaces/a-71-cabin-c1"#')
        ->not->toContain('Old room')
        ->toMatch('#Example spaces: not yet confirmed by SmartGro\..*href="/workspaces"[^>]*> All workspace types#')
        ->and($phone)->toMatch('#<summary[^>]*>.*?Meeting rooms.*?Meeting room M1.*?SmartGro A-70 · Sector 2, Noida#')
        ->and($phone)->toContain('Example spaces: not yet confirmed by SmartGro.');
});

it('accepts a space type only as a link in a main-menu drop-down, and keeps it when published', function () {
    $menu = NavigationMenu::query()->where('handle', 'main')->sole();
    $errors = function (array $items) use ($menu): array {
        try {
            app(CreateDraft::class)->handle($menu, $this->staff[0], ['items' => $items]);
        } catch (ValidationException $exception) {
            return array_keys($exception->errors());
        }

        return [];
    };

    expect($errors([spaceTypeMenuLink('Meeting rooms', 'meeting_room')]))->toContain('items.0.type')
        ->and($errors([['label' => 'Spaces', 'type' => 'group', 'children' => [['label' => 'Rooms', 'type' => 'group', 'children' => [spaceTypeMenuLink('Meeting rooms', 'meeting_room')]]]]]))->toContain('items.0.children.0.children.0.type')
        ->and($errors([['label' => 'Spaces', 'type' => 'group', 'children' => [['label' => 'Meeting rooms', 'type' => 'space_type']]]]))->toBe(['items.0.children.0.space_type_id']);

    publishSpacesMenu([['label' => 'Spaces', 'type' => 'group', 'children' => [spaceTypeMenuLink('Meeting rooms', 'meeting_room')]]]);

    expect($menu->refresh()->toRevisionPayload()['items'][0]['children'][0])->toMatchArray(['label' => 'Meeting rooms', 'type' => 'space_type', 'space_type_id' => ($this->type)('meeting_room')->id])
        ->and(NavigationItem::query()->where('link_type', 'space_type')->sole()->linkable)->toBeInstanceOf(SpaceType::class);
});

it('keeps the website working without the space listing table, as on a database not migrated for it', function () {
    publishSpacesMenu([['label' => 'Spaces', 'type' => 'group', 'children' => [
        ['label' => 'All workspace types', 'type' => 'section', 'section' => 'workspace_types'],
        spaceTypeMenuLink('Meeting rooms', 'meeting_room'),
    ]]]);
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'space_listings' && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'space_listings')) {
            $queries[] = $query->sql;
        }
    });

    expect(spacesHtml('/'))->toContain('All workspace types')->not->toContain('data-flyout-tab');
    get('/spaces/a-70-meeting-room-m1')->assertNotFound();
    actingAs($this->staff[0])->get(SpaceListingResource::getUrl('index'))->assertForbidden();
    expect(fn () => app(CreateDraft::class)->handle(NavigationMenu::query()->where('handle', 'main')->sole(), $this->staff[0], ['items' => [['label' => 'Spaces', 'type' => 'group', 'children' => [spaceTypeMenuLink('Meeting rooms', 'meeting_room')]]]]))
        ->toThrow(ValidationException::class);

    expect($queries)->toBe([]);
});

it('shows a space\'s photo, labels a stock photo as not the location, and serves it to visitors only while the space is shown (OD-177 e)', function () {
    Storage::fake('local');
    $stock = app(CreateMediaAsset::class)->handle($this->staff[2], UploadedFile::fake()->image('cabin.jpg', 1600, 1000), [
        'title' => 'Stock cabin', 'origin' => MediaOrigin::LicensedStock->value, 'alt_text' => 'A glass-walled cabin', 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'REF-1',
    ]);
    $listing = ($this->listing)('A-70', 'private_cabin', 'Cabin C1', ['media_asset_id' => $stock->id]);

    expect(spacesHtml('/spaces/a-70-cabin-c1'))->toContain('src="/media/'.$stock->id.'/w960"')->toContain('alt="A glass-walled cabin"')->toContain('Stock photo, not a SmartGro location');
    get('/media/'.$stock->id.'/w960')->assertOk();

    $listing->forceFill(['status' => 'unpublished'])->save();

    get('/media/'.$stock->id.'/w960')->assertNotFound();
});

it('lists shown spaces in the sitemap and the search titles report, never an example space (OD-177 e)', function () {
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M1');
    ($this->listing)('A-70', 'private_cabin', 'Cabin C1', ['is_sample' => true]);

    get('/sitemap.xml')->assertOk()->assertSee('/spaces/a-70-meeting-room-m1', false)->assertDontSee('/spaces/a-70-cabin-c1', false);
    expect(app(SearchMetadata::class)->rows()->firstWhere('key', '/spaces/a-70-meeting-room-m1'))
        ->toMatchArray(['type' => 'Space', 'title' => 'Meeting room M1 at SmartGro A-70 · SmartGro', 'description' => 'A quiet room for a small team.', 'source' => 'Space name and summary']);
});

it('keeps spaces working without the photo column, as on a database not migrated for it', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => ! ($table === 'space_listings' && $column === 'media_asset_id') && $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    ($this->listing)('A-70', 'meeting_room', 'Meeting room M1');
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'space_listings') && str_contains($query->sql, 'media_asset_id')) {
            $queries[] = $query->sql;
        }
    });

    get('/spaces/a-70-meeting-room-m1')->assertOk()->assertDontSee('/media/', false);
    $draft = app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->staff[0], ['name' => 'Draft room', 'media_asset_id' => 1]);

    expect($draft->payload)->not->toHaveKey('media_asset_id')
        ->and($queries)->toBe([]);
});
