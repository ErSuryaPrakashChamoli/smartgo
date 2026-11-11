<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\ProposeDealSpace;
use App\Actions\Inventory\RecordAllocation;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\Deals\DealResource;
use App\Filament\Clusters\Sales\Resources\Deals\Pages\ListDeals;
use App\Filament\Clusters\Sales\Resources\Deals\Pages\ViewDeal;
use App\Filament\Clusters\Sales\Resources\Deals\RelationManagers\ProposedSpacesRelationManager;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Filament\Clusters\Sales\Resources\Leads\RelationManagers\DealsRelationManager;
use App\Filament\Clusters\Sales\Resources\PipelineStages\PipelineStageResource;
use App\Models\Deal;
use App\Models\Floor;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->lead = Lead::factory()->forProperty($this->property)->create();
    $this->cabin = Space::factory()->onFloor(Floor::factory()->forProperty($this->property)->create(['building_level' => 1]))->create(['name' => 'Cabin 1']);
});

/**
 * Test stages only: the owner has not decided the stages (D-09, A-42).
 *
 * @return array{open: PipelineStage, next: PipelineStage, won: PipelineStage, lost: PipelineStage}
 */
function testStages(): array
{
    return [
        'open' => PipelineStage::factory()->create(['name' => 'Test open', 'sort_order' => 1, 'probability_pct' => 20]),
        'next' => PipelineStage::factory()->create(['name' => 'Test next', 'sort_order' => 2]),
        'won' => PipelineStage::factory()->won()->create(['name' => 'Test won']),
        'lost' => PipelineStage::factory()->lost()->create(['name' => 'Test lost']),
    ];
}

it('explains on the enquiry page that no deal can be opened until the Super Admin sets up stages', function () {
    actingAs($this->sales);

    Livewire::test(DealsRelationManager::class, ['ownerRecord' => $this->lead, 'pageClass' => ViewLead::class])
        ->assertSee('No open pipeline stages are set up yet')
        ->assertActionHidden(TestAction::make('openDeal')->table());

    get(DealResource::getUrl('index'))->assertOk()->assertSee('No open pipeline stages are set up yet');
});

it('opens a deal from the enquiry page, once, and goes to it', function () {
    $stages = testStages();
    actingAs($this->sales);

    Livewire::test(DealsRelationManager::class, ['ownerRecord' => $this->lead, 'pageClass' => ViewLead::class])
        ->callAction(TestAction::make('openDeal')->table(), ['pipeline_stage_id' => $stages['open']->id, 'seats' => 8, 'monthly_value' => '96000'])
        ->assertHasNoActionErrors()
        ->assertNotified('Deal opened')
        ->assertRedirect(DealResource::getUrl('view', ['record' => Deal::query()->sole()]));

    expect(Deal::query()->sole()->only(['lead_id', 'seats', 'monthly_value_minor', 'owner_user_id']))->toBe(['lead_id' => $this->lead->id, 'seats' => 8, 'monthly_value_minor' => 9600000, 'owner_user_id' => $this->sales->id]);

    Livewire::test(DealsRelationManager::class, ['ownerRecord' => $this->lead, 'pageClass' => ViewLead::class])
        ->assertSee('Deal '.Deal::query()->sole()->id)
        ->assertActionHidden(TestAction::make('openDeal')->table());

});

it('proposes a space with its availability today, wins the deal and shows the allocation and the history', function () {
    $stages = testStages();
    $deal = Deal::factory()->forLead($this->lead)->inStage($stages['open'])->create();
    actingAs($this->sales);

    Livewire::test(ProposedSpacesRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => ViewDeal::class])
        ->assertSee('No spaces proposed')
        ->callAction(TestAction::make('propose')->table(), ['space_id' => $this->cabin->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Space proposed')
        ->assertSee([$this->cabin->displayName(), 'The whole space (4 seats)', 'Free', 'Not allocated']);

    Livewire::test(ViewDeal::class, ['record' => $deal->getRouteKey()])
        ->assertSee(['Test open (20%)', $this->lead->reference, 'Proposed spaces are not reserved'])
        ->callAction('win', ['pipeline_stage_id' => $stages['won']->id, 'agreement_reference' => 'AG-2027-014', 'starts_on' => today()->toDateString()])
        ->assertHasNoActionErrors()
        ->assertNotified('Deal won')
        ->assertActionHidden('win')
        ->assertActionHidden('move')
        ->assertSee(['AG-2027-014', 'Deal won: 1 allocation recorded', 'Allocation recorded, '.$this->cabin->code.': Deal '.$deal->id.' won']);

    Livewire::test(ProposedSpacesRelationManager::class, ['ownerRecord' => $deal->fresh(), 'pageClass' => ViewDeal::class])
        ->assertSee('Occupied')
        ->assertDontSee('Not allocated')
        ->assertActionHidden(TestAction::make('propose')->table());

    expect(SpaceAllocation::query()->sole()->agreement_reference)->toBe('AG-2027-014');
});

it('keeps the deal open and says why when a proposed space was taken in the meantime', function () {
    $stages = testStages();
    $deal = Deal::factory()->forLead($this->lead)->inStage($stages['open'])->create();
    app(ProposeDealSpace::class)->handle($this->sales, $deal, ['space_id' => $this->cabin->id]);
    app(RecordAllocation::class)->handle(User::factory()->withRole(StaffRole::OperationsManager)->create(), $this->cabin, ['agreement_reference' => 'AG-OTHER', 'starts_on' => today()->toDateString(), 'reason' => 'Signed elsewhere']);
    actingAs($this->sales);

    Livewire::test(ViewDeal::class, ['record' => $deal->getRouteKey()])
        ->callAction('win', ['pipeline_stage_id' => $stages['won']->id, 'agreement_reference' => 'AG-2027-014', 'starts_on' => today()->toDateString()])
        ->assertNotified('Not saved');

    expect($deal->fresh()->isOpen())->toBeTrue()
        ->and(SpaceAllocation::query()->pluck('agreement_reference')->all())->toBe(['AG-OTHER']);
});

it('moves a deal, changes its owner and marks it lost from its page', function () {
    $stages = testStages();
    $deal = Deal::factory()->forLead($this->lead)->inStage($stages['open'])->create(['owner_user_id' => $this->sales->id]);
    $colleague = tap(User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Colleague Test']), fn (User $user) => $user->assignedProperties()->attach($this->property));
    actingAs($this->sales);

    Livewire::test(ViewDeal::class, ['record' => $deal->getRouteKey()])
        ->callAction('move', ['pipeline_stage_id' => $stages['next']->id])
        ->assertNotified('Deal moved')
        ->callAction('assign', ['owner' => $colleague->id])
        ->assertNotified('Owner updated')
        ->callAction('lose', ['pipeline_stage_id' => $stages['lost']->id, 'reason' => 'Chose another provider'])
        ->assertNotified('Deal marked lost')
        ->assertSee(['Chose another provider', 'Deal moved from "Test open" to "Test next"', 'Deal assigned to Colleague Test'])
        ->assertActionHidden('lose');

    expect($deal->fresh()->outcome())->toBe(PipelineOutcome::Lost)
        ->and($deal->fresh()->owner_user_id)->toBe($colleague->id);
});

it('lists only deals within the user\'s scope, and refuses the rest by direct address', function () {
    $stages = testStages();
    $mine = Deal::factory()->forLead($this->lead)->inStage($stages['open'])->create();
    $elsewhere = Deal::factory()->forLead(Lead::factory()->forProperty(Property::query()->where('code', 'H-105')->sole())->create())->inStage($stages['open'])->create();
    actingAs($this->sales);

    Livewire::test(ListDeals::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$elsewhere]);
    get(DealResource::getUrl('view', ['record' => $mine]))->assertOk();
    get(DealResource::getUrl('view', ['record' => $elsewhere]))->assertNotFound();
});

it('shows a Property Manager their location\'s deals without any way to change them', function () {
    $stages = testStages();
    $deal = Deal::factory()->forLead($this->lead)->inStage($stages['open'])->create();
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    actingAs($manager);

    get(DealResource::getUrl('view', ['record' => $deal]))->assertOk();

    Livewire::test(ViewDeal::class, ['record' => $deal->getRouteKey()])
        ->assertActionHidden('changeDetails')
        ->assertActionHidden('move')
        ->assertActionHidden('assign')
        ->assertActionHidden('win')
        ->assertActionHidden('lose');
    Livewire::test(ProposedSpacesRelationManager::class, ['ownerRecord' => $deal, 'pageClass' => ViewDeal::class])->assertActionHidden(TestAction::make('propose')->table());
    Livewire::test(DealsRelationManager::class, ['ownerRecord' => $this->lead, 'pageClass' => ViewLead::class])->assertActionHidden(TestAction::make('openDeal')->table());
});

it('refuses the deal screens to staff who do not see enquiries', function (StaffRole $role) {
    $deal = Deal::factory()->forLead($this->lead)->create();
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create());

    get(DealResource::getUrl('index'))->assertForbidden();
    get(DealResource::getUrl('view', ['record' => $deal]))->assertNotFound();
})->with([StaffRole::OperationsManager, StaffRole::MarketingManager, StaffRole::Finance]);

it('keeps the back office working on a database without the deal tables: no deal screens and no query to them', function () {
    $admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, Deal::TABLES, true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = new ArrayObject;
    DB::listen(function ($query) use ($queries): void {
        if (preg_match('/`(pipeline_stages|deals|deal_space)`/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });
    actingAs($admin);

    expect(DealResource::canAccess())->toBeFalse()
        ->and(PipelineStageResource::canAccess())->toBeFalse();
    get(DealResource::getUrl('index'))->assertForbidden();
    get(DealResource::getUrl('view', ['record' => 1]))->assertForbidden();
    get(PipelineStageResource::getUrl('index'))->assertForbidden();
    get(PipelineStageResource::getUrl('edit', ['record' => 1]))->assertForbidden();
    get(LeadResource::getUrl('view', ['record' => $this->lead]))->assertOk()->assertSee($this->lead->reference)->assertDontSee('Open a deal');
    get(LeadResource::getUrl('index'))->assertOk()->assertDontSee('Pipeline stages');

    expect($queries->getArrayCopy())->toBe([]);
});
