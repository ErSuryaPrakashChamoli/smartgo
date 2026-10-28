<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Properties\SaveProperty;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Filament\Clusters\Properties\Resources\Cities\CityResource;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\EditProperty;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\ListProperties;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Models\City;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->assigned = Property::query()->where('code', 'A-70')->sole();
    $this->other = Property::query()->where('code', 'H-105')->sole();
    $this->manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $this->manager->assignedProperties()->attach($this->assigned);
});

/**
 * The property's current details as SaveProperty takes them, with changes.
 *
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function currentPropertyData(Property $property, array $changes = []): array
{
    return [
        ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
        'operating_model' => $property->operating_model->value,
        'operational_status' => $property->operational_status->value,
        'building' => $property->primaryBuilding->only(['address_line_1', 'locality', 'postal_code', 'state']),
        ...$changes,
    ];
}

it('lists and opens only the Property Manager\'s assigned properties', function () {
    actingAs($this->manager);

    Livewire::test(ListProperties::class)
        ->assertCanSeeTableRecords([$this->assigned])
        ->assertCanNotSeeTableRecords([$this->other]);

    actingAs($this->manager)->get(PropertyResource::getUrl('edit', ['record' => $this->assigned]))->assertOk();
    actingAs($this->manager)->get(PropertyResource::getUrl('edit', ['record' => $this->other]))->assertNotFound();
});

it('lets a Property Manager edit an assigned property and nothing else', function () {
    $saved = app(SaveProperty::class)->handle($this->manager, $this->assigned, currentPropertyData($this->assigned, [
        'building' => ['address_line_1' => 'A-70', 'locality' => 'Sector 2', 'postal_code' => '201301'],
    ]));

    expect($saved->primaryBuilding->postal_code)->toBe('201301')
        ->and(fn () => app(SaveProperty::class)->handle($this->manager, $this->other, currentPropertyData($this->other)))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveProperty::class)->handle($this->manager, null, currentPropertyData($this->assigned, ['code' => 'B-1', 'slug' => 'b-1'])))->toThrow(AuthorizationException::class);
});

it('keeps cities, sectors, the categories offered and the property\'s status out of a Property Manager\'s reach', function () {
    $hotDesks = Product::query()->where('key', 'hot_desk')->sole();
    $this->assigned->products()->attach($hotDesks, ['is_active' => true]);

    expect($this->manager->can('create', City::class))->toBeFalse()
        ->and($this->manager->can('update', $this->assigned->city))->toBeFalse()
        ->and($this->manager->can('create', MicroMarket::class))->toBeFalse()
        ->and(fn () => app(SaveProperty::class)->handle($this->manager, $this->assigned, currentPropertyData($this->assigned, ['product_ids' => []])))
        ->toThrow(AuthorizationException::class, 'You cannot change which workspace categories a property offers.');

    expect(fn () => app(SaveProperty::class)->handle($this->manager, $this->assigned, currentPropertyData($this->assigned, ['operational_status' => PropertyStatus::Closed->value])))
        ->toThrow(AuthorizationException::class, 'Only operations staff can change')
        ->and(fn () => app(SaveProperty::class)->handle($this->manager, $this->assigned, currentPropertyData($this->assigned, ['slug' => 'a-70-new'])))
        ->toThrow(AuthorizationException::class, 'Only operations staff can change');

    actingAs($this->manager);
    Livewire::test(EditProperty::class, ['record' => $this->assigned->getRouteKey()])
        ->assertFormFieldDisabled('product_ids')
        ->assertFormFieldDisabled('operational_status')
        ->assertFormFieldDisabled('slug')
        ->fillForm(['building' => ['address_line_1' => 'A-70', 'locality' => 'Sector 2', 'postal_code' => '201301']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->assigned->fresh())
        ->operational_status->toBe(PropertyStatus::Open)
        ->and($this->assigned->fresh()->primaryBuilding->postal_code)->toBe('201301')
        ->and($this->assigned->products()->wherePivot('is_active', true)->pluck('key')->all())->toBe(['hot_desk']);
    actingAs($this->manager)->get(CityResource::getUrl('create'))->assertForbidden();
});

it('gives a user who also holds an unlimited role that role\'s reach', function () {
    $this->manager->assignRole(StaffRole::OperationsManager);

    expect($this->manager->can('update', $this->other))->toBeTrue()
        ->and($this->manager->can('create', Property::class))->toBeTrue();
});

it('gives a Property Manager without assignments no property at all', function () {
    $unassigned = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();

    actingAs($unassigned);

    Livewire::test(ListProperties::class)->assertCountTableRecords(0);
    expect($unassigned->can('view', $this->assigned))->toBeFalse();
});
