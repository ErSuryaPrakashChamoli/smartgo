<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\ProposeDealSpace;
use App\Enums\Access\StaffRole;
use App\Models\Deal;
use App\Models\DealSpace;
use App\Models\Floor;
use App\Models\Lead;
use App\Models\PipelineStage;
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

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->floor = Floor::factory()->forProperty($this->property)->create(['building_level' => 1]);
    $this->first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->first->assignedProperties()->attach($this->property);
    $this->second->assignedProperties()->attach($this->property);
    // Test stages only (D-09, A-42).
    $this->open = PipelineStage::factory()->create();
    $this->won = PipelineStage::factory()->won()->create();
});

afterEach(function () {
    InventoryHarness::reset();
});

/**
 * An open deal on a new enquiry about A-70 with the given whole space proposed.
 */
function dealProposing(Space $space): Deal
{
    $deal = Deal::factory()->forLead(Lead::factory()->forProperty(test()->property)->create())->inStage(test()->open)->create();
    app(ProposeDealSpace::class)->handle(test()->first, $deal, ['space_id' => $space->id]);

    return $deal;
}

/**
 * @return array{deal_id: int, win: array<string, mixed>}
 */
function winData(Deal $deal, string $reference): array
{
    return ['deal_id' => $deal->id, 'win' => ['pipeline_stage_id' => test()->won->id, 'agreement_reference' => $reference, 'starts_on' => today()->toDateString()]];
}

it('lets exactly one of two deals win the same free space at the same instant; the other allocates nothing and stays open (BR-CRM-7)', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create();
    $one = dealProposing($cabin);
    $two = dealProposing($cabin);

    $results = InventoryHarness::runWorkers([
        ['mode' => 'win_deal', 'space_id' => $cabin->id, 'user_id' => $this->first->id, 'data' => winData($one, 'AG-ONE')],
        ['mode' => 'win_deal', 'space_id' => $cabin->id, 'user_id' => $this->second->id, 'data' => winData($two, 'AG-TWO')],
    ]);

    $outcomes = collect($results)->pluck('outcome')->sort()->values()->all();
    $winner = SpaceAllocation::query()->sole();
    $loser = $winner->agreement_reference === 'AG-ONE' ? $two : $one;

    expect($outcomes)->toContain('refused')
        ->and(collect($results)->firstWhere('outcome', 'refused')['message'])->toContain('already allocated')
        ->and(Deal::query()->whereNotNull('won_at')->count())->toBe(1)
        ->and($loser->fresh()->isOpen())->toBeTrue()
        ->and(DealSpace::query()->where('deal_id', $loser->id)->whereNotNull('space_allocation_id')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'deal_won')->count())->toBe(1);
})->group('mysql', 'concurrency');

it('wins the same deal once when two people submit it at the same instant', function () {
    $cabin = Space::factory()->onFloor($this->floor)->create();
    $pool = Space::factory()->onFloor($this->floor)->pooled(10)->create();
    $deal = dealProposing($cabin);
    app(ProposeDealSpace::class)->handle($this->first, $deal, ['space_id' => $pool->id, 'seats' => 3]);

    $results = InventoryHarness::runWorkers([
        ['mode' => 'win_deal', 'space_id' => $cabin->id, 'user_id' => $this->first->id, 'data' => winData($deal, 'AG-FIRST')],
        ['mode' => 'win_deal', 'space_id' => $cabin->id, 'user_id' => $this->second->id, 'data' => winData($deal, 'AG-SECOND')],
    ]);

    expect(collect($results)->pluck('outcome')->filter(fn (string $outcome): bool => $outcome === 'refused')->count())->toBe(1)
        ->and(collect($results)->firstWhere('outcome', 'refused')['message'])->toBe('This deal is already won.')
        ->and(SpaceAllocation::query()->count())->toBe(2)
        ->and(SpaceAllocation::query()->distinct()->pluck('agreement_reference')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'deal_won')->count())->toBe(1);
})->group('mysql', 'concurrency');

it('lets exactly one of a won deal and a direct allocation take the last seats of a pool at the same instant', function () {
    $pool = Space::factory()->onFloor($this->floor)->pooled(4)->create();
    $deal = Deal::factory()->forLead(Lead::factory()->forProperty($this->property)->create())->inStage($this->open)->create();
    app(ProposeDealSpace::class)->handle($this->first, $deal, ['space_id' => $pool->id, 'seats' => 3]);
    $operations = User::factory()->withRole(StaffRole::OperationsManager)->create();

    $results = InventoryHarness::runWorkers([
        ['mode' => 'win_deal', 'space_id' => $pool->id, 'user_id' => $this->first->id, 'data' => winData($deal, 'AG-DEAL')],
        ['mode' => 'allocate', 'space_id' => $pool->id, 'user_id' => $operations->id, 'data' => ['agreement_reference' => 'AG-DIRECT', 'seats_allocated' => 2, 'starts_on' => today()->toDateString(), 'reason' => 'Race test']],
    ]);

    expect(collect($results)->pluck('outcome')->filter(fn (string $outcome): bool => $outcome === 'refused')->count())->toBe(1)
        ->and(SpaceAllocation::query()->count())->toBe(1)
        ->and((int) SpaceAllocation::query()->sum('seats_allocated'))->toBeLessThanOrEqual(4)
        ->and($deal->fresh()->isOpen())->toBe(SpaceAllocation::query()->sole()->agreement_reference === 'AG-DIRECT');
})->group('mysql', 'concurrency');
