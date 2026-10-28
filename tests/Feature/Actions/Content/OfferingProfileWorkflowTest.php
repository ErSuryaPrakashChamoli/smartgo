<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Actions\Properties\SaveProperty;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\Offering;
use App\Models\OfferingProfile;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    $this->hotDesks = Product::query()->where('key', 'hot_desk')->sole();
});

/**
 * Records the categories a property offers, as operations staff do on the property form.
 *
 * @param  list<int>  $productIds
 */
function recordOfferings(User $user, Property $property, array $productIds): void
{
    app(SaveProperty::class)->handle($user, $property, [
        ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
        'operating_model' => $property->operating_model->value,
        'operational_status' => $property->operational_status->value,
        'building' => $property->primaryBuilding->only(['address_line_1', 'locality']),
        'product_ids' => $productIds,
    ]);
}

/**
 * @param  array<string, mixed>  $payload
 */
function publishOffering(OfferingProfile $profile, User $author, User $approver, User $publisher, array $payload): Revision
{
    $draft = app(CreateDraft::class)->handle($profile, $author, $payload);

    return app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

it('gives each recorded category an unpublished profile, and deactivates rather than deletes one no longer offered', function () {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $offering = Offering::query()->sole();

    expect($offering)->is_active->toBeTrue()
        ->and($offering->profile)->not->toBeNull()->status->toBe('draft');
    get('/locations/noida/a-70/hot-desks')->assertNotFound();

    recordOfferings($this->operations, $this->property, []);

    expect($offering->fresh()->is_active)->toBeFalse()
        ->and(OfferingProfile::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'offerings_changed')->latest('id')->first()->properties['removed'])->toBe([$this->hotDesks->id]);

    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);

    expect(Offering::query()->count())->toBe(1)->and($offering->fresh()->is_active)->toBeTrue();
});

it('allows one offering per property and category', function () {
    Offering::query()->forceCreate(['property_id' => $this->property->id, 'product_id' => $this->hotDesks->id, 'is_active' => true]);

    Offering::query()->forceCreate(['property_id' => $this->property->id, 'product_id' => $this->hotDesks->id, 'is_active' => true]);
})->throws(UniqueConstraintViolationException::class);

it('publishes an offering through draft, approval and publication by three people, and only then shows it', function () {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $profile = Offering::query()->sole()->profile;

    publishOffering($profile, $this->author, $this->approver, $this->publisher, [
        'headline' => 'Desks near the metro', 'description' => 'Take any open desk.', 'included' => ['Wi-Fi', ' Tea and coffee '], 'not_included' => ['Lockers'],
        'seo_title' => 'Hot desks in Sector 2, Noida',
    ]);

    get('/locations/noida/a-70/hot-desks')->assertOk()
        ->assertSee('Desks near the metro')->assertSee('Tea and coffee')->assertSee('<title>Hot desks in Sector 2, Noida</title>', false);
    get('/locations/noida/a-70')->assertSee('href="/locations/noida/a-70/hot-desks"', false);
    get('/workspaces/hot-desks')->assertOk()->assertSee('SmartGro A-70');
});

it('needs a headline and description, and never takes capacity, availability or price (FR-CMS-6)', function () {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $profile = Offering::query()->sole()->profile;

    $draft = app(CreateDraft::class)->handle($profile, $this->author, ['headline' => '', 'capacity' => 40, 'price' => 5000, 'availability' => 'available']);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class)
        ->and(array_keys($draft->payload))->not->toContain('capacity')->not->toContain('price')->not->toContain('availability');
});

it('refuses to publish an offering that operations no longer record, whose category is off, or whose property is closed', function (Closure $makeIneligible, string $message) {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $profile = Offering::query()->sole()->profile;
    $draft = app(CreateDraft::class)->handle($profile, $this->author, ['headline' => 'H', 'description' => 'D']);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver);

    $makeIneligible($this);

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->publisher))->toThrow(ValidationException::class, $message)
        ->and($approved->fresh()->state)->toBe(RevisionState::Approved)
        ->and($profile->fresh()->isPublished())->toBeFalse();
})->with([
    'no longer offered' => [fn ($test) => recordOfferings($test->operations, $test->property, []), 'Operations staff no longer record this workspace category at the property.'],
    'category switched off' => [fn ($test) => $test->hotDesks->forceFill(['is_active' => false])->save(), 'This workspace category is switched off.'],
    'property closed' => [fn ($test) => $test->property->forceFill(['operational_status' => 'closed'])->save(), 'The property is closed, so it cannot be shown on the website.'],
]);

it('takes a published offering off the website at once, for publishers only', function () {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $profile = Offering::query()->sole()->profile;
    publishOffering($profile, $this->author, $this->approver, $this->publisher, ['headline' => 'Desks', 'description' => 'D']);
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);

    expect(fn () => app(UnpublishContent::class)->handle($profile->fresh(), $manager, 'Not offered now', false))->toThrow(AuthorizationException::class);

    app(UnpublishContent::class)->handle($profile->fresh(), $this->approver, 'Not offered now', false);

    get('/locations/noida/a-70/hot-desks')->assertNotFound();
    get('/workspaces/hot-desks')->assertNotFound();
    expect($profile->fresh()->status)->toBe('unpublished');
});

it('lets a Property Manager draft offerings of assigned properties only, and never publish them', function () {
    recordOfferings($this->operations, $this->property, [$this->hotDesks->id]);
    $other = Property::query()->where('code', 'H-105')->sole();
    recordOfferings($this->operations, $other, [$this->hotDesks->id]);
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    $ownProfile = $this->property->offerings()->sole()->profile;
    $otherProfile = $other->offerings()->sole()->profile;

    $draft = app(CreateDraft::class)->handle($ownProfile, $manager, ['headline' => 'Desks', 'description' => 'D']);
    app(SubmitRevision::class)->handle($draft, $manager);

    expect(fn () => app(CreateDraft::class)->handle($otherProfile, $manager, ['headline' => 'X']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ApproveRevision::class)->handle($draft->fresh(), $manager))->toThrow(AuthorizationException::class);

    $approved = app(ApproveRevision::class)->handle($draft->fresh(), $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($approved, $manager))->toThrow(AuthorizationException::class);
});

it('keeps a published offering off the website while its property is not on it', function () {
    $planned = Property::factory()->withPrimaryBuilding()->create(['city_id' => $this->property->city_id]);
    offerForTest($planned, 'hot_desk');

    get('/workspaces/hot-desks')->assertNotFound();
    expect(Offering::query()->publiclyListed()->count())->toBe(0);
});
