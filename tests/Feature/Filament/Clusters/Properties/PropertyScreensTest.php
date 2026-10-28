<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Properties\Resources\Cities\CityResource;
use App\Filament\Clusters\Properties\Resources\Cities\Pages\CreateCity;
use App\Filament\Clusters\Properties\Resources\Cities\Pages\EditCity;
use App\Filament\Clusters\Properties\Resources\Cities\RelationManagers\MicroMarketsRelationManager;
use App\Filament\Clusters\Properties\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Properties\Resources\Products\ProductResource;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\CreateProperty;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\EditProperty;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Models\City;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->operations = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();
});

it('lists the properties, cities and categories for staff who may see them', function (StaffRole $role) {
    $user = User::factory()->withEmailAuthentication()->withRole($role)->create();

    actingAs($user)->get(PropertyResource::getUrl('index'))->assertOk()->assertSee('A-70')->assertSee('H-105, Sector 62, Noida')->assertSee('None recorded');
    actingAs($user)->get(CityResource::getUrl('index'))->assertOk()->assertSee('Operating: shown');
    actingAs($user)->get(ProductResource::getUrl('index'))->assertOk()->assertSee('Conference and training rooms');
})->with([StaffRole::OperationsManager, StaffRole::MarketingManager, StaffRole::Finance]);

it('lets only staff who may manage properties open the forms', function () {
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $property = Property::query()->where('code', 'A-70')->sole();

    actingAs($marketing)->get(PropertyResource::getUrl('create'))->assertForbidden();
    actingAs($marketing)->get(PropertyResource::getUrl('edit', ['record' => $property]))->assertForbidden();
    actingAs($this->operations)->get(PropertyResource::getUrl('edit', ['record' => $property]))->assertOk();
});

it('adds a property from the form', function () {
    actingAs($this->operations);
    $noida = City::query()->where('slug', 'noida')->sole();
    $hotDesks = Product::query()->where('key', 'hot_desk')->sole();

    Livewire::test(CreateProperty::class)
        ->fillForm([
            'name' => 'B-12', 'code' => 'B-12', 'slug' => 'b-12', 'city_id' => $noida->id,
            'micro_market_id' => MicroMarket::query()->where('slug', 'sector-62')->value('id'),
            'operating_model' => 'owned_operated', 'operational_status' => 'planned',
            'building' => ['address_line_1' => 'B-12', 'locality' => 'Sector 62'],
            'product_ids' => [(string) $hotDesks->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Property::query()->where('code', 'B-12')->sole())
        ->primaryBuilding->address_line_1->toBe('B-12')
        ->and(Property::query()->where('code', 'B-12')->sole()->products->pluck('key')->all())->toBe(['hot_desk']);
});

it('shows server-side errors on the form fields', function () {
    actingAs($this->operations);
    $property = Property::query()->where('code', 'A-70')->sole();

    Livewire::test(EditProperty::class, ['record' => $property->getRouteKey()])
        ->assertSet('data.code', 'A-70')
        ->assertSet('data.building.address_line_1', 'A-70')
        ->assertSet('data.building.locality', 'Sector 2')
        ->fillForm(['code' => 'A-71', 'building' => ['address_line_1' => 'A-70', 'locality' => 'Sector 2', 'postal_code' => '0123']])
        ->call('save')
        ->assertHasFormErrors(['code', 'building.postal_code']);

    expect($property->fresh()->code)->toBe('A-70');
});

it('refuses compare as a city address, which is the comparison page', function () {
    actingAs($this->operations);

    Livewire::test(CreateCity::class)
        ->fillForm(['name' => 'Compare', 'slug' => 'compare'])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(City::query()->where('slug', 'compare')->exists())->toBeFalse();
});

it('adds and edits cities, sectors and workspace categories from their forms, audited', function () {
    actingAs($this->operations);

    Livewire::test(CreateCity::class)
        ->fillForm(['name' => 'Test City', 'slug' => 'test-city', 'state' => 'Test State'])
        ->call('create')
        ->assertHasNoFormErrors();
    $city = City::query()->where('slug', 'test-city')->sole();

    Livewire::test(EditCity::class, ['record' => $city->getRouteKey()])
        ->fillForm(['is_publicly_visible' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    Livewire::test(MicroMarketsRelationManager::class, ['ownerRecord' => $city, 'pageClass' => EditCity::class])
        ->callAction(TestAction::make('create')->table(), ['name' => 'Test Sector', 'slug' => 'test-sector'])
        ->assertHasNoActionErrors();
    $sector = MicroMarket::query()->where('slug', 'test-sector')->sole();

    Livewire::test(MicroMarketsRelationManager::class, ['ownerRecord' => $city, 'pageClass' => EditCity::class])
        ->callAction(TestAction::make('edit')->table($sector), ['name' => 'Test Sector Two', 'slug' => 'test-sector-two'])
        ->assertHasNoActionErrors();

    $product = Product::query()->where('key', 'hot_desk')->sole();

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm(['sort_order' => 25])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($city->fresh()->is_publicly_visible)->toBeTrue()
        ->and($sector->fresh())->city_id->toBe($city->id)->slug->toBe('test-sector-two')
        ->and($product->fresh()->sort_order)->toBe(25)
        ->and(Activity::query()->inLog('properties')->causedBy($this->operations)->pluck('event')->all())->toBe(['created', 'updated', 'created', 'updated', 'updated']);
});
