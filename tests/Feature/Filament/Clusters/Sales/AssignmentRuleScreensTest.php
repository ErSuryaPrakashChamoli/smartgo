<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\AssignNewLead;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentStrategy;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\CreateAssignmentRule;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\EditAssignmentRule;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\ListAssignmentRules;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\ViewAssignmentRule;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\AssignmentRule;
use App\Models\City;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Ravi Sales']);
    $this->sales->assignedProperties()->attach($this->property);
});

it('shows the rules to the Super Admin only, and an empty list says enquiries stay unassigned', function () {
    actingAs($this->admin)->get(AssignmentRuleResource::getUrl('index'))
        ->assertOk()
        ->assertSee('No assignment rules')
        ->assertSee('every new enquiry stays in the unassigned queue');

    foreach ([StaffRole::SalesCrmExecutive, StaffRole::OperationsManager, StaffRole::MarketingManager] as $role) {
        actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
            ->get(AssignmentRuleResource::getUrl('index'))->assertForbidden();
    }

    actingAs($this->sales)->get(AssignmentRuleResource::getUrl('create'))->assertForbidden();
});

it('creates a rule from the form, switched off, with its criteria and people', function () {
    $undoRepeaterFake = Repeater::fake();
    actingAs($this->admin);

    Livewire::test(CreateAssignmentRule::class)
        ->fillForm([
            'name' => 'Noida desks',
            'priority' => 5,
            'strategy' => AssignmentStrategy::RoundRobin->value,
            'criteria' => ['city_ids' => [City::query()->where('slug', 'noida')->value('id')], 'team_sizes' => ['2-5']],
            'assignees' => [['user_id' => $this->sales->id]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();
    $rule = AssignmentRule::query()->sole();

    expect($rule->only(['name', 'priority', 'is_active']))->toBe(['name' => 'Noida desks', 'priority' => 5, 'is_active' => false])
        ->and($rule->assignees()->pluck('users.id')->all())->toBe([$this->sales->id]);

    Livewire::test(ListAssignmentRules::class)
        ->assertCanSeeTableRecords([$rule])
        ->assertSee(['Cities: Noida', 'Team sizes: 2 to 5 people', 'Ravi Sales', 'Off']);
});

it('shows the action\'s refusal on the field it concerns', function () {
    AssignmentRule::factory()->create(['priority' => 5]);
    actingAs($this->admin);

    Livewire::test(CreateAssignmentRule::class)
        ->fillForm(['name' => 'Second rule', 'priority' => 5, 'strategy' => AssignmentStrategy::FixedUser->value])
        ->call('create')
        ->assertHasFormErrors(['priority']);

    expect(AssignmentRule::query()->count())->toBe(1);
});

it('switches a rule on and off from its page, and opens an active rule there instead of its form', function () {
    $rule = AssignmentRule::factory()->withAssignees([$this->sales])->create(['name' => 'Noida desks']);
    actingAs($this->admin);

    Livewire::test(ViewAssignmentRule::class, ['record' => $rule->getRouteKey()])
        ->assertActionHidden('switchOff')
        ->callAction('switchOn')
        ->assertNotified('Rule switched on')
        ->assertActionHidden('edit');

    expect($rule->fresh()->is_active)->toBeTrue();

    Livewire::test(EditAssignmentRule::class, ['record' => $rule->getRouteKey()])
        ->assertRedirect(AssignmentRuleResource::getUrl('view', ['record' => $rule]));

    Livewire::test(ViewAssignmentRule::class, ['record' => $rule->getRouteKey()])
        ->callAction('switchOff')
        ->assertNotified('Rule switched off');

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('explains on the rule page why it cannot be switched on', function () {
    $rule = AssignmentRule::factory()->create(['strategy' => AssignmentStrategy::RoundRobin]);
    actingAs($this->admin);

    Livewire::test(ViewAssignmentRule::class, ['record' => $rule->getRouteKey()])
        ->callAction('switchOn')
        ->assertNotified('Not switched on');

    expect($rule->fresh()->is_active)->toBeFalse();
});

it('tells staff on the enquiry why it has, or has not, an owner from the rules', function () {
    AssignmentRule::factory()->active()->withAssignees([$this->sales])->create(['name' => 'Noida desks']);
    $assigned = Lead::factory()->forProperty($this->property)->create();
    app(AssignNewLead::class)->handle($assigned);
    $before = Lead::factory()->forProperty($this->property)->create();

    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $assigned]))
        ->assertOk()
        ->assertSee('Given to Ravi Sales by the rule &quot;Noida desks&quot; (round-robin).', false);

    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $before]))
        ->assertOk()
        ->assertSee('The rules have not run for this enquiry');
});

it('lists the rule\'s recent decisions with links to the enquiries', function () {
    $rule = AssignmentRule::factory()->active()->withAssignees([$this->sales])->create();
    $lead = Lead::factory()->forProperty($this->property)->create();
    app(AssignNewLead::class)->handle($lead);

    actingAs($this->admin)->get(AssignmentRuleResource::getUrl('view', ['record' => $rule]))
        ->assertOk()
        ->assertSee($lead->reference)
        ->assertSee(LeadResource::getUrl('view', ['record' => $lead]), false)
        ->assertSee('Assigned by a rule')
        ->assertSee('Ravi Sales');
});
