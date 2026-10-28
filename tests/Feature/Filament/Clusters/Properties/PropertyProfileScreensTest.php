<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\EditPropertyProfile;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\ListPropertyProfiles;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Widgets\WaitingForApproval;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
