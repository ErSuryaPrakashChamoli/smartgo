<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Actions\Media\CreateMediaAsset;
use App\Enums\Access\StaffRole;
use App\Enums\Content\MediaOrigin;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\EditPropertyProfile;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\ListPropertyProfiles;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Widgets\WaitingForApproval;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->manager->assignedProperties()->attach($this->property);
    offerForTest($this->property, 'hot_desk', published: false);
});

it('lists every profile for marketing staff and only assigned ones for a Property Manager', function () {
    actingAs($this->marketing);
    Livewire::test(ListPropertyProfiles::class)->assertCountTableRecords(4);

    actingAs($this->manager);
    Livewire::test(ListPropertyProfiles::class)->assertCanSeeTableRecords([$this->property->profile])->assertCountTableRecords(1);

    $other = Property::query()->where('code', 'H-105')->sole()->profile;
    actingAs($this->manager)->get(PropertyProfileResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())->get(PropertyProfileResource::getUrl('index'))->assertForbidden();
});

it('writes a profile draft in the editor and submits it for approval', function () {
    actingAs($this->manager);

    Livewire::test(EditPropertyProfile::class, ['record' => $this->property->profile->getRouteKey()])
        ->callAction('startDraft')
        ->fillForm([
            'headline' => 'Offices near the metro', 'summary' => 'Private offices and meeting rooms.', 'getting_there' => 'Five minutes from the metro.',
            'nearest_metro' => 'Sector 15 station', 'metro_walk_minutes' => '5', 'parking' => 'Basement parking',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->callAction('submit')
        ->assertNotified('Submitted for approval');

    expect($this->property->profile->openRevision())
        ->state->value->toBe('submitted')
        ->payload->toMatchArray(['headline' => 'Offices near the metro', 'getting_there' => 'Five minutes from the metro.', 'nearest_metro' => 'Sector 15 station', 'metro_walk_minutes' => 5, 'parking' => 'Basement parking']);
});

it('links the property editor to its profile and lists profiles waiting for approval', function () {
    actingAs($this->manager)->get(PropertyResource::getUrl('edit', ['record' => $this->property]))
        ->assertOk()
        ->assertSee(PropertyProfileResource::getUrl('edit', ['record' => $this->property->profile]), false);

    app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($this->property->profile, $this->manager, ['headline' => 'H', 'summary' => 'S']), $this->manager);

    actingAs($this->marketing);
    Livewire::test(WaitingForApproval::class)->assertSee(['SmartGro A-70', 'Property profile, version 1']);
});

it('shows a Property Manager the take-down action of a live profile only as unavailable', function () {
    publishPropertyProfileForTest($this->property);
    actingAs($this->manager);

    Livewire::test(EditPropertyProfile::class, ['record' => $this->property->profile->getRouteKey()])
        ->assertActionVisible('takeDown')
        ->assertActionDisabled('takeDown');
});

it('offers stock photos in the photo picker only as sample content, and always shows the ones a profile already holds (OD-176)', function () {
    Storage::fake('local');
    $photo = fn (MediaOrigin $origin, ?int $propertyId, string $title) => app(CreateMediaAsset::class)->handle($this->marketing, UploadedFile::fake()->image('photo.jpg', 1200, 800), [
        'title' => $title, 'origin' => $origin->value, 'alt_text' => 'A room', 'not_from_old_website' => true,
        'licence_holder' => 'Holder', 'licence_reference' => 'L-1', 'depicts_property_id' => $propertyId,
    ]);
    $photo(MediaOrigin::ClientPhotography, $this->property->id, 'Reception at A-70');
    $inUse = $photo(MediaOrigin::LicensedStock, null, 'Stock lounge');
    $photo(MediaOrigin::LicensedStock, null, 'Stock desk');
    config(['backoffice.sample_content' => true]);
    app(CreateDraft::class)->handle($this->property->profile, $this->marketing, ['headline' => 'H', 'summary' => 'S', 'hero_asset_id' => $inUse->id]);
    actingAs($this->marketing);

    Livewire::test(EditPropertyProfile::class, ['record' => $this->property->profile->getRouteKey()])
        ->assertOk()->assertSee('Reception at A-70')->assertSee('Stock lounge (stock photo, sample content only)')->assertSee('Stock desk (stock photo, sample content only)');

    config(['backoffice.sample_content' => false]);

    Livewire::test(EditPropertyProfile::class, ['record' => $this->property->profile->getRouteKey()])
        ->assertOk()->assertSee('Reception at A-70')->assertSee('Stock lounge (stock photo, sample content only)')->assertDontSee('Stock desk');
});

it('chooses a 3D model, its guided views and floor plans in the editor, and saves them in the draft (OD-181)', function () {
    Storage::fake('local');
    $undoRepeaterFake = Repeater::fake();
    $model = uploadTwinModelForTest($this->marketing, $this->property, ['title' => 'Ground floor model']);
    uploadTwinModelForTest($this->marketing, Property::query()->where('code', 'H-105')->sole(), ['title' => 'Model of another location']);
    $plan = app(CreateMediaAsset::class)->handle($this->marketing, UploadedFile::fake()->image('plan.png', 1600, 1000), [
        'title' => 'Ground floor plan', 'origin' => MediaOrigin::Commissioned->value, 'alt_text' => 'Plan of the ground floor',
        'not_from_old_website' => true, 'depicts_property_id' => $this->property->id,
    ]);
    actingAs($this->marketing);

    Livewire::test(EditPropertyProfile::class, ['record' => $this->property->profile->getRouteKey()])
        ->callAction('startDraft')
        ->assertFormFieldExists('twin_model_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$model->id])
        ->assertFormFieldHidden('twin_tour_stops')
        ->fillForm([
            'headline' => 'Offices near the metro', 'summary' => 'Private offices and meeting rooms.', 'twin_model_id' => $model->id,
            'twin_tour_stops' => [['camera' => 'SG_CAM_1', 'title' => 'Reception']],
            'floor_plans' => [['label' => 'Ground floor', 'media_asset_id' => $plan->id, 'note' => 'Reception and cabins.', 'is_example' => false]],
        ])
        ->assertFormFieldVisible('twin_tour_stops')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->property->profile->openRevision()->payload)->toMatchArray([
        'twin_model_id' => $model->id,
        'twin_tour_stops' => [['camera' => 'SG_CAM_1', 'title' => 'Reception']],
        'floor_plans' => [['label' => 'Ground floor', 'media_asset_id' => $plan->id, 'note' => 'Reception and cabins.', 'is_example' => false]],
    ]);

    $undoRepeaterFake();
});
