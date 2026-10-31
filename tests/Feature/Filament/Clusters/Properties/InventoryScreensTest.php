<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Inventory\RecordAllocation;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Filament\Clusters\Properties\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\EditProperty;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\Properties\RelationManagers\FloorsRelationManager;
use App\Filament\Clusters\Properties\Resources\Properties\RelationManagers\SpacesRelationManager;
use App\Filament\Clusters\Properties\Resources\Spaces\Pages\EditSpace;
use App\Filament\Clusters\Properties\Resources\Spaces\Pages\ViewSpace;
use App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers\AllocationsRelationManager;
use App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers\SeatsRelationManager;
use App\Filament\Clusters\Properties\Resources\Spaces\SpaceResource;
use App\Filament\Clusters\Properties\Resources\SpaceTypes\SpaceTypeResource;
use App\Models\Floor;
use App\Models\Product;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 10:00:00');
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->operations = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('lets operations staff set up a location in one session: floors, a run of cabins and a desk zone, with today\'s availability (FR-INV-1)', function () {
    actingAs($this->operations);
    $building = $this->property->primaryBuilding->id;

    Livewire::test(FloorsRelationManager::class, ['ownerRecord' => $this->property, 'pageClass' => EditProperty::class])
        ->assertSee('No floors recorded')
        ->callAction(TestAction::make('addFloor')->table(), ['building_level' => 0, 'label' => 'Ground floor', 'reason' => 'Floor list confirmed by the owner'])
        ->callAction(TestAction::make('addFloor')->table(), ['building_level' => 1, 'label' => 'First floor', 'reason' => 'Floor list confirmed by the owner'])
        ->callAction(TestAction::make('addFloor')->table(), ['building_level' => 2, 'label' => 'Second floor', 'reason' => 'Floor list confirmed by the owner'])
        ->assertHasNoActionErrors()
        ->assertSee(['Ground floor', 'First floor', 'Second floor']);

    $first = Floor::query()->where('building_level', 1)->sole();
    $cabinType = SpaceType::query()->where('key', 'private_cabin')->sole();
    $deskType = SpaceType::query()->where('key', 'desk_zone_dedicated')->sole();
    $cabins = offerForTest($this->property, 'private_office');

    Livewire::test(SpacesRelationManager::class, ['ownerRecord' => $this->property->fresh(), 'pageClass' => EditProperty::class])
        ->callAction(TestAction::make('addSpaces')->table(), [
            'floor_id' => $first->id, 'space_type_id' => $cabinType->id, 'inventory_mode' => 'exclusive', 'count' => 12, 'code_prefix' => 'A70-C',
            'first_number' => 1, 'name_prefix' => 'Cabin', 'capacity_seats' => 4, 'offering_ids' => [$cabins->id], 'reason' => 'Inventory from the floor plan',
        ])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('addSpace')->table(), [
            'floor_id' => $first->id, 'space_type_id' => $deskType->id, 'inventory_mode' => 'per_seat', 'code' => 'A70-D1', 'capacity_seats' => 20,
            'offering_ids' => [], 'reason' => 'Inventory from the floor plan',
        ])
        ->assertHasNoActionErrors()
        ->assertSee(['A70-C01', 'Cabin 1', 'Free', 'Private offices and cabins'])
        ->searchTable('A70-D1')
        ->assertSee('20 of 20 seats free');

    expect(Space::query()->count())->toBe(13)
        ->and(Space::query()->where('code', 'A70-D1')->sole()->seats()->count())->toBe(20)
        ->and($building)->toBe($first->building_id);
});

it('shows a refusal with its reason and keeps the form open', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $cabin = Space::factory()->onFloor($floor)->create(['code' => 'A70-C01']);
    app(RecordAllocation::class)->handle($this->operations, $cabin, ['agreement_reference' => 'AG-1', 'starts_on' => '2027-02-01', 'reason' => 'Signed']);
    actingAs($this->operations);

    Livewire::test(ViewSpace::class, ['record' => $cabin->getRouteKey()])
        ->callAction(TestAction::make('changeStatus'), ['status' => 'maintenance', 'reason' => 'Repainting'])
        ->assertNotified('Not saved')
        ->assertActionMounted(TestAction::make('changeStatus'));

    expect($cabin->fresh()->operational_status)->toBe(SpaceStatus::Active);
});

it('records, gives notice on and cancels an allocation from the space\'s page', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $zone = Space::factory()->onFloor($floor)->withSeats(3, InventoryMode::Hybrid)->create(['code' => 'A70-H1']);
    actingAs($this->operations);

    $manager = Livewire::test(AllocationsRelationManager::class, ['ownerRecord' => $zone, 'pageClass' => ViewSpace::class])
        ->assertSee('Nothing allocated')
        ->callAction(TestAction::make('recordAllocation')->table(), ['agreement_reference' => 'AG-2027-014', 'space_seat_id' => $zone->seats[0]->id, 'starts_on' => '2027-03-01', 'reason' => 'Agreement signed'])
        ->assertHasNoActionErrors()
        ->assertNotified('Allocation recorded')
        ->callAction(TestAction::make('recordAllocation')->table(), ['agreement_reference' => 'AG-2027-015', 'seats_allocated' => 3, 'starts_on' => '2027-03-01', 'reason' => 'Agreement signed'])
        ->assertNotified('Not saved')
        ->call('unmountAction')
        ->callAction(TestAction::make('recordAllocation')->table(), ['agreement_reference' => 'AG-2027-015', 'seats_allocated' => 2, 'starts_on' => '2027-03-01', 'reason' => 'Agreement signed'])
        ->assertSee(['AG-2027-014', 'Seat 1', 'AG-2027-015', '2 seats', 'Open-ended']);

    $pool = SpaceAllocation::query()->where('agreement_reference', 'AG-2027-015')->sole();
    $manager->callAction(TestAction::make('notice')->table($pool), ['notice_given_on' => '2027-03-01', 'ends_on' => '2027-05-31', 'reason' => 'Notice letter received'])
        ->callAction(TestAction::make('cancel')->table(SpaceAllocation::query()->where('agreement_reference', 'AG-2027-014')->sole()), ['reason' => 'Recorded against the wrong seat'])
        ->assertSee(['Notice given', '31 May 2027', 'Cancelled']);

    Livewire::test(SeatsRelationManager::class, ['ownerRecord' => $zone->fresh(), 'pageClass' => ViewSpace::class])
        ->assertSee(['Free'])
        ->callAction(TestAction::make('deactivate')->table($zone->seats[2]), ['reason' => 'Desk removed'])
        ->assertNotified('Seat taken out of use')
        ->callAction(TestAction::make('deactivate')->table($zone->seats[1]), ['reason' => 'Desk removed'])
        ->assertNotified('Not saved');

    expect($zone->fresh()->capacity_seats)->toBe(2);
});

it('lets a space be edited only by staff who manage inventory for its property, and keeps a zone\'s seats on its own page', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $zone = Space::factory()->onFloor($floor)->withSeats(4)->create(['code' => 'A70-D1', 'space_type_id' => SpaceType::query()->where('key', 'desk_zone_dedicated')->value('id')]);
    $marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();

    actingAs($marketing)->get(SpaceResource::getUrl('view', ['record' => $zone]))->assertOk()->assertSee('4 of 4 seats free')->assertDontSee('Allocations');
    actingAs($marketing)->get(SpaceResource::getUrl('edit', ['record' => $zone]))->assertForbidden();

    actingAs($this->operations);
    Livewire::test(EditSpace::class, ['record' => $zone->getRouteKey()])
        ->assertFormFieldHidden('capacity_seats')
        ->fillForm(['name' => 'North desks', 'reason' => 'Name agreed'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($zone->fresh()->name)->toBe('North desks')->and($zone->fresh()->capacity_seats)->toBe(4);
});

it('limits a Property Manager to their own properties\' spaces', function () {
    $other = Property::query()->where('code', 'A-71')->sole();
    $own = Space::factory()->onFloor(Floor::factory()->forProperty($this->property)->create(['building_level' => 1]))->create(['code' => 'A70-C01']);
    $notOwn = Space::factory()->onFloor(Floor::factory()->forProperty($other)->create(['building_level' => 1]))->create(['code' => 'A71-C01']);
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);

    actingAs($manager)->get(SpaceResource::getUrl('index'))->assertOk()->assertSee('A70-C01')->assertDontSee('A71-C01');
    actingAs($manager)->get(SpaceResource::getUrl('view', ['record' => $notOwn]))->assertNotFound();
    actingAs($manager)->get(SpaceResource::getUrl('edit', ['record' => $own]))->assertOk();
});

it('lets categories and space types say how their spaces are counted, and keeps the ways spaces already use', function () {
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $desks = Space::factory()->onFloor($floor)->withSeats(2)->create(['code' => 'A70-D1']);
    $desks->offerings()->sync([offerForTest($this->property, 'dedicated_desk')->id]);
    $product = Product::query()->where('key', 'dedicated_desk')->sole();
    actingAs($this->operations);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormSet(['allowed_inventory_modes' => ['per_seat', 'hybrid']])
        ->fillForm(['allowed_inventory_modes' => ['hybrid']])
        ->call('save')
        ->assertHasFormErrors(['allowed_inventory_modes']);

    get(SpaceTypeResource::getUrl('index'))->assertOk()->assertSee(['Private cabin or office', 'Dedicated desk zone']);
});

it('keeps on each category the space types it can be sold with, audited, and keeps the types its spaces use (OD-153)', function () {
    $type = fn (string $key): int => SpaceType::query()->where('key', $key)->value('id');
    $floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $desks = Space::factory()->onFloor($floor)->withSeats(2)->create(['code' => 'A70-D1', 'space_type_id' => $type('desk_zone_dedicated')]);
    $desks->offerings()->sync([offerForTest($this->property, 'dedicated_desk')->id]);
    $product = Product::query()->where('key', 'dedicated_desk')->sole();
    actingAs($this->operations);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormSet(['space_type_ids' => [$type('desk_zone_dedicated'), $type('desk_zone_flexible')]])
        ->fillForm(['space_type_ids' => [$type('desk_zone_flexible')]])
        ->call('save')
        ->assertHasFormErrors(['space_type_ids'])
        ->fillForm(['space_type_ids' => [$type('desk_zone_dedicated'), $type('desk_zone_flexible'), $type('office_suite')]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->spaceTypes()->orderBy('key')->pluck('key')->all())->toBe(['desk_zone_dedicated', 'desk_zone_flexible', 'office_suite'])
        ->and(Activity::query()->where('event', 'space_types_changed')->sole())
        ->causer_id->toBe($this->operations->id)
        ->properties->toArray()->toBe(['old' => ['desk_zone_dedicated', 'desk_zone_flexible'], 'attributes' => ['desk_zone_dedicated', 'desk_zone_flexible', 'office_suite']]);
    get(SpaceTypeResource::getUrl('index'))->assertOk()->assertSee('Can be sold as')->assertSee('Dedicated desks, Hot desks');
});

it('keeps the property pages and categories working on a database without the inventory tables, such as the owner\'s (OD-120)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, ['floors', 'spaces', 'space_types', 'space_seats', 'offering_space', 'space_allocations'], true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $column !== 'allowed_inventory_modes' && $schema->hasColumn($table, $column));
    Once::flush();
    $inventoryQueries = [];
    DB::listen(function ($query) use (&$inventoryQueries): void {
        if (preg_match('/`(floors|spaces|space_types|space_seats|offering_space|space_allocations)`|allowed_inventory_modes/', $query->sql) === 1) {
            $inventoryQueries[] = $query->sql;
        }
    });
    actingAs($this->operations);

    get(PropertyResource::getUrl('edit', ['record' => $this->property]))->assertOk()->assertDontSee('No floors recorded');
    get(SpaceResource::getUrl('index'))->assertForbidden();
    get(SpaceTypeResource::getUrl('index'))->assertForbidden();
    Livewire::test(EditProduct::class, ['record' => Product::query()->where('key', 'hot_desk')->value('id')])->assertFormFieldHidden('allowed_inventory_modes');

    expect($inventoryQueries)->toBe([]);
});
