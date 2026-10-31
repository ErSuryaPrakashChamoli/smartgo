<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Inventory\ChangeAllocation;
use App\Actions\Inventory\RecordAllocation;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\CustomerAvailability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Floor;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\SpaceAvailability;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 10:00:00');
    app(SyncAccessControl::class)->handle();
    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->floor = Floor::factory()->forProperty(Property::factory()->create())->create(['building_level' => 1, 'label' => 'First floor']);
    $this->availability = app(SpaceAvailability::class);
});

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * @param  array<string, mixed>  $data
 */
function allocateForTest(Space $space, User $user, array $data): void
{
    app(RecordAllocation::class)->handle($user, $space, ['agreement_reference' => 'AG-'.fake()->unique()->numberBetween(1, 9999), 'reason' => 'Signed agreement', ...$data]);
}

function refusalForTest(Closure $attempt): ?string
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return collect($exception->errors())->flatten()->first();
    }

    return null;
}

it('shows a whole space as available now when free, from the day after its allocation ends within the horizon, and otherwise the waitlist (BR-AV-2)', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create(['is_publicly_listable' => true, 'capacity_seats' => 4]);

    expect($this->availability->on($cabin)->customerState)->toBe(CustomerAvailability::AvailableNow);

    allocateForTest($cabin, $this->operations, ['starts_on' => '2027-02-01', 'ends_on' => '2027-04-30']);
    $occupied = $this->availability->on($cabin);

    expect($occupied->committed)->toBe(4)
        ->and($occupied->customerState)->toBe(CustomerAvailability::AvailableFrom)
        ->and($occupied->freeFrom?->toDateString())->toBe('2027-05-01')
        ->and($occupied->summary(wholeSpace: true))->toBe('Occupied; free from 1 May 2027')
        ->and($this->availability->on($cabin, CarbonImmutable::parse('2027-05-01'))->customerState)->toBe(CustomerAvailability::AvailableNow);

    $suite = Space::factory()->onFloor($this->floor)->create(['is_publicly_listable' => true]);
    allocateForTest($suite, $this->operations, ['starts_on' => '2027-01-01', 'ends_on' => '2027-12-31']);
    $openEnded = Space::factory()->onFloor($this->floor)->create(['is_publicly_listable' => true]);
    allocateForTest($openEnded, $this->operations, ['starts_on' => '2027-01-01']);

    expect($this->availability->on($suite)->customerState)->toBe(CustomerAvailability::JoinWaitlist)
        ->and($this->availability->on($openEnded)->customerState)->toBe(CustomerAvailability::JoinWaitlist)
        ->and($this->availability->on($openEnded)->freeFrom)->toBeNull();
});

it('refuses a second allocation of a whole space for any overlapping day, and allows the next day', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create(['code' => 'A70-C01']);
    allocateForTest($cabin, $this->operations, ['agreement_reference' => 'AG-1', 'starts_on' => '2027-03-01', 'ends_on' => '2027-06-30']);

    expect(refusalForTest(fn () => allocateForTest($cabin, $this->operations, ['starts_on' => '2027-06-30'])))
        ->toBe('The space is already allocated for some of these dates: AG-1 (whole space, 1 Mar 2027 to 30 Jun 2027).')
        ->and(refusalForTest(fn () => allocateForTest($cabin, $this->operations, ['starts_on' => '2027-07-01'])))->toBeNull();
});

it('counts a pool\'s free seats as its capacity less the seats allocated, on every day of a new allocation (BR-AV-3)', function () {
    $pool = Space::factory()->onFloor($this->floor)->pooled(10)->create(['is_publicly_listable' => true]);
    allocateForTest($pool, $this->operations, ['seats_allocated' => 6, 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);
    allocateForTest($pool, $this->operations, ['seats_allocated' => 3, 'starts_on' => '2027-04-01']);

    expect($this->availability->on($pool)->free())->toBe(4)
        ->and($this->availability->on($pool)->summary(wholeSpace: false))->toBe('4 of 10 seats free')
        ->and(refusalForTest(fn () => allocateForTest($pool, $this->operations, ['seats_allocated' => 5, 'starts_on' => '2027-03-15'])))
        ->toStartWith('Only 4 of 10 seats are free on every day of these dates, so 5 cannot be allocated.')
        ->and(refusalForTest(fn () => allocateForTest($pool, $this->operations, ['seats_allocated' => 8, 'starts_on' => '2027-04-15'])))
        ->toStartWith('Only 7 of 10 seats')
        ->and(refusalForTest(fn () => allocateForTest($pool, $this->operations, ['seats_allocated' => 4, 'starts_on' => '2027-03-15'])))->toBeNull();

    $full = $this->availability->on($pool, CarbonImmutable::parse('2027-03-15'));

    expect($full->free())->toBe(0)
        ->and($full->customerState)->toBe(CustomerAvailability::AvailableFrom)
        ->and($full->freeFrom?->toDateString())->toBe('2027-04-01')
        ->and($this->availability->on($pool)->customerState)->toBe(CustomerAvailability::AvailableNow);
});

it('refuses a seat in a zone whose seats are not assigned individually, even one left from an earlier way of counting', function () {
    $pool = Space::factory()->onFloor($this->floor)->withSeats(2)->create();
    $pool->forceFill(['inventory_mode' => InventoryMode::Pooled])->save();

    expect(refusalForTest(fn () => allocateForTest($pool, $this->operations, ['space_seat_id' => $pool->seats[0]->id, 'starts_on' => '2027-03-01'])))
        ->toBe('This zone\'s seats are not assigned individually: give a number of seats instead.');
});

it('tracks each seat of a per-seat zone and shows the zone available while one seat is free (BR-AV-3a)', function () {
    $zone = Space::factory()->onFloor($this->floor)->withSeats(2)->create(['is_publicly_listable' => true]);
    [$first, $second] = $zone->seats->all();
    allocateForTest($zone, $this->operations, ['space_seat_id' => $first->id, 'starts_on' => '2027-03-01']);

    expect($this->availability->on($zone)->freeSeats)->toBe(['2'])
        ->and($this->availability->on($zone)->customerState)->toBe(CustomerAvailability::AvailableNow)
        ->and(refusalForTest(fn () => allocateForTest($zone, $this->operations, ['space_seat_id' => $first->id, 'starts_on' => '2027-05-01'])))->toStartWith('Seat 1 is already allocated for some of these dates')
        ->and(refusalForTest(fn () => allocateForTest($zone, $this->operations, ['seats_allocated' => 1, 'starts_on' => '2027-05-01'])))->toBe('Choose the seat: in this zone each seat is allocated on its own.');

    allocateForTest($zone, $this->operations, ['space_seat_id' => $second->id, 'starts_on' => '2027-03-01']);

    expect($this->availability->on($zone)->freeSeats)->toBe([])
        ->and($this->availability->on($zone)->customerState)->toBe(CustomerAvailability::JoinWaitlist);
});

it('keeps individually committed seats plus pooled seats within a hybrid zone\'s active seats (BR-AV-3d)', function () {
    $zone = Space::factory()->onFloor($this->floor)->withSeats(4, InventoryMode::Hybrid)->create(['is_publicly_listable' => true]);
    [$first, $second] = $zone->seats->all();
    allocateForTest($zone, $this->operations, ['space_seat_id' => $first->id, 'starts_on' => '2027-03-01']);
    allocateForTest($zone, $this->operations, ['seats_allocated' => 2, 'starts_on' => '2027-03-01']);

    expect($this->availability->on($zone)->free())->toBe(1)
        ->and(refusalForTest(fn () => allocateForTest($zone, $this->operations, ['seats_allocated' => 2, 'starts_on' => '2027-03-10'])))->toStartWith('Only 1 of 4 seats is free')
        ->and(refusalForTest(fn () => allocateForTest($zone, $this->operations, ['space_seat_id' => $first->id, 'starts_on' => '2027-03-10'])))->toStartWith('Seat 1 is already allocated');

    allocateForTest($zone, $this->operations, ['space_seat_id' => $second->id, 'starts_on' => '2027-03-10']);

    expect(refusalForTest(fn () => allocateForTest($zone, $this->operations, ['space_seat_id' => $zone->seats[2]->id, 'starts_on' => '2027-03-10'])))->toStartWith('Only 0 of 4 seats are free')
        ->and($this->availability->on($zone, CarbonImmutable::parse('2027-03-10'))->free())->toBe(0);
});

it('never allocates or shows a space that is not active, and hides one staff have not made listable (BR-AV-4, BR-AV-1)', function () {
    $room = Space::factory()->onFloor($this->floor)->create(['operational_status' => SpaceStatus::Maintenance, 'is_publicly_listable' => true]);
    $unlisted = Space::factory()->onFloor($this->floor)->create(['is_publicly_listable' => false]);

    expect(refusalForTest(fn () => allocateForTest($room, $this->operations, ['starts_on' => '2027-03-01'])))->toBe('This space is under maintenance, so nothing can be allocated in it (BR-AV-4).')
        ->and($this->availability->on($room)->usable())->toBeFalse()
        ->and($this->availability->on($room)->customerState)->toBe(CustomerAvailability::Hidden)
        ->and($this->availability->on($unlisted)->customerState)->toBe(CustomerAvailability::Hidden)
        ->and($this->availability->on($unlisted)->free())->toBe(4);
});

it('gives a space sold as several categories one availability (BR-AV-3c)', function () {
    $this->seed(ReferenceDataSeeder::class);
    $property = $this->floor->property;
    $room = Space::factory()->onFloor($this->floor)->create();
    $room->offerings()->sync([offerForTest($property, 'meeting_room')->id, offerForTest($property, 'conference_room')->id]);
    allocateForTest($room, $this->operations, ['starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);

    expect($this->availability->on($room)->free())->toBe(0)
        ->and($room->offerings()->count())->toBe(2);
});

it('shows "Limited availability" only once a threshold is set, and only for shared zones (BR-AV-1)', function () {
    $pool = Space::factory()->onFloor($this->floor)->pooled(10)->create(['is_publicly_listable' => true]);
    allocateForTest($pool, $this->operations, ['seats_allocated' => 8, 'starts_on' => '2027-03-01']);

    expect($this->availability->on($pool)->customerState)->toBe(CustomerAvailability::AvailableNow);

    config(['inventory.limited_availability_below' => 3]);

    expect($this->availability->on($pool)->customerState)->toBe(CustomerAvailability::Limited);
});

it('frees a space at once when an allocation is cancelled or its end date brought forward, and checks a later end date like a new allocation', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create(['is_publicly_listable' => true]);
    allocateForTest($cabin, $this->operations, ['agreement_reference' => 'AG-1', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-31']);
    allocateForTest($cabin, $this->operations, ['agreement_reference' => 'AG-2', 'starts_on' => '2027-04-01', 'ends_on' => '2027-04-30']);
    [$first, $second] = $cabin->allocations()->orderBy('starts_on')->get()->all();

    expect(refusalForTest(fn () => app(ChangeAllocation::class)->handle($this->operations, $first, 'end_date', ['ends_on' => '2027-04-10', 'reason' => 'Extension requested'])))
        ->toBe('The space is already allocated for some of these dates: AG-2 (whole space, 1 Apr 2027 to 30 Apr 2027).');

    app(ChangeAllocation::class)->handle($this->operations, $second, 'cancel', ['reason' => 'Agreement not signed']);
    app(ChangeAllocation::class)->handle($this->operations, $first, 'end_date', ['ends_on' => '2027-04-10', 'reason' => 'Extension agreed']);
    app(ChangeAllocation::class)->handle($this->operations, $first->fresh(), 'notice', ['notice_given_on' => '2027-03-01', 'ends_on' => '2027-03-15', 'reason' => 'Notice received']);

    expect($this->availability->on($cabin)->freeFrom?->toDateString())->toBe('2027-03-16')
        ->and($first->fresh()->statusOn(CarbonImmutable::today())->label())->toBe('Notice given')
        ->and($second->fresh()->statusOn(CarbonImmutable::today())->label())->toBe('Cancelled');
});
