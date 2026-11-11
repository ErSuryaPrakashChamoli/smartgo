<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\CreatePipelineStage;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\EditPipelineStage;
use App\Filament\Clusters\Sales\Resources\PipelineStages\Pages\ListPipelineStages;
use App\Filament\Clusters\Sales\Resources\PipelineStages\PipelineStageResource;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
});

it('starts empty, saying what is missing: nothing is preset (D-09)', function () {
    actingAs($this->admin);

    expect(PipelineStage::query()->count())->toBe(0);

    Livewire::test(ListPipelineStages::class)
        ->assertSee('No pipeline stages yet')
        ->assertSee('Until an open stage exists no deal can be opened');
});

it('lets the Super Admin add a stage through the form, audited, with a unique name and a probability up to 100 (test values only)', function () {
    actingAs($this->admin);

    Livewire::test(CreatePipelineStage::class)
        ->fillForm(['name' => 'Test stage A', 'sort_order' => 10, 'outcome' => PipelineOutcome::Open->value, 'probability_pct' => 40, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $stage = PipelineStage::query()->sole();

    expect($stage->only(['name', 'sort_order', 'probability_pct', 'is_active']))->toBe(['name' => 'Test stage A', 'sort_order' => 10, 'probability_pct' => 40, 'is_active' => true])
        ->and($stage->outcome)->toBe(PipelineOutcome::Open)
        ->and(Activity::query()->inLog('leads')->whereMorphedTo('subject', $stage)->where('event', 'created')->exists())->toBeTrue();

    Livewire::test(CreatePipelineStage::class)
        ->fillForm(['name' => 'Test stage A', 'sort_order' => 1, 'outcome' => PipelineOutcome::Won->value, 'probability_pct' => 101])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique', 'probability_pct' => 'max']);

    Livewire::test(CreatePipelineStage::class)
        ->fillForm(['name' => 'Test stage B', 'sort_order' => 2, 'outcome' => PipelineOutcome::Won->value, 'probability_pct' => null])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PipelineStage::query()->where('name', 'Test stage B')->sole()->probability_pct)->toBeNull();
});

it('fixes a stage\'s outcome once a deal uses it, but still lets it be renamed and switched off', function () {
    $stage = PipelineStage::factory()->create(['name' => 'Test stage A']);
    Deal::factory()->inStage($stage)->create();
    actingAs($this->admin);

    Livewire::test(EditPipelineStage::class, ['record' => $stage->getRouteKey()])
        ->assertFormFieldDisabled('outcome')
        ->assertSee('Deals already use this stage')
        ->fillForm(['name' => 'Test stage renamed', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($stage->fresh()->only(['name', 'is_active']))->toBe(['name' => 'Test stage renamed', 'is_active' => false])
        ->and($stage->fresh()->outcome)->toBe(PipelineOutcome::Open);
});

it('refuses the stage screens to everyone but the Super Admin, by direct address too', function (StaffRole $role) {
    $stage = PipelineStage::factory()->create();
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create());

    get(PipelineStageResource::getUrl('index'))->assertForbidden();
    get(PipelineStageResource::getUrl('create'))->assertForbidden();
    get(PipelineStageResource::getUrl('edit', ['record' => $stage]))->assertForbidden();
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::MarketingManager, StaffRole::Finance]);

it('opens the stage screens for the Super Admin', function () {
    $stage = PipelineStage::factory()->create();
    actingAs($this->admin);

    get(PipelineStageResource::getUrl('index'))->assertOk();
    get(PipelineStageResource::getUrl('edit', ['record' => $stage]))->assertOk();
});
