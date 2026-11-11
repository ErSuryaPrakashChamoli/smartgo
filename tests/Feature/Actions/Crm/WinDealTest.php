<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\ProposeDealSpace;
use App\Actions\Crm\WinDeal;
use App\Actions\Inventory\AllocateWonDeal;
use App\Actions\Inventory\RecordAllocation;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\PipelineOutcome;
use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Floor;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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
    $this->operations = User::factory()->withRole(StaffRole::OperationsManager)->create();
    $this->floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    // Test stages only: the owner has not decided the stages (D-09, A-42).
    $this->won = PipelineStage::factory()->won()->create(['name' => 'Signed']);
    $this->deal = Deal::factory()->forLead(Lead::factory()->forProperty($this->property)->create())->inStage(PipelineStage::factory()->create())->create();
    $this->cabin = Space::factory()->onFloor($this->floor)->create(['capacity_seats' => 6]);
    $this->desks = Space::factory()->onFloor($this->floor)->withSeats(3)->create();
    $this->pool = Space::factory()->onFloor($this->floor)->pooled(10)->create();
    $this->today = CarbonImmutable::today();
});

/**
 * Proposes the cabin, desk 2 and four pool seats on the deal.
 */
function proposeThreeSpaces(): void
{
    $propose = app(ProposeDealSpace::class);
    $propose->handle(test()->sales, test()->deal, ['space_id' => test()->cabin->id]);
    $propose->handle(test()->sales, test()->deal, ['space_id' => test()->desks->id, 'space_seat_id' => test()->desks->seats->firstWhere('label', '2')->id]);
    $propose->handle(test()->sales, test()->deal, ['space_id' => test()->pool->id, 'seats' => 4]);
}

/**
 * @return array<string, mixed>
 */
function agreementFrom(CarbonImmutable $from, ?CarbonImmutable $until = null): array
{
    return ['pipeline_stage_id' => test()->won->id, 'agreement_reference' => 'AG-2027-014', 'starts_on' => $from->toDateString(), 'ends_on' => $until?->toDateString()];
}

it('wins a deal by allocating every proposed space through the inventory, under one agreement, linked and audited (BR-CRM-7)', function () {
    Event::fake([SpaceAvailabilityChanged::class]);
    proposeThreeSpaces();

    app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today, $this->today->addYear()));

    $deal = $this->deal->fresh();
    $allocations = SpaceAllocation::query()->orderBy('space_id')->get();

    expect($deal->outcome())->toBe(PipelineOutcome::Won)
        ->and($deal->only(['pipeline_stage_id', 'agreement_reference']))->toBe(['pipeline_stage_id' => $this->won->id, 'agreement_reference' => 'AG-2027-014'])
        ->and($allocations->map(fn (SpaceAllocation $allocation): array => [$allocation->space_id, $allocation->space_seat_id, $allocation->seats_allocated, $allocation->status, $allocation->agreement_reference, $allocation->created_by]))->toEqual(collect([
            [$this->cabin->id, null, 6, AllocationStatus::Active, 'AG-2027-014', $this->sales->id],
            [$this->desks->id, $this->desks->seats->firstWhere('label', '2')->id, 1, AllocationStatus::Active, 'AG-2027-014', $this->sales->id],
            [$this->pool->id, null, 4, AllocationStatus::Active, 'AG-2027-014', $this->sales->id],
        ]))
        ->and(DealSpace::query()->orderBy('space_id')->pluck('space_allocation_id')->all())->toBe($allocations->modelKeys())
        ->and(Activity::query()->inLog('inventory')->where('event', 'allocation_added')->get()->map(fn (Activity $entry): array => [$entry->properties['reason'], $entry->properties['deal_id']])->unique()->values()->all())
        ->toBe([['Deal '.$deal->id.' won (enquiry '.$deal->lead->reference.')', $deal->id]])
        ->and(Activity::query()->where('event', 'deal_won')->sole()->properties->all())->toEqual(['deal_id' => $deal->id, 'stage' => 'Signed', 'agreement_reference' => 'AG-2027-014', 'allocations' => 3]);

    Event::assertDispatchedTimes(SpaceAvailabilityChanged::class, 3);
});

it('reserves the spaces when the agreement starts later', function () {
    proposeThreeSpaces();

    app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today->addMonth()));

    expect(SpaceAllocation::query()->get()->map(fn (SpaceAllocation $allocation): string => $allocation->status->value)->unique()->values()->all())->toBe([AllocationStatus::Reserved->value])
        ->and(SpaceAllocation::query()->whereNull('ends_on')->count())->toBe(3);
});

it('lets a Sales/CRM Executive win a deal with spaces, though they still cannot record an allocation directly', function () {
    app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->cabin->id]);

    expect(fn () => app(RecordAllocation::class)->handle($this->sales, $this->cabin, ['agreement_reference' => 'AG-1', 'starts_on' => $this->today->toDateString(), 'reason' => 'Direct']))
        ->toThrow(AuthorizationException::class);

    app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today));

    expect(SpaceAllocation::query()->count())->toBe(1);
});

it('allocates nothing and keeps the deal open when any proposed space is no longer free, naming the space', function () {
    proposeThreeSpaces();
    app(RecordAllocation::class)->handle($this->operations, $this->pool, ['agreement_reference' => 'AG-OTHER', 'seats_allocated' => 8, 'starts_on' => $this->today->toDateString(), 'reason' => 'Signed elsewhere']);

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today)))
        ->toThrow(ValidationException::class, $this->pool->displayName().': Only 2 of 10 seats are free');

    expect($this->deal->fresh()->isOpen())->toBeTrue()
        ->and(SpaceAllocation::query()->pluck('agreement_reference')->all())->toBe(['AG-OTHER'])
        ->and(DealSpace::query()->whereNotNull('space_allocation_id')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'deal_won')->count())->toBe(0)
        ->and(Activity::query()->inLog('inventory')->where('properties->deal_id', $this->deal->id)->count())->toBe(0);
});

it('refuses a proposed space that is no longer in use, or whose seat is no longer in use', function (string $change, string $message) {
    proposeThreeSpaces();
    match ($change) {
        'maintenance' => $this->cabin->forceFill(['operational_status' => SpaceStatus::Maintenance])->save(),
        'seat' => $this->desks->seats->firstWhere('label', '2')->forceFill(['is_active' => false])->save(),
    };

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today)))->toThrow(ValidationException::class, $message)
        ->and(SpaceAllocation::query()->count())->toBe(0);
})->with([
    ['maintenance', 'so nothing can be allocated in it'],
    ['seat', 'Seat 2 is not in use, so it cannot be allocated.'],
]);

it('refuses a proposal whose space has changed mode since', function () {
    app(ProposeDealSpace::class)->handle($this->sales, $this->deal, ['space_id' => $this->pool->id, 'seats' => 2]);
    $this->pool->forceFill(['inventory_mode' => InventoryMode::PerSeat])->save();

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today)))
        ->toThrow(ValidationException::class, 'so this proposal no longer fits it')
        ->and(SpaceAllocation::query()->count())->toBe(0);
});

it('wins a deal once, however often it is submitted', function () {
    proposeThreeSpaces();
    app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today));

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today)))->toThrow(ValidationException::class, 'This deal is already won.')
        ->and(SpaceAllocation::query()->count())->toBe(3)
        ->and(Activity::query()->where('event', 'deal_won')->count())->toBe(1);
});

it('never allocates a proposal twice, even for a deal found open again', function () {
    proposeThreeSpaces();
    app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today));
    $this->deal->forceFill(['won_at' => null])->save();

    expect(fn () => app(AllocateWonDeal::class)->handle($this->sales, $this->deal->fresh(), 'AG-AGAIN', $this->today, null))
        ->toThrow(ValidationException::class, 'This deal\'s spaces are already allocated.')
        ->and(SpaceAllocation::query()->count())->toBe(3);
});

it('needs the agreement only when spaces are proposed, checked as an allocation\'s', function () {
    proposeThreeSpaces();

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, ['pipeline_stage_id' => $this->won->id]))->toThrow(ValidationException::class, 'The agreement reference field is required.')
        ->and(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, [...agreementFrom($this->today), 'agreement_reference' => 'AG 1']))->toThrow(ValidationException::class, 'Use letters, numbers, hyphens, dots and slashes')
        ->and(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, agreementFrom($this->today->subYear(), $this->today->subDay())))->toThrow(ValidationException::class, 'The end date cannot be before the start date or in the past.')
        ->and(SpaceAllocation::query()->count())->toBe(0);

    $bare = Deal::factory()->forLead(Lead::factory()->forProperty($this->property)->create())->inStage($this->deal->stage)->create();
    app(WinDeal::class)->handle($this->sales, $bare, ['pipeline_stage_id' => $this->won->id]);

    expect($bare->fresh()->outcome())->toBe(PipelineOutcome::Won)
        ->and($bare->fresh()->agreement_reference)->toBeNull()
        ->and(SpaceAllocation::query()->count())->toBe(0);
});

it('wins only in an active won stage, and says when none is set up', function () {
    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, ['pipeline_stage_id' => $this->deal->pipeline_stage_id]))->toThrow(ValidationException::class, 'Choose one of the won stages.');

    $this->won->update(['is_active' => false]);

    expect(fn () => app(WinDeal::class)->handle($this->sales, $this->deal, ['pipeline_stage_id' => $this->won->id]))->toThrow(ValidationException::class, 'No won stage is set up yet');
});

it('refuses staff who may not work on the deal, and allocates nothing', function (string $who) {
    proposeThreeSpaces();
    $actor = match ($who) {
        'sales elsewhere' => tap(User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(), fn (User $user) => $user->assignedProperties()->attach(Property::query()->where('code', 'H-105')->sole())),
        'property manager' => tap(User::factory()->withRole(StaffRole::PropertyManager)->create(), fn (User $user) => $user->assignedProperties()->attach($this->property)),
        'operations manager' => $this->operations,
        'deactivated' => tap($this->sales, fn (User $user) => $user->forceFill(['is_active' => false])->save()),
    };

    expect(fn () => app(WinDeal::class)->handle($actor, $this->deal, agreementFrom($this->today)))->toThrow(AuthorizationException::class)
        ->and(SpaceAllocation::query()->count())->toBe(0)
        ->and($this->deal->fresh()->isOpen())->toBeTrue();
})->with(['sales elsewhere', 'property manager', 'operations manager', 'deactivated']);

it('allocates a closed deal\'s spaces for nobody', function () {
    proposeThreeSpaces();
    $this->deal->forceFill(['lost_at' => now(), 'lost_reason' => 'Budget'])->save();

    expect(fn () => app(AllocateWonDeal::class)->handle($this->sales, $this->deal, 'AG-1', $this->today, null))->toThrow(AuthorizationException::class)
        ->and(SpaceAllocation::query()->count())->toBe(0);
});
