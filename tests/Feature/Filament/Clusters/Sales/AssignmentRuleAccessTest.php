<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\SaveAssignmentRule;
use App\Actions\Crm\SetAssignmentRuleActive;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentStrategy;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\AssignmentRuleResource;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\EditAssignmentRule;
use App\Filament\Clusters\Sales\Resources\AssignmentRules\Pages\ViewAssignmentRule;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\AssignmentRule;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
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
    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
    $this->rule = AssignmentRule::factory()->withAssignees([$this->admin])->create(['name' => 'Noida desks']);
    $this->activeRule = AssignmentRule::factory()->active()->withAssignees([$this->admin])->create();
});

/**
 * Staff who may not manage assignment rules: every other role, and a deactivated Super Admin (P9-B evidence closure).
 *
 * @return array<string, array{StaffRole, bool}>
 */
function staffWithoutRuleAccess(): array
{
    return [
        'sales' => [StaffRole::SalesCrmExecutive, true],
        'operations' => [StaffRole::OperationsManager, true],
        'marketing' => [StaffRole::MarketingManager, true],
        'property manager' => [StaffRole::PropertyManager, true],
        'finance' => [StaffRole::Finance, true],
        'deactivated super admin' => [StaffRole::SuperAdmin, false],
    ];
}

it('refuses the rules list, a rule\'s page, its edit form and the new-rule form to anyone but an active Super Admin, even by direct address', function (StaffRole $role, bool $active) {
    $user = User::factory()->withEmailAuthentication()->withRole($role)->create(['is_active' => $active]);
    $user->assignedProperties()->attach($this->property);
    actingAs($user);

    foreach ([
        AssignmentRuleResource::getUrl('index'),
        AssignmentRuleResource::getUrl('create'),
        AssignmentRuleResource::getUrl('view', ['record' => $this->rule]),
        AssignmentRuleResource::getUrl('edit', ['record' => $this->rule]),
    ] as $url) {
        expect(get($url)->getStatusCode())->toBe(403, $url);
    }

    Livewire::test(ViewAssignmentRule::class, ['record' => $this->rule->getRouteKey()])->assertForbidden();
    Livewire::test(EditAssignmentRule::class, ['record' => $this->rule->getRouteKey()])->assertForbidden();
})->with(staffWithoutRuleAccess());

it('refuses changing, switching on and switching off a rule through the actions to anyone but an active Super Admin', function (StaffRole $role, bool $active) {
    $user = User::factory()->withRole($role)->create(['is_active' => $active]);

    expect(fn () => app(SaveAssignmentRule::class)->handle($user, $this->rule, ['name' => 'Renamed', 'priority' => $this->rule->priority, 'strategy' => AssignmentStrategy::RoundRobin->value]))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetAssignmentRuleActive::class)->handle($user, $this->rule, true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(SetAssignmentRuleActive::class)->handle($user, $this->activeRule, false))->toThrow(AuthorizationException::class);

    expect($this->rule->fresh()->only(['name', 'is_active']))->toBe(['name' => 'Noida desks', 'is_active' => false])
        ->and($this->activeRule->fresh()->is_active)->toBeTrue();
})->with(staffWithoutRuleAccess());

it('sends a visitor who is not signed in to the sign-in page', function () {
    foreach (['index', 'create'] as $page) {
        get(AssignmentRuleResource::getUrl($page))->assertRedirect(Filament::getLoginUrl());
    }

    foreach (['view', 'edit'] as $page) {
        get(AssignmentRuleResource::getUrl($page, ['record' => $this->rule]))->assertRedirect(Filament::getLoginUrl());
    }
});

/**
 * Makes the application see a database without the assignment tables (the owner's server, the site), the
 * established way: the real test database keeps them; nothing is dropped. Returns the queries made to them.
 *
 * @return ArrayObject<int, string>
 */
function hideAssignmentTables(): ArrayObject
{
    $schema = Schema::getFacadeRoot();
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, AssignmentRule::TABLES, true) && $schema->hasTable($table));
    Schema::shouldReceive('hasColumn')->andReturnUsing(fn (string $table, string $column): bool => $schema->hasColumn($table, $column));
    Schema::shouldReceive('hasColumns')->andReturnUsing(fn (string $table, array $columns): bool => $schema->hasColumns($table, $columns));
    Once::flush();
    $queries = new ArrayObject;
    DB::listen(function ($query) use ($queries): void {
        if (preg_match('/`(assignment_rules|assignment_rule_user|lead_assignments)`/', $query->sql) === 1) {
            $queries[] = $query->sql;
        }
    });

    return $queries;
}

it('keeps the back office working on a database without the assignment tables: no rules screens, no explanation, no query to them', function () {
    $lead = Lead::factory()->forProperty($this->property)->create();
    $queries = hideAssignmentTables();
    actingAs($this->admin);

    expect(AssignmentRuleResource::canAccess())->toBeFalse();
    get(AssignmentRuleResource::getUrl('index'))->assertForbidden();
    get(AssignmentRuleResource::getUrl('create'))->assertForbidden();
    get(LeadResource::getUrl('view', ['record' => $lead]))
        ->assertOk()
        ->assertSee($lead->reference)
        ->assertDontSee('Assignment rules')
        ->assertDontSee('The rules have not run');
    get(LeadResource::getUrl('index'))->assertOk()->assertDontSee('Assignment rules');

    expect($queries->getArrayCopy())->toBe([]);
});

it('refuses a rule\'s own address on a database without the assignment tables without querying them', function () {
    $queries = hideAssignmentTables();
    actingAs($this->admin);

    get(AssignmentRuleResource::getUrl('view', ['record' => $this->rule]))->assertForbidden();
    get(AssignmentRuleResource::getUrl('edit', ['record' => $this->rule]))->assertForbidden();

    expect($queries->getArrayCopy())->toBe([]);
});
