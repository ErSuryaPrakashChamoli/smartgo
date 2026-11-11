<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\LoseDeal;
use App\Actions\Crm\ProposeDealSpace;
use App\Actions\Crm\WithdrawDealSpace;
use App\Enums\Access\StaffRole;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Floor;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    // Test stage only: the owner has not decided the stages (D-09, A-42).
    $this->deal = Deal::factory()->forLead(Lead::factory()->forProperty($this->property)->create())->inStage(PipelineStage::factory()->create())->create();
    $this->cabin = Space::factory()->onFloor($this->floor)->create(['capacity_seats' => 6]);
    $this->desks = Space::factory()->onFloor($this->floor)->withSeats(3)->create();
    $this->pool = Space::factory()->onFloor($this->floor)->pooled(10)->create();
});

it('proposes a whole space, one seat of a per-seat zone and a number of seats of a pool, as an allocation would take them, audited', function () {
    $cabin = app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]);
    $seat = app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->desks->id, 'space_seat_id' => $this->desks->seats->firstWhere('label', '2')->id]);
    $pool = app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->pool->id, 'seats' => 4]);

    expect([$cabin->seats, $seat->seats, $pool->seats])->toBe([6, 1, 4])
        ->and([$cabin->portion(), $seat->portion(), $pool->portion()])->toBe(['The whole space (6 seats)', 'Seat 2', '4 seats'])
        ->and(DealSpace::query()->whereNotNull('space_allocation_id')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'deal_space_proposed')->count())->toBe(3)
        ->and(Activity::query()->where('event', 'deal_space_proposed')->first()->properties->all())->toEqual(['deal_id' => $this->deal->id, 'space' => $this->cabin->code, 'seat' => null, 'seats' => 6]);
});

it('takes either a seat or a number of seats in a hybrid zone, never more than its seats in use', function () {
    $hybrid = Space::factory()->onFloor($this->floor)->withSeats(4, InventoryMode::Hybrid)->create();

    expect(app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $hybrid->id, 'space_seat_id' => $hybrid->seats->first()->id])->seats)->toBe(1)
        ->and(app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $hybrid->id, 'seats' => 2])->seats)->toBe(2)
        ->and(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $hybrid->id, 'seats' => 5]))->toThrow(ValidationException::class, 'This zone offers 4 seats.');
});

it('refuses a proposal that does not fit the space', function (string $case, string $message) {
    $other = Space::factory()->onFloor($this->floor)->withSeats(2)->create();
    $data = match ($case) {
        'a seat of a whole space' => ['space_id' => $this->cabin->id, 'space_seat_id' => $other->seats->first()->id],
        'a per-seat zone without a seat' => ['space_id' => $this->desks->id],
        'another zone\'s seat' => ['space_id' => $this->desks->id, 'space_seat_id' => $other->seats->first()->id],
        'a seat out of use' => ['space_id' => $this->desks->id, 'space_seat_id' => tap($this->desks->seats->first(), fn ($seat) => $seat->forceFill(['is_active' => false])->save())->id],
        'a pool without a number' => ['space_id' => $this->pool->id],
        'a seat of a pool' => ['space_id' => $this->pool->id, 'space_seat_id' => $other->seats->first()->id],
        'more seats than the pool offers' => ['space_id' => $this->pool->id, 'seats' => 11],
        'an unknown space' => ['space_id' => 999999],
    };

    expect(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, $data))->toThrow(ValidationException::class, $message)
        ->and(DealSpace::query()->count())->toBe(0);
})->with([
    ['a seat of a whole space', 'Choose one of this zone\'s seats in use.'],
    ['a per-seat zone without a seat', 'Choose the seat: in this zone each seat is proposed on its own.'],
    ['another zone\'s seat', 'Choose one of this zone\'s seats in use.'],
    ['a seat out of use', 'Choose one of this zone\'s seats in use.'],
    ['a pool without a number', 'Enter how many seats are proposed.'],
    ['a seat of a pool', 'Choose one of this zone\'s seats in use.'],
    ['more seats than the pool offers', 'This zone offers 10 seats.'],
    ['an unknown space', 'Choose a space from the list.'],
]);

it('proposes only spaces in use, at the enquiry\'s location', function () {
    $this->cabin->forceFill(['operational_status' => SpaceStatus::Maintenance])->save();
    $elsewhere = Space::factory()->onFloor(Floor::factory()->forProperty(Property::query()->where('code', 'H-105')->sole())->create(['building_level' => 1]))->create();

    expect(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]))->toThrow(ValidationException::class, 'This space is under maintenance, so it cannot be proposed.')
        ->and(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $elsewhere->id]))->toThrow(ValidationException::class, 'The enquiry is about SmartGro '.$this->property->name.', so propose one of its spaces.')
        ->and(DealSpace::query()->count())->toBe(0);
});

it('lets staff who work on every location propose any location\'s space for an enquiry without one', function () {
    $admin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $general = Deal::factory()->forLead(Lead::factory()->create())->inStage($this->deal->stage)->create();
    $elsewhere = Space::factory()->onFloor(Floor::factory()->forProperty(Property::query()->where('code', 'H-105')->sole())->create(['building_level' => 1]))->create();

    expect(app(ProposeDealSpace::class)->handle($admin, $general, ['space_id' => $elsewhere->id])->space_id)->toBe($elsewhere->id)
        ->and(fn () => app(ProposeDealSpace::class)->handle($this->sales, $general, ['space_id' => $this->cabin->id]))->toThrow(AuthorizationException::class);
});

it('proposes the same space or seat once per deal', function () {
    app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]);
    app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->desks->id, 'space_seat_id' => $this->desks->seats->first()->id]);

    expect(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]))->toThrow(ValidationException::class, 'This space is already proposed on the deal.')
        ->and(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->desks->id, 'space_seat_id' => $this->desks->seats->first()->id]))->toThrow(ValidationException::class, 'This seat is already proposed on the deal.')
        ->and(app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->desks->id, 'space_seat_id' => $this->desks->seats->last()->id])->seats)->toBe(1);
});

it('refuses staff who may not work on the deal', function (string $who) {
    $actor = match ($who) {
        'property manager' => tap(User::factory()->withRole(StaffRole::PropertyManager)->create(), fn (User $user) => $user->assignedProperties()->attach($this->property)),
        'deactivated' => tap($this->sales, fn (User $user) => $user->forceFill(['is_active' => false])->save()),
    };

    expect(fn () => app(ProposeDealSpace::class)->handle($actor, $this->deal, ['space_id' => $this->cabin->id]))->toThrow(AuthorizationException::class)
        ->and(DealSpace::query()->count())->toBe(0);
})->with(['property manager', 'deactivated']);

it('withdraws a proposed space from an open deal, audited, and keeps a closed deal\'s proposals', function () {
    $line = app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]);
    $kept = app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->pool->id, 'seats' => 2]);

    app(WithdrawDealSpace::class)->handle($this->sales, $line);
    app(LoseDeal::class)->handle($this->sales, $this->deal, ['pipeline_stage_id' => PipelineStage::factory()->lost()->create()->id, 'reason' => 'Budget']);

    expect(DealSpace::query()->pluck('id')->all())->toBe([$kept->id])
        ->and(Activity::query()->where('event', 'deal_space_withdrawn')->sole()->properties->all())->toEqual(['deal_id' => $this->deal->id, 'space' => $this->cabin->code, 'seat' => null, 'seats' => 6])
        ->and(fn () => app(WithdrawDealSpace::class)->handle($this->sales, $kept))->toThrow(ValidationException::class, 'so its proposed spaces stay on record')
        ->and(fn () => app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]))->toThrow(ValidationException::class, 'so its proposed spaces stay as they were');
});
