<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Enums\Properties\PropertyStatus;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->manager->assignedProperties()->attach($this->property);
    offerForTest($this->property, 'hot_desk', published: false);
    $this->profile = $this->property->profile;
    $this->otherProfile = Property::query()->where('code', 'H-105')->sole()->profile;
});

function profilePhoto(User $user, MediaOrigin $origin = MediaOrigin::ClientPhotography, array $overrides = []): MediaAsset
{
    return app(CreateMediaAsset::class)->handle($user, UploadedFile::fake()->image('room.jpg', 1200, 800), [
        'title' => 'Meeting room', 'origin' => $origin->value, 'alt_text' => 'Meeting room with a long table',
        'not_from_old_website' => true, 'licence_holder' => 'Stock library', 'licence_reference' => 'L-1',
        'depicts_property_id' => Property::query()->where('code', 'A-70')->value('id'), ...$overrides,
    ]);
}

/**
 * Writes, approves and publishes a profile version with three different people.
 *
 * @param  array<string, mixed>  $payload
 */
function publishProfile(PropertyProfile $profile, User $author, User $approver, User $publisher, array $payload): Revision
{
    $draft = app(CreateDraft::class)->handle($profile, $author, $payload);

    return app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

it('creates an empty, unpublished profile for each property, which keeps it off the website', function () {
    expect(PropertyProfile::query()->count())->toBe(4)
        ->and(PropertyProfile::query()->where('status', 'published')->count())->toBe(0);

    get('/locations/noida/a-70')->assertNotFound();
});

it('refuses to submit or publish a profile while the property cannot be shown', function () {
    $other = Property::query()->where('code', 'A-71')->sole();
    $draft = app(CreateDraft::class)->handle($other->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S']);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->marketing))
        ->toThrow(ValidationException::class, 'Operations staff must record at least one workspace category this property offers first.');

    offerForTest($other, 'hot_desk', published: false);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft->fresh(), $this->marketing), $this->approver);
    $other->forceFill(['operational_status' => PropertyStatus::Closed])->save();

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->publisher))
        ->toThrow(ValidationException::class, 'The property is closed, so it cannot be shown on the website.')
        ->and($other->profile->fresh()->isPublished())->toBeFalse();
});

it('shows a profile on the property page only once a version is approved and published', function () {
    $draft = app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'Draft headline', 'summary' => 'Draft summary.']);
    get('/locations/noida/a-70')->assertDontSee('Draft headline');

    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->marketing), $this->approver);
    get('/locations/noida/a-70')->assertDontSee('Draft headline');

    app(PublishRevision::class)->handle($draft->fresh(), $this->publisher);

    get('/locations/noida/a-70')->assertOk()->assertSeeInOrder(['SmartGro A-70', 'Draft headline', 'Draft summary.']);
});

it('shows a location\'s own contact details only once a version with them is published, and SmartGro\'s main ones until then', function () {
    publishProfile($this->profile, $this->marketing, $this->approver, $this->publisher, ['headline' => 'Near the metro', 'summary' => 'Offices.']);
    $draft = app(CreateDraft::class)->handle($this->profile, $this->manager, [
        'headline' => 'Near the metro', 'summary' => 'Offices.', 'phone' => '+91 120 400 0000', 'whatsapp' => '+911204000000', 'email' => 'a70@example.com',
    ]);

    get('/locations/noida/a-70')->assertOk()->assertSee('Talk to SmartGro</h2>', false)->assertDontSee('+91 120 400 0000');

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->manager), $this->approver), $this->publisher);
    offerForTest($this->property, 'hot_desk');

    foreach (['/locations/noida/a-70', '/locations/noida/a-70/hot-desks'] as $path) {
        get($path)->assertOk()
            ->assertSee('Talk to SmartGro A-70</h2>', false)
            ->assertSee('href="tel:+911204000000"', false)
            ->assertSee('href="https://wa.me/911204000000"', false)
            ->assertSee('href="mailto:a70@example.com"', false);
    }
});

it('refuses contact details in the wrong format', function (string $field, string $value) {
    expect(fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', $field => $value]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'phone with letters' => ['phone', 'call reception'],
    'WhatsApp without the country code' => ['whatsapp', '01204000000'],
    'email' => ['email', 'reception at smartgro'],
]);

it('does not publish a scheduled profile once the property can no longer be shown, and audits why', function () {
    $draft = app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S']);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->marketing), $this->approver);
    app(ScheduleRevision::class)->handle($approved, $this->publisher, now()->addHour());
    $this->property->forceFill(['operational_status' => PropertyStatus::Closed])->save();
    $this->travel(1)->hours();

    $this->artisan('content:publish-scheduled')->expectsOutputToContain('Published 0 scheduled version(s).')->assertSuccessful();

    expect($this->profile->fresh()->isPublished())->toBeFalse()
        ->and($approved->fresh()->scheduled_for)->toBeNull()
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->sole()->properties['reason'])->toBe('The property is closed, so it cannot be shown on the website.');

    get('/locations/noida/a-70')->assertNotFound();
});

it('lets a Property Manager write profiles of assigned properties only, and never approve or publish them', function () {
    $draft = app(CreateDraft::class)->handle($this->profile, $this->manager, ['headline' => 'Near the metro', 'summary' => 'Two floors of offices.']);
    app(SubmitRevision::class)->handle($draft, $this->manager);

    expect(fn () => app(CreateDraft::class)->handle($this->otherProfile, $this->manager, ['headline' => 'X']))->toThrow(AuthorizationException::class, 'You cannot edit this content.')
        ->and(fn () => app(ApproveRevision::class)->handle($draft->fresh(), $this->manager))->toThrow(AuthorizationException::class);

    $approved = app(ApproveRevision::class)->handle($draft->fresh(), $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->manager))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PublishRevision::class)->handle($approved, $this->approver))->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.');
});

it('keeps publishing, scheduling and taking down a property\'s profile from Property Managers and operations staff', function () {
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    publishProfile($this->profile, $this->marketing, $this->approver, $this->publisher, ['headline' => 'Live', 'summary' => 'Live summary.']);
    $draft = app(CreateDraft::class)->handle($this->profile, $this->manager, ['headline' => 'Next', 'summary' => 'Next summary.']);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->manager), $this->approver);

    foreach ([$this->manager, $operations] as $user) {
        expect(fn () => app(PublishRevision::class)->handle($approved->fresh(), $user))->toThrow(AuthorizationException::class)
            ->and(fn () => app(ScheduleRevision::class)->handle($approved->fresh(), $user, now()->addHour()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(UnpublishContent::class)->handle($this->profile->fresh(), $user, 'Closed for works', false))->toThrow(AuthorizationException::class);
    }

    expect($this->profile->fresh()->isPublished())->toBeTrue()
        ->and($approved->fresh()->scheduled_for)->toBeNull();

    get('/locations/noida/a-70')->assertOk()->assertSee('Live summary.');
});

it('lets operations staff read every profile, and sales staff those of their properties, but neither write them', function () {
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $sales->assignedProperties()->attach($this->property);
    $draft = app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'Draft']);
    $otherDraft = app(CreateDraft::class)->handle($this->otherProfile, $this->marketing, ['headline' => 'Other']);

    expect($operations->can('view', $draft))->toBeTrue()
        ->and($operations->can('view', $otherDraft))->toBeTrue()
        ->and($sales->can('view', $draft))->toBeTrue()
        ->and($sales->can('view', $otherDraft))->toBeFalse()
        ->and(fn () => app(CreateDraft::class)->handle($this->otherProfile, $operations, ['headline' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateDraft::class)->handle($this->profile, $sales, ['headline' => 'X']))->toThrow(AuthorizationException::class);
});

it('refuses a photo uploaded for another property', function () {
    $photo = profilePhoto($this->marketing, overrides: ['depicts_property_id' => Property::query()->where('code', 'H-105')->value('id')]);

    expect(fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['hero_asset_id' => $photo->id]))
        ->toThrow(ValidationException::class, 'Choose a photo uploaded for this property.');
});

it('shows a location\'s getting-here details only once a version with them is published (FR-DISC-7)', function () {
    publishProfile($this->profile, $this->marketing, $this->approver, $this->publisher, ['headline' => 'Near the metro', 'summary' => 'Offices.']);
    $draft = app(CreateDraft::class)->handle($this->profile, $this->manager, [
        'headline' => 'Near the metro', 'summary' => 'Offices.', 'nearest_metro' => 'Sector 15 station', 'metro_walk_minutes' => '7',
        'buses' => 'Stop outside gate 2', 'parking' => 'Basement parking for visitors', 'accessibility' => 'Step-free entrance and lift',
    ]);

    get('/locations/noida/a-70')->assertOk()->assertDontSee('Sector 15 station');

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->manager), $this->approver), $this->publisher);

    expect($this->profile->fresh()->metro_walk_minutes)->toBe(7);
    get('/locations/noida/a-70')->assertOk()->assertSeeInOrder([
        'Getting here', 'Nearest metro station', 'Sector 15 station', 'Walk from the metro', '7 minutes',
        'Buses', 'Stop outside gate 2', 'Parking', 'Basement parking for visitors', 'Accessibility', 'Step-free entrance and lift',
    ]);
});

it('refuses getting-here details that are not plain text, or a walk without a station', function (array $fields, string $field) {
    expect(fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', ...$fields]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'walk without a station' => [['metro_walk_minutes' => 5], 'metro_walk_minutes'],
    'walk of no minutes' => [['nearest_metro' => 'Sector 15', 'metro_walk_minutes' => 0], 'metro_walk_minutes'],
    'walk over an hour' => [['nearest_metro' => 'Sector 15', 'metro_walk_minutes' => 61], 'metro_walk_minutes'],
    'station with markup' => [['nearest_metro' => '<b>Sector 15</b>'], 'nearest_metro'],
    'parking with markup' => [['parking' => '<script>x</script>'], 'parking'],
    'accessibility too long' => [['accessibility' => str_repeat('a', 301)], 'accessibility'],
]);

it('never lets a profile change status, capacity, availability or price (FR-CMS-6)', function () {
    publishProfile($this->profile, $this->marketing, $this->approver, $this->publisher, [
        'headline' => 'Open late', 'summary' => 'A short summary.',
        'operational_status' => 'closed', 'capacity' => 500, 'price' => 1, 'is_available' => false,
    ]);

    expect($this->property->fresh()->operational_status)->toBe(PropertyStatus::Open)
        ->and(array_keys($this->profile->fresh()->publishedRevision->payload))
        ->toEqualCanonicalizing(['headline', 'summary', 'getting_there', 'nearest_metro', 'metro_walk_minutes', 'buses', 'parking', 'accessibility', 'phone', 'whatsapp', 'email', 'hero_asset_id', 'gallery_asset_ids', 'seo_title', 'seo_description'])
        ->and($this->marketing->can('update', $this->property))->toBeFalse();
});

it('accepts only SmartGro or commissioned photography with a valid licence as the property\'s photos (BR-MED-2, BR-MED-3)', function (MediaOrigin $origin, array $overrides, bool $accepted) {
    $photo = profilePhoto($this->marketing, $origin, $overrides);
    $attempt = fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', 'gallery_asset_ids' => [$photo->id]]);

    $accepted
        ? expect($attempt()->payload['gallery_asset_ids'])->toBe([$photo->id])
        : expect($attempt)->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('gallery_asset_ids.0'));
})->with([
    'SmartGro photography' => [MediaOrigin::ClientPhotography, [], true],
    'commissioned' => [MediaOrigin::Commissioned, [], true],
    'AI concept' => [MediaOrigin::AiGeneratedConcept, [], false],
    'render' => [MediaOrigin::Render, [], false],
    'stock' => [MediaOrigin::LicensedStock, [], false],
    'expired licence' => [MediaOrigin::Commissioned, ['licence_expires_at' => now()->subDay()->toDateString()], false],
]);

it('refuses more than twelve photos or the same photo twice', function () {
    $photo = profilePhoto($this->marketing);

    expect(fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['gallery_asset_ids' => [$photo->id, $photo->id]]))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(CreateDraft::class)->handle($this->profile, $this->marketing, ['gallery_asset_ids' => array_fill(0, 13, $photo->id)]))
        ->toThrow(ValidationException::class);
});

it('shows published photos to visitors and keeps unpublished ones private', function () {
    $main = profilePhoto($this->marketing);
    $draftOnly = profilePhoto($this->marketing);
    publishProfile($this->profile, $this->marketing, $this->approver, $this->publisher, ['headline' => 'H', 'summary' => 'S', 'hero_asset_id' => $main->id]);
    app(CreateDraft::class)->handle($this->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', 'gallery_asset_ids' => [$draftOnly->id]]);

    get('/locations/noida/a-70')->assertOk()->assertSee($main->renditionPath('w960'), false)->assertSee('alt="Meeting room with a long table"', false);
    get($main->renditionPath('w480'))->assertOk();
    get($draftOnly->renditionPath('w480'))->assertNotFound();

    $this->property->forceFill(['operational_status' => PropertyStatus::TemporarilyClosed])->save();
    get($main->renditionPath('w480'))->assertNotFound();
});

it('previews a profile version on the property page for staff who may see it, never indexed', function () {
    $draft = app(CreateDraft::class)->handle($this->profile, $this->manager, ['headline' => 'Preview headline', 'summary' => 'S']);

    actingAs($this->manager)->get(RevisionPreviewController::url($draft))
        ->assertOk()
        ->assertSee('Preview headline')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    actingAs($this->manager)->get(RevisionPreviewController::url($draft))->assertDontSee('application/ld+json', false);

    $unassigned = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    actingAs($unassigned)->get(RevisionPreviewController::url($draft))->assertForbidden();
    get('/locations/noida/a-70')->assertDontSee('Preview headline');
});
