<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\SpaceListings\Pages\CreateSpaceListing;
use App\Filament\Clusters\Website\Resources\SpaceListings\Pages\ListSpaceListings;
use App\Filament\Clusters\Website\Resources\SpaceListings\SpaceListingResource;
use App\Filament\Clusters\Website\Resources\SpaceListings\Tables\SpaceListingsTable;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Floor;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->a70 = Property::query()->where('code', 'A-70')->sole();
    $this->meetingRoom = SpaceType::query()->where('key', 'meeting_room')->sole();
    $this->payload = fn (array $changes = []): array => [
        'property_id' => $this->a70->id, 'space_type_id' => $this->meetingRoom->id, 'name' => 'Meeting room M1', 'slug' => 'a-70-meeting-room-m1',
        'summary' => 'A room for client meetings.', ...$changes,
    ];
    // A live listing as the workflow leaves it: written by the author, approved by the approver, published by the publisher.
    $this->live = function (array $changes = []): SpaceListing {
        $draft = app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->author, ($this->payload)($changes));
        app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver), $this->publisher);

        return $draft->revisionable->refresh();
    };
});

it('lists the spaces with their location and type, what is live and what is in progress, and refuses staff who cannot view content', function () {
    ($this->live)();
    app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->author, ($this->payload)(['name' => 'Only in a draft', 'slug' => 'a-70-only-in-a-draft']));

    actingAs($this->author)->get(SpaceListingResource::getUrl('index'))->assertOk()
        ->assertSeeInOrder(['Meeting room M1', 'SmartGro A-70 · Meeting room'])
        ->assertSee('Only in a draft')->assertSee('v1: Draft');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get(SpaceListingResource::getUrl('index'))->assertForbidden();
});

it('creates a space as an unpublished draft with its details, and fills in the address from the location and name', function () {
    actingAs($this->author);

    Livewire::test(CreateSpaceListing::class)
        ->fillForm(['property_id' => $this->a70->id, 'space_type_id' => $this->meetingRoom->id])
        ->fillForm(['name' => 'Meeting room M1'])
        ->assertSchemaStateSet(['slug' => 'a-70-meeting-room-m1'])
        ->fillForm([
            'summary' => 'A room for client meetings.', 'details' => "First.\n\nSecond.", 'seats' => 6,
            'features' => ['Whiteboard', 'Screen'], 'sort_order' => 10, 'is_sample' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $listing = SpaceListing::query()->sole();

    expect($listing)->status->toBe('draft')->name->toBeNull()
        ->and($listing->revisions()->sole())
        ->state->toBe(RevisionState::Draft)
        ->payload->toMatchArray([
            'property_id' => $this->a70->id, 'space_type_id' => $this->meetingRoom->id, 'space_id' => null, 'name' => 'Meeting room M1', 'slug' => 'a-70-meeting-room-m1',
            'details' => "First.\n\nSecond.", 'seats' => 6, 'features' => ['Whiteboard', 'Screen'], 'is_sample' => true, 'sort_order' => 10,
        ]);
});

it('refuses an inventory space at another location or of another type, and an example space where sample content is not allowed', function () {
    $floor = Floor::factory()->for($this->a70)->create();
    $here = Space::factory()->for($this->a70)->for($floor)->create(['space_type_id' => $this->meetingRoom->id]);
    $otherType = Space::factory()->for($this->a70)->for($floor)->create(['space_type_id' => SpaceType::query()->where('key', 'private_cabin')->value('id')]);
    $submit = fn (array $changes) => app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->author, ($this->payload)($changes)), $this->author);

    config(['backoffice.sample_content' => false]);

    expect(fn () => $submit(['space_id' => $otherType->id]))->toThrow(ValidationException::class, 'Choose an inventory space at this location and of this space type, or leave it empty.')
        ->and(fn () => $submit(['is_sample' => true, 'slug' => 'a-70-example']))->toThrow(ValidationException::class, 'This website does not publish example spaces.')
        ->and($submit(['space_id' => $here->id, 'slug' => 'a-70-real'])->state)->toBe(RevisionState::Submitted);
});

it('switches a live space off at once from the list, with the reason in the audit log', function () {
    $listing = ($this->live)();
    actingAs($this->approver);

    Livewire::test(ListSpaceListings::class)
        ->call('updateTableColumnState', 'switched_on', (string) $listing->id, false)
        ->assertNotified('Switched off');

    expect($listing->refresh()->status)->toBe('unpublished')
        ->and(Activity::query()->where('event', 'content_unpublished')->sole()->properties->get('reason'))->toBe(SpaceListingsTable::SWITCHED_OFF_REASON);
    $this->get('/spaces/a-70-meeting-room-m1')->assertNotFound();
});

it('switches a space back on only through approval: a new version is submitted and the space stays off until it is published', function () {
    $listing = ($this->live)();
    actingAs($this->approver);
    Livewire::test(ListSpaceListings::class)->call('updateTableColumnState', 'switched_on', (string) $listing->id, false);

    Livewire::test(ListSpaceListings::class)
        ->call('updateTableColumnState', 'switched_on', (string) $listing->id, true)
        ->assertNotified('Sent for approval');

    $version = $listing->revisions()->where('number', 2)->sole();
    expect($listing->refresh()->status)->toBe('unpublished')
        ->and($version)->state->toBe(RevisionState::Submitted)->author_id->toBe($this->approver->id)
        ->and($version->payload)->toMatchArray(['name' => 'Meeting room M1', 'slug' => 'a-70-meeting-room-m1']);

    Livewire::test(ListSpaceListings::class)
        ->call('updateTableColumnState', 'switched_on', (string) $listing->id, true)
        ->assertNotified('Already in progress');

    expect($listing->revisions()->count())->toBe(2);

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle($version, $this->author), $this->publisher);

    $this->get('/spaces/a-70-meeting-room-m1')->assertOk();
});

it('previews a space version as its page, marked as a preview', function () {
    $draft = app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->author, ($this->payload)(['name' => 'Draft room name', 'features' => ['Whiteboard']]));

    actingAs($this->author)->get(RevisionPreviewController::url($draft))->assertOk()
        ->assertSeeInOrder(['Draft room name', 'SmartGro A-70', 'A room for client meetings.', 'Whiteboard'])
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('takes a photo of the space\'s own location, a stock photo only where sample content is allowed, and never another location\'s (OD-177 e)', function () {
    Storage::fake('local');
    $image = fn (string $title, MediaOrigin $origin, ?Property $property = null): MediaAsset => app(CreateMediaAsset::class)->handle($this->publisher, UploadedFile::fake()->image(str($title)->slug().'.jpg', 1600, 1000), [
        'title' => $title, 'origin' => $origin->value, 'alt_text' => 'Alt for '.$title, 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'REF-1', 'depicts_property_id' => $property?->id,
    ]);
    $own = $image('A-70 meeting room', MediaOrigin::ClientPhotography, $this->a70);
    $elsewhere = $image('A-23 meeting room', MediaOrigin::ClientPhotography, Property::query()->where('code', 'A-23')->sole());
    $stock = $image('Stock meeting room', MediaOrigin::LicensedStock);
    $submit = fn (array $changes) => app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle(SpaceListing::factory()->create(), $this->author, ($this->payload)($changes)), $this->author);

    config(['backoffice.sample_content' => false]);

    expect(fn () => $submit(['media_asset_id' => $elsewhere->id, 'slug' => 'a-70-elsewhere']))->toThrow(ValidationException::class, 'Choose a photo uploaded for this space\'s location.')
        ->and(fn () => $submit(['media_asset_id' => $stock->id, 'slug' => 'a-70-stock']))->toThrow(ValidationException::class, 'Stock photos can only be sample content')
        ->and($submit(['media_asset_id' => $own->id, 'slug' => 'a-70-own'])->payload['media_asset_id'])->toBe($own->id);

    config(['backoffice.sample_content' => true]);

    expect($submit(['media_asset_id' => $stock->id, 'slug' => 'a-70-stock-sample', 'is_sample' => true])->payload['media_asset_id'])->toBe($stock->id);
});
