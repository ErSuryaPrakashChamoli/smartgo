<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Access\SyncUserProperties;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\Pages\EditUser;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $this->properties = Property::query()->pluck('id', 'code');
});

it('replaces a staff member\'s assigned properties and audits who changed them', function () {
    app(SyncUserProperties::class)->handle($this->manager, [$this->properties['A-70'], $this->properties['A-71']], $this->admin);
    app(SyncUserProperties::class)->handle($this->manager, [$this->properties['A-71'], $this->properties['H-105']], $this->admin);

    $entry = Activity::query()->inLog('access')->where('event', 'properties_assigned')->latest('id')->first();

    expect($this->manager->assignedProperties()->orderBy('code')->pluck('code')->all())->toBe(['A-71', 'H-105'])
        ->and($this->manager->assignedProperties()->first()->pivot->assigned_by)->toBe($this->admin->id)
        ->and($entry->causer->is($this->admin))->toBeTrue()
        ->and($entry->properties->all())->toBe(['added' => ['H-105'], 'removed' => ['A-70']]);
});

it('writes no audit entry when nothing changes', function () {
    app(SyncUserProperties::class)->handle($this->manager, [$this->properties['A-70']], $this->admin);
    app(SyncUserProperties::class)->handle($this->manager, [$this->properties['A-70']], $this->admin);

    expect(Activity::query()->where('event', 'properties_assigned')->count())->toBe(1);
});

it('refuses a property that does not exist', function () {
    app(SyncUserProperties::class)->handle($this->manager, [999999], $this->admin);
})->throws(ValidationException::class);

it('assigns properties from the staff editor', function () {
    actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $this->manager->getRouteKey()])
        ->assertSet('data.property_ids', [])
        ->fillForm(['property_ids' => [(string) $this->properties['H-105']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->manager->assignedProperties()->pluck('code')->all())->toBe(['H-105']);
});
