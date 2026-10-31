<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\PublishScheduledRevisions;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Inventory\AddSpaces;
use App\Actions\Inventory\ChangeSeats;
use App\Actions\Inventory\ChangeSpaceStatus;
use App\Actions\Inventory\RecordAllocation;
use App\Actions\Inventory\RemoveFloor;
use App\Actions\Inventory\RemoveSpace;
use App\Actions\Inventory\SaveFloor;
use App\Actions\Inventory\SaveSpace;
use App\Actions\Properties\SetCategorySpaceTypes;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Filament\Clusters\Properties\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Properties\Resources\SpaceTypes\Pages\ListSpaceTypes;
use App\Models\Floor;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\SpaceType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 10:00:00');
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->floor = app(SaveFloor::class)->handle($this->operations, $this->property, null, [
        'building_id' => $this->property->primaryBuilding->id, 'building_level' => 1, 'label' => 'First floor', 'reason' => 'Floor list from operations',
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function spaceDataForTest(Floor $floor, string $typeKey, string $mode, array $data = []): array
{
    return [
        'floor_id' => $floor->id,
        'space_type_id' => SpaceType::query()->where('key', $typeKey)->sole()->id,
        'inventory_mode' => $mode,
        'capacity_seats' => 4,
        'is_publicly_listable' => false,
        'offering_ids' => [],
        'reason' => 'Inventory entered from the floor plan',
        ...$data,
    ];
}

function errorForTest(Closure $attempt): ?string
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return collect($exception->errors())->flatten()->first();
    }

    return null;
}

it('adds a space with the reason audited, and a per-seat zone with one seat per place', function () {
    Event::fake([SpaceAvailabilityChanged::class]);
    $desks = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'desk_zone_dedicated', 'per_seat', [
        'code' => 'A70-D1', 'capacity_seats' => 3, 'offering_ids' => [offerForTest($this->property, 'dedicated_desk')->id],
    ]));

    expect($desks->seats->pluck('label')->all())->toBe(['1', '2', '3'])
        ->and($desks->capacity_seats)->toBe(3)
        ->and($desks->offerings->pluck('product.key')->all())->toBe(['dedicated_desk'])
        ->and(Activity::query()->where('event', 'space_added')->sole()->properties['reason'])->toBe('Inventory entered from the floor plan')
        ->and(Activity::query()->where('event', 'floor_added')->sole()->properties['reason'])->toBe('Floor list from operations');
    Event::assertDispatched(SpaceAvailabilityChanged::class);
});

it('refuses a space sold as a category its counting does not suit, or counted in a way its type does not allow (BR-PRODCFG-2)', function () {
    $desks = offerForTest($this->property, 'dedicated_desk');

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C1', 'offering_ids' => [$desks->id]]))))
        ->toBe('A space counted as "whole space" cannot be sold as Dedicated desks (BR-PRODCFG-2).')
        ->and(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'meeting_room', 'pooled', ['code' => 'A70-M1']))))
        ->toBe('A meeting room cannot be counted as "shared pool of seats".')
        ->and(Space::query()->count())->toBe(0);
});

it('sells a space only as categories that accept its space type, as kept on each category (BR-PRODCFG-2, OD-153)', function () {
    $meetingRooms = offerForTest($this->property, 'meeting_room');
    $privateOffices = offerForTest($this->property, 'private_office');
    $meetingRoom = fn (): Space => app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'meeting_room', 'exclusive', ['code' => 'A70-M01', 'offering_ids' => [$meetingRooms->id, $privateOffices->id]]));

    expect(errorForTest($meetingRoom))->toBe('A meeting room cannot be sold as Private offices and cabins: that category does not accept this space type (BR-PRODCFG-2).')
        ->and(Space::query()->where('code', 'A70-M01')->exists())->toBeFalse()
        ->and(fn () => app(SetCategorySpaceTypes::class)->handle(User::factory()->withRole(StaffRole::PropertyManager)->create(), $privateOffices->product, []))->toThrow(AuthorizationException::class);

    app(SetCategorySpaceTypes::class)->handle($this->operations, $privateOffices->product, [...$privateOffices->product->spaceTypes()->pluck('space_types.id')->all(), SpaceType::query()->where('key', 'meeting_room')->value('id')]);

    expect($meetingRoom()->offerings->pluck('id')->sort()->values()->all())->toBe(collect([$meetingRooms->id, $privateOffices->id])->sort()->values()->all())
        ->and(Activity::query()->where('event', 'space_types_changed')->sole()->properties->all())->toMatchArray([
            'old' => ['office_suite', 'private_cabin'], 'attributes' => ['meeting_room', 'office_suite', 'private_cabin'],
        ]);
});

it('applies the space-type rule when a space is edited, to a run of spaces, and when a category\'s types change (BR-PRODCFG-2, OD-155)', function () {
    $meetingRooms = offerForTest($this->property, 'meeting_room');
    $managedOffices = offerForTest($this->property, 'managed_office');
    $refusal = 'A meeting room cannot be sold as Managed offices: that category does not accept this space type (BR-PRODCFG-2).';
    $room = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'meeting_room', 'exclusive', ['code' => 'A70-M01', 'offering_ids' => [$meetingRooms->id]]));

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, $room, spaceDataForTest($this->floor, 'meeting_room', 'exclusive', ['offering_ids' => [$meetingRooms->id, $managedOffices->id]]))))->toBe($refusal)
        ->and($room->fresh()->offerings->pluck('id')->all())->toBe([$meetingRooms->id])
        ->and(errorForTest(fn () => app(AddSpaces::class)->handle($this->operations, $this->property, [...spaceDataForTest($this->floor, 'meeting_room', 'exclusive', ['offering_ids' => [$managedOffices->id]]), 'count' => 3, 'code_prefix' => 'A70-R', 'first_number' => 1])))->toBe($refusal)
        ->and(Space::query()->where('code', 'like', 'A70-R%')->exists())->toBeFalse()
        ->and(errorForTest(fn () => app(SetCategorySpaceTypes::class)->handle($this->operations, $meetingRooms->product, [SpaceType::query()->where('key', 'conference_training_room')->value('id')])))
        ->toBe('These spaces are sold as this category and are of a type no longer ticked; change them first: A70-M01.')
        ->and($meetingRooms->product->spaceTypes()->orderBy('key')->pluck('key')->all())->toBe(['conference_training_room', 'meeting_room']);
});

it('keeps spaces and categories as they were on a database without the space-type list, such as the owner\'s today (OD-153)', function () {
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'product_space_type' && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $listQueries = [];
    DB::listen(function ($query) use (&$listQueries): void {
        if (str_contains($query->sql, 'product_space_type')) {
            $listQueries[] = $query->sql;
        }
    });
    $privateOffices = offerForTest($this->property, 'private_office');

    $space = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'meeting_room', 'exclusive', ['code' => 'A70-M01', 'offering_ids' => [$privateOffices->id]]));
    actingAs($this->operations);
    Livewire::test(EditProduct::class, ['record' => $privateOffices->product->getRouteKey()])->assertOk()->assertFormFieldHidden('space_type_ids');
    Livewire::test(ListSpaceTypes::class)->assertOk()->assertSee('Meeting room')->assertTableColumnHidden('sold_as');

    expect($space->offerings->pluck('id')->all())->toBe([$privateOffices->id])
        ->and($listQueries)->toBe([]);
});

it('keeps codes permanent: never changed, never reused after a removal (BR-INV-2)', function () {
    $cabin = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C1']));

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, $cabin, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C9']))))
        ->toBe('A space\'s code is permanent and cannot be changed.');

    app(RemoveSpace::class)->handle($this->operations, $cabin, 'Entered twice by mistake');

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C1']))))
        ->toBe('This code is already used by another space, now or before. Codes are permanent.')
        ->and(Activity::query()->where('event', 'space_removed')->sole()->properties['reason'])->toBe('Entered twice by mistake');
});

it('never removes a space that has been allocated: it is decommissioned instead', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create();
    SpaceAllocation::factory()->create(['space_id' => $cabin->id, 'status' => 'cancelled']);

    expect(errorForTest(fn () => app(RemoveSpace::class)->handle($this->operations, $cabin, 'Duplicate')))
        ->toBe('This space has been allocated, so it is kept: decommission it instead (BR-INV-2).')
        ->and($cabin->fresh()->trashed())->toBeFalse();
});

it('changes how a space is counted only while nothing is allocated now or later (BR-INV-3)', function () {
    $pool = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'desk_zone_flexible', 'pooled', ['code' => 'A70-F1', 'capacity_seats' => 10]));
    app(RecordAllocation::class)->handle($this->operations, $pool, ['agreement_reference' => 'AG-7', 'seats_allocated' => 2, 'starts_on' => '2027-04-01', 'reason' => 'Signed']);

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, $pool, spaceDataForTest($this->floor, 'desk_zone_flexible', 'per_seat', ['capacity_seats' => 10]))))
        ->toBe('The way this space is counted can change only when nothing is allocated in it now or later (BR-INV-3). Allocated: AG-7 (2 seats, 1 Apr 2027 to open-ended).')
        ->and($pool->fresh()->inventory_mode)->toBe(InventoryMode::Pooled);
});

it('refuses a lower capacity, a status out of use or a seat taken out while allocations need them, listing them (BR-INV-4)', function () {
    $pool = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'desk_zone_flexible', 'pooled', ['code' => 'A70-F1', 'capacity_seats' => 10]));
    app(RecordAllocation::class)->handle($this->operations, $pool, ['agreement_reference' => 'AG-8', 'seats_allocated' => 8, 'starts_on' => '2027-03-01', 'ends_on' => '2027-06-30', 'reason' => 'Signed']);
    $zone = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'desk_zone_dedicated', 'hybrid', ['code' => 'A70-H1', 'capacity_seats' => 3]));
    app(RecordAllocation::class)->handle($this->operations, $zone, ['agreement_reference' => 'AG-9', 'space_seat_id' => $zone->seats[0]->id, 'starts_on' => '2027-03-01', 'reason' => 'Signed']);
    app(RecordAllocation::class)->handle($this->operations, $zone, ['agreement_reference' => 'AG-10', 'seats_allocated' => 2, 'starts_on' => '2027-03-01', 'reason' => 'Signed']);

    expect(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, $pool, spaceDataForTest($this->floor, 'desk_zone_flexible', 'pooled', ['capacity_seats' => 7]))))
        ->toBe('With 7 seats these allocations would no longer fit; move or end them first (BR-INV-4): AG-8 (8 seats, 1 Mar 2027 to 30 Jun 2027).')
        ->and(errorForTest(fn () => app(ChangeSpaceStatus::class)->handle($this->operations, $pool, SpaceStatus::Maintenance, SpaceStatus::Active, 'Repainting')))
        ->toBe('This space has allocations now or later; move, end or cancel them first (BR-INV-4): AG-8 (8 seats, 1 Mar 2027 to 30 Jun 2027).')
        ->and(errorForTest(fn () => app(ChangeSeats::class)->handle($this->operations, $zone, 'deactivate', ['seat_id' => $zone->seats[0]->id, 'reason' => 'Broken chair'])))
        ->toStartWith('Seat 1 cannot be taken out of use while these allocations need it')
        ->and(errorForTest(fn () => app(ChangeSeats::class)->handle($this->operations, $zone, 'deactivate', ['seat_id' => $zone->seats[2]->id, 'reason' => 'Broken chair'])))
        ->toBe('Seat 3 cannot be taken out of use while these allocations need it; move or end them first (BR-INV-4): AG-9 (seat 1, 1 Mar 2027 to open-ended); AG-10 (2 seats, 1 Mar 2027 to open-ended).')
        ->and(errorForTest(fn () => app(SaveSpace::class)->handle($this->operations, $this->property, $pool, spaceDataForTest($this->floor, 'desk_zone_flexible', 'pooled', ['capacity_seats' => 8]))))->toBeNull()
        ->and($pool->fresh()->capacity_seats)->toBe(8);
});

it('changes a status with its reason, never over someone else\'s change', function () {
    Event::fake([SpaceAvailabilityChanged::class]);
    $room = Space::factory()->onFloor($this->floor)->create();
    app(ChangeSpaceStatus::class)->handle($this->operations, $room, SpaceStatus::Maintenance, SpaceStatus::Active, 'Air conditioning repair');

    $entry = Activity::query()->where('event', 'space_status_changed')->sole();

    expect($room->fresh()->operational_status)->toBe(SpaceStatus::Maintenance)
        ->and($entry->properties->only(['reason', 'old', 'attributes'])->all())->toEqual(['reason' => 'Air conditioning repair', 'old' => ['operational_status' => 'active'], 'attributes' => ['operational_status' => 'maintenance']])
        ->and($entry->causer_id)->toBe($this->operations->id)
        ->and(errorForTest(fn () => app(ChangeSpaceStatus::class)->handle($this->operations, $room, SpaceStatus::OutOfService, SpaceStatus::Active, 'Flooded')))
        ->toBe('Someone else changed this space\'s status. Reload it and try again.')
        ->and(errorForTest(fn () => app(ChangeSpaceStatus::class)->handle($this->operations, $room, SpaceStatus::Active, SpaceStatus::Maintenance, '')))
        ->toBe('Give the reason for the change in plain text, up to 300 characters.');
    Event::assertDispatched(SpaceAvailabilityChanged::class, fn (SpaceAvailabilityChanged $event): bool => $event->space->is($room) && $event->change === 'status');
});

it('adds seats after the highest number and keeps the zone\'s capacity equal to its seats in use', function () {
    $zone = app(SaveSpace::class)->handle($this->operations, $this->property, null, spaceDataForTest($this->floor, 'desk_zone_dedicated', 'per_seat', ['code' => 'A70-D1', 'capacity_seats' => 2]));
    $zone = app(ChangeSeats::class)->handle($this->operations, $zone, 'add', ['count' => 2, 'reason' => 'Two desks added']);
    $zone = app(ChangeSeats::class)->handle($this->operations, $zone, 'deactivate', ['seat_id' => $zone->seats[1]->id, 'reason' => 'Desk removed']);

    expect($zone->seats->pluck('label')->all())->toBe(['1', '2', '3', '4'])
        ->and($zone->seats->where('is_active', true)->pluck('label')->values()->all())->toBe(['1', '3', '4'])
        ->and($zone->capacity_seats)->toBe(3);
});

it('adds a run of spaces in one step, or none when one code is taken', function () {
    $made = app(AddSpaces::class)->handle($this->operations, $this->property, [
        ...spaceDataForTest($this->floor, 'private_cabin', 'exclusive'), 'count' => 12, 'code_prefix' => 'A70-C', 'first_number' => 1, 'name_prefix' => 'Cabin',
    ]);

    expect($made->pluck('code')->first())->toBe('A70-C01')
        ->and($made->pluck('code')->last())->toBe('A70-C12')
        ->and($made->last()->name)->toBe('Cabin 12')
        ->and(errorForTest(fn () => app(AddSpaces::class)->handle($this->operations, $this->property, [
            ...spaceDataForTest($this->floor, 'private_cabin', 'exclusive'), 'count' => 3, 'code_prefix' => 'A70-C', 'first_number' => 12,
        ])))->toBe('These codes are already used, now or before, and codes are permanent: A70-C12.')
        ->and(Space::query()->count())->toBe(12);
});

it('lets operations add floors, a Property Manager only correct their own, and keeps a floor with spaces', function () {
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    $building = $this->property->primaryBuilding->id;

    expect(fn () => app(SaveFloor::class)->handle($manager, $this->property, null, ['building_id' => $building, 'building_level' => 2, 'label' => 'Second', 'reason' => 'x']))
        ->toThrow(AuthorizationException::class, 'Only operations staff can add floors.')
        ->and(app(SaveFloor::class)->handle($manager, $this->property, $this->floor, ['building_id' => $building, 'building_level' => 1, 'label' => 'First floor (east)', 'reason' => 'Label corrected'])->label)->toBe('First floor (east)')
        ->and(errorForTest(fn () => app(SaveFloor::class)->handle($this->operations, $this->property, null, ['building_id' => $building, 'building_level' => 1, 'label' => 'Duplicate', 'reason' => 'x'])))
        ->toBe('This building already has a floor at this level.');

    Space::factory()->onFloor($this->floor)->create();

    expect(errorForTest(fn () => app(RemoveFloor::class)->handle($this->operations, $this->floor, 'Wrong level')))->toBe('This floor has spaces, so it cannot be removed.');
});

it('limits inventory and allocations to the roles and properties of the approved matrix', function () {
    $space = Space::factory()->onFloor($this->floor)->create();
    $other = Property::query()->where('code', 'A-71')->sole();
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($other);
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['has_all_property_access' => true]);
    $finance = User::factory()->withRole(StaffRole::Finance)->create();
    $allocate = fn (User $user) => app(RecordAllocation::class)->handle($user, $space, ['agreement_reference' => 'AG-1', 'starts_on' => '2027-03-01', 'reason' => 'x']);

    expect(fn () => $allocate($marketing))->toThrow(AuthorizationException::class)
        ->and(fn () => $allocate($sales))->toThrow(AuthorizationException::class)
        ->and(fn () => $allocate($manager))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeSpaceStatus::class)->handle($marketing, $space, SpaceStatus::Maintenance, SpaceStatus::Active, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveSpace::class)->handle($manager, $this->property, $space, spaceDataForTest($this->floor, 'private_cabin', 'exclusive')))->toThrow(AuthorizationException::class)
        ->and($sales->can('viewAny', SpaceAllocation::class))->toBeTrue()
        ->and($finance->can('viewAny', SpaceAllocation::class))->toBeTrue()
        ->and($marketing->can('viewAny', SpaceAllocation::class))->toBeFalse()
        ->and($marketing->can('view', $space))->toBeTrue()
        ->and($manager->can('view', $space))->toBeFalse()
        ->and(SpaceAllocation::query()->count())->toBe(0);

    $manager->assignedProperties()->attach($this->property);
    $allocate($manager->fresh());

    expect(SpaceAllocation::query()->sole()->created_by)->toBe($manager->id);
});

it('refuses spaces and floors to a deactivated account, whatever its role, even when the action is called directly (OD-153)', function () {
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    $this->operations->update(['is_active' => false]);
    $manager->update(['is_active' => false]);

    expect(fn () => app(SaveSpace::class)->handle($this->operations->fresh(), $this->property, null, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C01'])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveSpace::class)->handle($manager->fresh(), $this->property, null, spaceDataForTest($this->floor, 'private_cabin', 'exclusive', ['code' => 'A70-C01'])))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SaveFloor::class)->handle($this->operations->fresh(), $this->property, null, ['building_id' => $this->property->primaryBuilding->id, 'building_level' => 2, 'label' => 'Second floor', 'reason' => 'Floor list']))->toThrow(AuthorizationException::class)
        ->and(Space::query()->exists())->toBeFalse();
});

it('applies the floor rule (BR-PROP-3) only at publication and only once switched on, never taking a published property off the website', function () {
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $other = Property::query()->where('code', 'A-71')->sole();
    offerForTest($other, 'meeting_room', published: false);
    $publishProfile = fn (Property $property) => app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(
        app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($property->profile, $marketing, ['headline' => 'H', 'summary' => 'S']), $marketing), $approver), $publisher);

    expect($other->inventoryPublicationGaps())->toBe(['Record at least one floor this property operates.']);

    $publishProfile($other);

    expect($other->fresh()->isPubliclyListed())->toBeTrue();

    config(['inventory.enforce_floor_rule_on_publication' => true]);
    $draft = app(CreateDraft::class)->handle($other->profile->fresh(), $marketing, ['headline' => 'H2', 'summary' => 'S2']);

    expect(errorForTest(fn () => app(SubmitRevision::class)->handle($draft, $marketing)))->toBe('Record at least one floor this property operates.')
        ->and($other->fresh()->isPubliclyListed())->toBeTrue();

    offerForTest($this->property, 'meeting_room', published: false);
    $publishProfile($this->property);

    expect($this->property->fresh()->isPubliclyListed())->toBeTrue();
});

it('keeps the floor-rule transition safe: a refused scheduled version leaves the live one on the website, and switching off is the whole rollback', function () {
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $other = Property::query()->where('code', 'A-71')->sole();
    offerForTest($other, 'meeting_room', published: false);
    $approved = fn (string $headline) => app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(
        app(CreateDraft::class)->handle($other->profile->fresh(), $marketing, ['headline' => $headline, 'summary' => 'S']), $marketing), $approver);
    app(PublishRevision::class)->handle($approved('Live headline'), $publisher);
    $scheduled = app(ScheduleRevision::class)->handle($approved('Next headline'), $publisher, now()->addHour());

    config(['inventory.enforce_floor_rule_on_publication' => true]);
    CarbonImmutable::setTestNow('2027-03-01 11:30:00');

    expect(app(PublishScheduledRevisions::class)->handle())->toBe(0)
        ->and($scheduled->fresh()->state)->toBe(RevisionState::Approved)
        ->and($scheduled->fresh()->scheduled_for)->toBeNull()
        ->and($other->fresh()->isPubliclyListed())->toBeTrue()
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->sole()->properties['reason'])->toContain('Record at least one floor');
    $this->get($other->fresh()->path())->assertOk()->assertSee('Live headline')->assertDontSee('Next headline');

    config(['inventory.enforce_floor_rule_on_publication' => false]);
    app(PublishRevision::class)->handle($scheduled->fresh(), $publisher);

    $this->get($other->fresh()->path())->assertOk()->assertSee('Next headline');
});

it('reports, without changing anything, which properties the floor rule would refuse', function () {
    $marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $other = Property::query()->where('code', 'A-71')->sole();
    offerForTest($other, 'meeting_room', published: false);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(
        app(CreateDraft::class)->handle($other->profile, $marketing, ['headline' => 'H', 'summary' => 'S']), $marketing), $approver), $publisher);
    $changes = Activity::query()->count();

    $this->artisan('inventory:floor-rule-readiness')
        ->expectsOutputToContain('Record at least one floor this property operates.')
        ->expectsOutputToContain('1 of 1 property(ies) are not ready')
        ->assertFailed();

    app(SaveFloor::class)->handle($this->operations, $other, null, [
        'building_id' => $other->primaryBuilding->id, 'building_level' => 0, 'label' => 'Ground floor', 'reason' => 'Floor list from operations',
    ]);

    $this->artisan('inventory:floor-rule-readiness')->expectsOutputToContain('All 1 property(ies) are ready.')->assertSuccessful();
    expect(Activity::query()->count())->toBe($changes + 1);

    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => $table !== 'floors' && $schema->hasTable($table));
    Once::flush();

    $this->artisan('inventory:floor-rule-readiness')->expectsOutputToContain('cannot be checked here')->assertFailed();
});
