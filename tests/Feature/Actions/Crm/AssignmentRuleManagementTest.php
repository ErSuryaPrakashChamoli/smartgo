<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\SaveAssignmentRule;
use App\Actions\Crm\SetAssignmentRuleActive;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentStrategy;
use App\Enums\Crm\LeadStatus;
use App\Models\AssignmentRule;
use App\Models\City;
use App\Models\Lead;
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

    $this->admin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'First Sales']);
    $this->second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Second Sales']);
    $this->noida = City::query()->where('slug', 'noida')->value('id');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function assignmentRuleData(array $overrides = []): array
{
    return [
        'name' => 'Noida desks',
        'priority' => 10,
        'strategy' => AssignmentStrategy::RoundRobin->value,
        'criteria' => ['city_ids' => [(string) test()->noida], 'team_sizes' => ['2-5']],
        'assignee_ids' => [test()->second->id, test()->first->id],
        ...$overrides,
    ];
}

it('lets the Super Admin create a rule, switched off, with its people in order, and audits what was set', function () {
    $rule = app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData());
    $entry = Activity::query()->where('event', 'assignment_rule_created')->sole();

    expect($rule->is_active)->toBeFalse()
        ->and($rule->criteria)->toBe(['city_ids' => [$this->noida], 'property_ids' => [], 'product_ids' => [], 'segment_ids' => [], 'team_sizes' => ['2-5'], 'sources' => []])
        ->and($rule->assignees()->pluck('users.id')->all())->toBe([$this->second->id, $this->first->id])
        ->and($entry->causer->is($this->admin))->toBeTrue()
        ->and($entry->properties->all())->toEqual(['rule_id' => $rule->id, 'changed' => ['name', 'priority', 'strategy', 'criteria', 'assignee_ids']]);
});

it('refuses everyone else, and a deactivated Super Admin, and saves nothing', function (StaffRole $role, bool $active) {
    $user = User::factory()->withRole($role)->create(['is_active' => $active]);

    expect(fn () => app(SaveAssignmentRule::class)->handle($user, new AssignmentRule, assignmentRuleData()))->toThrow(AuthorizationException::class);

    $rule = AssignmentRule::factory()->withAssignees([$this->first])->create();

    expect(fn () => app(SetAssignmentRuleActive::class)->handle($user, $rule, true))->toThrow(AuthorizationException::class)
        ->and(AssignmentRule::query()->count())->toBe(1)
        ->and($rule->fresh()->is_active)->toBeFalse();
})->with([
    'sales' => [StaffRole::SalesCrmExecutive, true],
    'operations' => [StaffRole::OperationsManager, true],
    'marketing' => [StaffRole::MarketingManager, true],
    'property manager' => [StaffRole::PropertyManager, true],
    'finance' => [StaffRole::Finance, true],
    'deactivated super admin' => [StaffRole::SuperAdmin, false],
]);

it('refuses a rule that would be unclear or give enquiries to the wrong people', function (Closure $data, string $message) {
    AssignmentRule::factory()->create(['priority' => 10]);

    expect(fn () => app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData($data())))
        ->toThrow(ValidationException::class, $message);

    expect(AssignmentRule::query()->count())->toBe(1);
})->with([
    'a priority already used' => [fn () => [], 'Another rule already has this priority'],
    'two people on a fixed-person rule' => [fn () => ['priority' => 11, 'strategy' => AssignmentStrategy::FixedUser->value], 'A fixed-person rule gives enquiries to one person.'],
    'someone who may not work on enquiries' => [fn () => ['priority' => 11, 'assignee_ids' => [User::factory()->withRole(StaffRole::MarketingManager)->create(['name' => 'Mona Marketing'])->id]], 'These people cannot work on enquiries: Mona Marketing.'],
    'a deactivated person' => [fn () => ['priority' => 11, 'assignee_ids' => [User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Gone Sales', 'is_active' => false])->id]], 'These people cannot work on enquiries: Gone Sales.'],
    'an unknown city' => [fn () => ['priority' => 11, 'criteria' => ['city_ids' => [999999]]], 'The selected criteria.city_ids.0 is invalid.'],
    'a team size the form does not offer' => [fn () => ['priority' => 11, 'criteria' => ['team_sizes' => ['7']]], 'The selected criteria.team_sizes.0 is invalid.'],
    'a criterion the enquiry does not record' => [fn () => ['priority' => 11, 'criteria' => ['budget' => ['high']]], 'The criteria field must be an array.'],
]);

it('changes a rule only while it is switched off, and audits only what changed', function () {
    $rule = app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData());
    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true);

    expect(fn () => app(SaveAssignmentRule::class)->handle($this->admin, $rule->fresh(), assignmentRuleData(['name' => 'Renamed'])))
        ->toThrow(ValidationException::class, 'Switch the rule off before changing it');

    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, false);
    app(SaveAssignmentRule::class)->handle($this->admin, $rule->fresh(), assignmentRuleData(['name' => 'Renamed', 'assignee_ids' => [$this->first->id, $this->second->id]]));

    expect($rule->fresh()->name)->toBe('Renamed')
        ->and($rule->assignees()->pluck('users.id')->all())->toBe([$this->first->id, $this->second->id])
        ->and(Activity::query()->where('event', 'assignment_rule_updated')->sole()->properties['changed'])->toBe(['name', 'assignee_ids']);
});

it('switches a rule on only when it can assign, and switching it twice audits once', function () {
    $rule = app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData(['assignee_ids' => []]));

    expect(fn () => app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true))->toThrow(ValidationException::class, 'Add the people this rule gives enquiries to');

    $rule->assignees()->attach($this->first, ['position' => 1]);
    $this->first->forceFill(['is_active' => false])->save();

    expect(fn () => app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true))->toThrow(ValidationException::class, 'These people can no longer work on enquiries: First Sales.');

    $this->first->forceFill(['is_active' => true])->save();
    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true);
    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true);

    expect($rule->fresh()->is_active)->toBeTrue()
        ->and(Activity::query()->where('event', 'assignment_rule_activated')->count())->toBe(1);

    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, false);

    expect($rule->fresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('event', 'assignment_rule_deactivated')->count())->toBe(1);
});

it('keeps a least-loaded rule off until the owner defines workload (A-34)', function () {
    $rule = app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData(['strategy' => AssignmentStrategy::LeastLoaded->value]));

    expect(fn () => app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true))
        ->toThrow(ValidationException::class, 'once the owner has decided which enquiries count as workload (A-34)');

    config(['crm.assignment.workload_statuses' => [LeadStatus::New->value]]);
    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true);

    expect($rule->fresh()->is_active)->toBeTrue();
});

it('needs exactly one person to switch on a fixed-person rule', function () {
    $rule = app(SaveAssignmentRule::class)->handle($this->admin, new AssignmentRule, assignmentRuleData(['strategy' => AssignmentStrategy::FixedUser->value, 'assignee_ids' => []]));

    expect(fn () => app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true))->toThrow(ValidationException::class, 'Choose the one person this rule gives enquiries to');

    app(SaveAssignmentRule::class)->handle($this->admin, $rule->fresh(), assignmentRuleData(['strategy' => AssignmentStrategy::FixedUser->value, 'assignee_ids' => [$this->first->id]]));
    app(SetAssignmentRuleActive::class)->handle($this->admin, $rule, true);

    expect($rule->fresh()->is_active)->toBeTrue()
        ->and(Lead::query()->count())->toBe(0);
});
