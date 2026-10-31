<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Inventory\RecordAllocation;
use App\Actions\Pricing\ChangePricePlanState;
use App\Actions\Pricing\SavePricePlan;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\SpaceStatus;
use App\Enums\Pricing\PricePlanStatus;
use App\Models\Floor;
use App\Models\PricePlan;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concurrency\InventoryHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    InventoryHarness::reset();
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);

    $this->floor = Floor::factory()->forProperty(Property::query()->where('code', 'A-70')->sole())->create(['building_level' => 1]);
    $this->first = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->second = User::factory()->withRole(StaffRole::OperationsManager)->create();
});

afterEach(function () {
    InventoryHarness::reset();
});

it('lets exactly one of two people allocate the last free seat of a pool at the same instant (BR-AV-3)', function () {
    $pool = Space::factory()->onFloor($this->floor)->pooled(3)->create();
    app(RecordAllocation::class)->handle($this->first, $pool, ['agreement_reference' => 'AG-0', 'seats_allocated' => 2, 'starts_on' => today()->toDateString(), 'reason' => 'Signed']);

    $results = InventoryHarness::runWorkers([
        ['mode' => 'allocate', 'space_id' => $pool->id, 'user_id' => $this->first->id, 'data' => ['agreement_reference' => 'AG-1', 'seats_allocated' => 1, 'starts_on' => today()->toDateString(), 'reason' => 'Signed']],
        ['mode' => 'allocate', 'space_id' => $pool->id, 'user_id' => $this->second->id, 'data' => ['agreement_reference' => 'AG-2', 'seats_allocated' => 1, 'starts_on' => today()->toDateString(), 'reason' => 'Signed']],
    ]);

    expect(collect($results)->where('outcome', 'refused')->count())->toBe(1)
        ->and(collect($results)->where('outcome', 'refused')->first()['message'])->toStartWith('Only 0 of 3 seats are free on every day of these dates')
        ->and((int) SpaceAllocation::query()->sum('seats_allocated'))->toBe(3);
})->group('mysql', 'concurrency');

it('never allocates a whole space twice at the same instant (BR-AV-2)', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create();

    $results = InventoryHarness::runWorkers([
        ['mode' => 'allocate', 'space_id' => $cabin->id, 'user_id' => $this->first->id, 'data' => ['agreement_reference' => 'AG-1', 'starts_on' => today()->toDateString(), 'reason' => 'Signed']],
        ['mode' => 'allocate', 'space_id' => $cabin->id, 'user_id' => $this->second->id, 'data' => ['agreement_reference' => 'AG-2', 'starts_on' => today()->addMonth()->toDateString(), 'reason' => 'Signed']],
    ]);

    expect(collect($results)->where('outcome', 'refused')->count())->toBe(1)
        ->and(SpaceAllocation::query()->count())->toBe(1);
})->group('mysql', 'concurrency');

it('never takes a space out of use while an allocation of it is being recorded at the same instant (BR-INV-4)', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create();

    $results = InventoryHarness::runWorkers([
        ['mode' => 'allocate', 'space_id' => $cabin->id, 'user_id' => $this->first->id, 'data' => ['agreement_reference' => 'AG-1', 'starts_on' => today()->toDateString(), 'reason' => 'Signed']],
        ['mode' => 'status', 'space_id' => $cabin->id, 'user_id' => $this->second->id, 'data' => ['status' => 'maintenance']],
    ]);

    $allocated = SpaceAllocation::query()->exists();

    expect(collect($results)->where('outcome', 'refused')->count())->toBe(1)
        ->and($allocated && $cabin->fresh()->operational_status === SpaceStatus::Maintenance)->toBeFalse();
})->group('mysql', 'concurrency');

it('never takes a seat out of use while it is being allocated at the same instant (BR-INV-4, BR-AV-3a)', function () {
    $zone = Space::factory()->onFloor($this->floor)->withSeats(2)->create();
    $seat = $zone->seats()->where('label', '1')->sole();

    $results = InventoryHarness::runWorkers([
        ['mode' => 'allocate', 'space_id' => $zone->id, 'user_id' => $this->first->id, 'data' => ['agreement_reference' => 'AG-1', 'space_seat_id' => $seat->id, 'starts_on' => today()->toDateString(), 'reason' => 'Signed']],
        ['mode' => 'deactivate_seat', 'space_id' => $zone->id, 'user_id' => $this->second->id, 'data' => ['seat_id' => $seat->id]],
    ]);

    expect(collect($results)->where('outcome', 'refused')->count())->toBe(1)
        ->and(SpaceAllocation::query()->exists() && ! $seat->fresh()->is_active)->toBeFalse();
})->group('mysql', 'concurrency');

it('activates only one of two overlapping price plans when two approvers act at the same instant (one active plan per scope and unit)', function () {
    $rooms = offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room');
    $author = User::factory()->withRole(StaffRole::Finance)->create();
    $approvers = [User::factory()->withRole(StaffRole::Finance)->create(), User::factory()->withRole(StaffRole::SuperAdmin)->create()];
    $plans = collect(['1500', '1600'])->map(fn (string $amount): PricePlan => app(ChangePricePlanState::class)->handle($author, app(SavePricePlan::class)->handle($author, null, [
        'offering_id' => $rooms->id, 'unit' => 'per_hour', 'amount' => $amount, 'valid_from' => today()->addDay()->toDateString(),
    ]), 'submit'));

    $results = InventoryHarness::runWorkers([
        ['mode' => 'activate_plan', 'space_id' => 0, 'user_id' => $approvers[0]->id, 'data' => ['plan_id' => $plans[0]->id]],
        ['mode' => 'activate_plan', 'space_id' => 0, 'user_id' => $approvers[1]->id, 'data' => ['plan_id' => $plans[1]->id]],
    ]);

    expect(collect($results)->where('outcome', 'refused')->count())->toBe(1)
        ->and(collect($results)->where('outcome', 'refused')->first()['message'])->toStartWith('Another active plan for the same category, space and unit covers some of these dates')
        ->and(PricePlan::query()->where('status', PricePlanStatus::Active->value)->count())->toBe(1);
})->group('mysql', 'concurrency');

it('activates a price plan once when two approvers press Activate at the same instant', function () {
    $rooms = offerForTest(Property::query()->where('code', 'A-70')->sole(), 'meeting_room');
    $author = User::factory()->withRole(StaffRole::Finance)->create();
    $plan = app(ChangePricePlanState::class)->handle($author, app(SavePricePlan::class)->handle($author, null, [
        'offering_id' => $rooms->id, 'unit' => 'per_hour', 'amount' => '1500', 'valid_from' => today()->addDay()->toDateString(),
    ]), 'submit');

    $results = InventoryHarness::runWorkers([
        ['mode' => 'activate_plan', 'space_id' => 0, 'user_id' => User::factory()->withRole(StaffRole::Finance)->create()->id, 'data' => ['plan_id' => $plan->id]],
        ['mode' => 'activate_plan', 'space_id' => 0, 'user_id' => User::factory()->withRole(StaffRole::SuperAdmin)->create()->id, 'data' => ['plan_id' => $plan->id]],
    ]);

    expect(collect($results)->pluck('outcome')->sort()->values()->all())->toBe(['active', 'refused'])
        ->and(Activity::query()->where('event', 'price_plan_activated')->count())->toBe(1);
})->group('mysql', 'concurrency');
