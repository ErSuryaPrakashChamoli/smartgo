<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\UnpublishContent;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Floor;
use App\Models\NavigationMenu;
use App\Models\Property;
use App\Models\Revision;
use App\Models\Space;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Models\User;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\DummyStaffSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleContentSeeder;
use Database\Seeders\SpaceListingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
    seed(SampleContentSeeder::class);
});

it('publishes three example spaces per space type at every location through the workflow, makes Spaces a tab per type, and adds nothing when run again (OD-177)', function () {
    config(['backoffice.sample_content' => false]);

    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);
    $revisions = Revision::query()->count();
    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);

    $author = User::query()->where('email', DummyStaffSeeder::email('marketing'))->sole();
    $approver = User::query()->where('email', DummyStaffSeeder::email('marketing2'))->sole();
    $listings = SpaceListing::query()->published()->with(['property', 'spaceType', 'publishedRevision'])->get();
    $spaces = collect(NavigationMenu::query()->where('handle', 'main')->sole()->toRevisionPayload()['items'])->firstWhere('label', 'Spaces')['children'];

    expect($listings)->toHaveCount(4 * 6 * 3)
        ->and($listings->every(fn (SpaceListing $listing): bool => $listing->is_sample && $listing->seats === null && $listing->publishedRevision->author_id === $author->id && $listing->publishedRevision->reviewer_id === $approver->id))->toBeTrue()
        ->and($listings->countBy(fn (SpaceListing $listing): string => $listing->property->code.' '.$listing->spaceType->key)->unique()->values()->all())->toBe([3])
        ->and($listings->firstWhere('slug', 'a-70-cabin-c1'))->name->toBe('Cabin C1')->features->toBe(SpaceListingSeeder::EXAMPLES['private_cabin']['features'])
        ->and(array_column($spaces, 'label'))->toBe(['All workspace types', 'Private cabins', 'Dedicated desks', 'Flexible seating', 'Meeting rooms', 'Conference rooms', 'Office suites'])
        ->and(array_column($spaces, 'type'))->toBe(['section', ...array_fill(0, 6, 'space_type')])
        ->and(Revision::query()->count())->toBe($revisions)
        ->and(config('backoffice.sample_content'))->toBeFalse();

    expect(get('/')->assertOk()->getContent())->toContain('data-flyout-tab')->toContain('href="/spaces/a-70-cabin-c1"')->toContain('Example spaces: not yet confirmed by SmartGro.');
    get('/spaces/h-105-board-room-t3')->assertOk()->assertSee('Board room T3')->assertSee('SmartGro H-105');
});

it('lists the inventory\'s own spaces instead of examples where any is publicly listable and in use', function () {
    $a70 = Property::query()->where('code', 'A-70')->sole();
    $meetingRoom = SpaceType::query()->where('key', 'meeting_room')->sole();
    $floor = Floor::factory()->for($a70)->create();
    $space = Space::factory()->for($a70)->for($floor)->create(['space_type_id' => $meetingRoom->id, 'code' => 'A70-MR-01', 'name' => 'Lotus', 'capacity_seats' => 8, 'is_publicly_listable' => true, 'operational_status' => SpaceStatus::Active]);
    Space::factory()->for($a70)->for($floor)->create(['space_type_id' => $meetingRoom->id, 'code' => 'A70-MR-02', 'is_publicly_listable' => false]);

    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);

    $atA70 = SpaceListing::query()->published()->where('property_id', $a70->id)->where('space_type_id', $meetingRoom->id)->get();

    expect($atA70)->toHaveCount(1)
        ->and($atA70->sole())->space_id->toBe($space->id)->name->toBe('Lotus')->seats->toBe(8)->is_sample->toBeFalse()->slug->toBe('a-70-a70-mr-01')
        ->and(SpaceListing::query()->published()->where('space_type_id', $meetingRoom->id)->count())->toBe(1 + 3 * 3);
});

it('leaves a space someone switched off, or the team\'s own menu, alone when run again', function () {
    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);
    $listing = SpaceListing::query()->where('slug', 'a-70-cabin-c1')->sole();
    $staff = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    app(UnpublishContent::class)->handle($listing, $staff, 'Not offered here.', false);
    // Its last version has older words than the seeder's, so only the switch keeps the seeder from putting it back.
    $listing->publishedRevision->forceFill(['payload' => [...$listing->publishedRevision->payload, 'summary' => 'Older example words.']])->save();
    $menu = NavigationMenu::query()->where('handle', 'main')->sole();
    $menu->publishedRevision->forceFill(['author_id' => $staff->id])->save();
    $revisions = Revision::query()->count();

    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);

    expect($listing->refresh()->status)->toBe('unpublished')
        ->and(Revision::query()->count())->toBe($revisions)
        ->and($listing->revisions()->whereIn('state', RevisionState::open())->exists())->toBeFalse();
});

it('is refused in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(SpaceListingSeeder::class)->seedWith(User::factory()->create(), User::factory()->create(), User::factory()->create()))
        ->toThrow(RuntimeException::class, 'Sample spaces are for local databases only.');
});

it('gives the example spaces the stock photos found in the media library, labelled on their pages (OD-177 e)', function () {
    Storage::fake('local');
    $cabins = app(CreateMediaAsset::class)->handle(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create(), UploadedFile::fake()->image('cabins.jpg', 1600, 1000), [
        'title' => DemoContentSeeder::IMAGES['cabins']['title'], 'origin' => MediaOrigin::LicensedStock->value, 'alt_text' => 'Cabins', 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'REF-1',
    ]);

    Artisan::call('db:seed', ['--class' => SpaceListingSeeder::class]);

    $photo = fn (string $slug): ?int => SpaceListing::query()->where('slug', $slug)->value('media_asset_id');

    expect($photo('a-70-cabin-c1'))->toBe($cabins->id)
        ->and($photo('h-105-office-suite-s2'))->toBe($cabins->id)
        ->and($photo('a-70-meeting-room-m1'))->toBeNull();
    get('/spaces/a-70-cabin-c1')->assertOk()->assertSee('Stock photo, not a SmartGro location');
});
