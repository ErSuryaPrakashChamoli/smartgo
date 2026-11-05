<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\AssignNewLead;
use App\Actions\Crm\SubmitEnquiry;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentOutcome;
use App\Enums\Crm\AssignmentStrategy;
use App\Enums\Crm\LeadStatus;
use App\Models\AssignmentRule;
use App\Models\City;
use App\Models\CustomerSegment;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->otherProperty = Property::query()->where('code', 'H-105')->sole();
    $this->first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'First Sales']);
    $this->second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Second Sales']);
    $this->first->assignedProperties()->attach($this->property);
    $this->second->assignedProperties()->attach($this->property);
});

/**
 * A new, unassigned enquiry about A-70 (the rules have not run for it).
 *
 * @param  array<string, mixed>  $attributes
 */
function newLeadForAssignment(array $attributes = []): Lead
{
    return Lead::factory()->forProperty(test()->property)->create($attributes);
}

it('leaves an enquiry unassigned, as today, when no rule is active', function () {
    AssignmentRule::factory()->withAssignees([$this->first])->create();
    $lead = newLeadForAssignment();

    $decision = app(AssignNewLead::class)->handle($lead);

    expect($lead->fresh()->owner_user_id)->toBeNull()
        ->and($decision->outcome)->toBe(AssignmentOutcome::NoRuleMatched)
        ->and($decision->assignment_rule_id)->toBeNull()
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(0);
});

it('matches each criterion on what the enquiry records, and leaves a non-matching enquiry unassigned', function (string $criterion) {
    $segments = CustomerSegment::query()->orderBy('id')->pluck('id');
    $lead = newLeadForAssignment(['team_size' => '2-5', 'customer_segment_id' => $segments->first(), 'product_id' => Product::query()->where('key', 'hot_desk')->value('id'), 'source' => Lead::SOURCE_WEBSITE_FORM]);
    $nonMatching = newLeadForAssignment([
        'team_size' => '50+', 'customer_segment_id' => $segments->last(), 'product_id' => Product::query()->where('key', 'meeting_room')->value('id'), 'source' => Lead::SOURCE_TOUR_FORM,
        'property_id' => $this->otherProperty->id, 'city_id' => City::query()->whereKeyNot($lead->city_id)->value('id'),
    ]);
    $value = $lead->{AssignmentRule::CRITERIA[$criterion]};
    AssignmentRule::factory()->active()->matching([$criterion => [$value]])->withAssignees([$this->first])->create();

    expect(app(AssignNewLead::class)->handle($lead)->outcome)->toBe(AssignmentOutcome::Assigned)
        ->and($lead->fresh()->owner_user_id)->toBe($this->first->id)
        ->and(app(AssignNewLead::class)->handle($nonMatching)->outcome)->toBe(AssignmentOutcome::NoRuleMatched)
        ->and($nonMatching->fresh()->owner_user_id)->toBeNull();
})->with(['city_ids', 'property_ids', 'product_ids', 'segment_ids', 'team_sizes', 'sources']);

it('does not match a criterion an enquiry has no value for', function () {
    AssignmentRule::factory()->active()->matching(['team_sizes' => ['2-5']])->withAssignees([$this->first])->create();
    $lead = newLeadForAssignment(['team_size' => null]);

    expect(app(AssignNewLead::class)->handle($lead)->outcome)->toBe(AssignmentOutcome::NoRuleMatched);
});

it('lets the first matching active rule decide, lowest priority number first, and ignores inactive rules', function () {
    AssignmentRule::factory()->active()->create(['priority' => 30, 'strategy' => AssignmentStrategy::FixedUser])->assignees()->attach($this->second, ['position' => 1]);
    $winner = AssignmentRule::factory()->active()->create(['priority' => 20, 'strategy' => AssignmentStrategy::FixedUser]);
    $winner->assignees()->attach($this->first, ['position' => 1]);
    AssignmentRule::factory()->create(['priority' => 10, 'strategy' => AssignmentStrategy::FixedUser])->assignees()->attach($this->second, ['position' => 1]);
    $lead = newLeadForAssignment();

    $decision = app(AssignNewLead::class)->handle($lead);

    expect($decision->assignment_rule_id)->toBe($winner->id)
        ->and($lead->fresh()->owner_user_id)->toBe($this->first->id);
});

it('gives enquiries to the rule\'s people in turn, round-robin, starting with the first in its order', function () {
    $third = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $third->assignedProperties()->attach($this->property);
    $rule = AssignmentRule::factory()->active()->withAssignees([$this->second, $this->first, $third])->create();

    $owners = collect(range(1, 4))->map(fn (): int => app(AssignNewLead::class)->handle(newLeadForAssignment())->assigned_user_id)->all();

    expect($owners)->toBe([$this->second->id, $this->first->id, $third->id, $this->second->id])
        ->and($rule->fresh()->last_assigned_user_id)->toBe($this->second->id);
});

it('passes over people who are deactivated or do not work on the enquiry\'s location, without using their turn', function () {
    $elsewhere = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $elsewhere->assignedProperties()->attach($this->otherProperty);
    $deactivated = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['is_active' => false]);
    $deactivated->assignedProperties()->attach($this->property);
    AssignmentRule::factory()->active()->withAssignees([$elsewhere, $deactivated, $this->first])->create();

    $owners = collect(range(1, 2))->map(fn (): int => app(AssignNewLead::class)->handle(newLeadForAssignment())->assigned_user_id)->all();

    expect($owners)->toBe([$this->first->id, $this->first->id]);
});

it('gives every matching enquiry to the fixed person, and leaves it unassigned when that person cannot take it', function () {
    $rule = AssignmentRule::factory()->active()->create(['strategy' => AssignmentStrategy::FixedUser]);
    $rule->assignees()->attach($this->second, ['position' => 1]);

    expect(app(AssignNewLead::class)->handle(newLeadForAssignment())->assigned_user_id)->toBe($this->second->id);

    $this->second->forceFill(['is_active' => false])->save();
    $lead = newLeadForAssignment();
    $decision = app(AssignNewLead::class)->handle($lead);

    expect($decision->outcome)->toBe(AssignmentOutcome::NoEligibleAssignee)
        ->and($decision->assignment_rule_id)->toBe($rule->id)
        ->and($lead->fresh()->owner_user_id)->toBeNull();
});

it('does not try the next rule when the matching rule has nobody eligible (interim until A-33 decides)', function () {
    AssignmentRule::factory()->active()->create(['priority' => 1, 'strategy' => AssignmentStrategy::FixedUser])
        ->assignees()->attach(User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['is_active' => false]), ['position' => 1]);
    AssignmentRule::factory()->active()->create(['priority' => 2])->assignees()->attach($this->first, ['position' => 1]);
    $lead = newLeadForAssignment();

    expect(app(AssignNewLead::class)->handle($lead)->outcome)->toBe(AssignmentOutcome::NoEligibleAssignee)
        ->and($lead->fresh()->owner_user_id)->toBeNull();
});

it('does not assign by least loaded while workload is undefined (A-34)', function () {
    AssignmentRule::factory()->active()->create(['strategy' => AssignmentStrategy::LeastLoaded])->assignees()->attach($this->first, ['position' => 1]);
    $lead = newLeadForAssignment();

    expect(config('crm.assignment.workload_statuses'))->toBeNull()
        ->and(app(AssignNewLead::class)->handle($lead)->outcome)->toBe(AssignmentOutcome::StrategyUnavailable)
        ->and($lead->fresh()->owner_user_id)->toBeNull();
});

it('gives the enquiry to the person with the fewest enquiries in the workload statuses, a tie to the first in the rule\'s order (mechanism only: the statuses are set by this test, A-34)', function () {
    config(['crm.assignment.workload_statuses' => [LeadStatus::New->value, LeadStatus::Contacted->value]]);
    AssignmentRule::factory()->active()->create(['strategy' => AssignmentStrategy::LeastLoaded])->assignees()->attach([
        $this->second->id => ['position' => 1],
        $this->first->id => ['position' => 2],
    ]);
    Lead::factory()->forProperty($this->property)->count(2)->create(['owner_user_id' => $this->second->id, 'status' => LeadStatus::New]);
    Lead::factory()->forProperty($this->property)->create(['owner_user_id' => $this->first->id, 'status' => LeadStatus::Contacted]);
    Lead::factory()->forProperty($this->property)->count(3)->create(['owner_user_id' => $this->first->id, 'status' => LeadStatus::Lost]);

    expect(app(AssignNewLead::class)->handle(newLeadForAssignment())->assigned_user_id)->toBe($this->first->id)
        ->and(app(AssignNewLead::class)->handle(newLeadForAssignment())->assigned_user_id)->toBe($this->second->id);
});

it('decides once per enquiry: a second run changes nothing and audits nothing more', function () {
    AssignmentRule::factory()->active()->withAssignees([$this->first, $this->second])->create();
    $lead = newLeadForAssignment();

    $first = app(AssignNewLead::class)->handle($lead);
    $again = app(AssignNewLead::class)->handle($lead);

    expect($again->is($first))->toBeTrue()
        ->and($lead->fresh()->owner_user_id)->toBe($this->first->id)
        ->and(LeadAssignment::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(1);
});

it('never reassigns an enquiry that already has an owner', function () {
    AssignmentRule::factory()->active()->withAssignees([$this->first])->create();
    $lead = newLeadForAssignment(['owner_user_id' => $this->second->id]);

    expect(app(AssignNewLead::class)->handle($lead)->outcome)->toBe(AssignmentOutcome::AlreadyOwned)
        ->and($lead->fresh()->owner_user_id)->toBe($this->second->id);
});

it('audits an assignment by a rule without personal data', function () {
    $rule = AssignmentRule::factory()->active()->withAssignees([$this->first])->create(['name' => 'Noida desks']);
    $lead = newLeadForAssignment(['submitted_email' => 'asha@example.com']);

    app(AssignNewLead::class)->handle($lead);
    $entry = Activity::query()->where('event', 'lead_auto_assigned')->sole();

    expect($entry->subject->is($lead))->toBeTrue()
        ->and($entry->causer)->toBeNull()
        ->and($entry->properties->all())->toEqual(['reference' => $lead->reference, 'rule_id' => $rule->id, 'to_user_id' => $this->first->id])
        ->and($entry->description)->toBe('Enquiry assigned to First Sales by the rule "Noida desks"')
        ->and(json_encode($entry->withoutRelations()->getAttributes()))->not->toContain('asha@example.com');
});

it('leaves the enquiry and the rule as they were when assignment fails, logs it without personal data, and assigns on a later try', function () {
    $rule = AssignmentRule::factory()->active()->withAssignees([$this->first, $this->second])->create();
    $lead = newLeadForAssignment(['submitted_email' => 'asha@example.com']);
    Log::spy();
    DB::listen(function ($query): void {
        if (str_starts_with(strtolower($query->sql), 'insert into `lead_assignments`') && ! app()->bound('assignment.allowed')) {
            throw new RuntimeException('simulated failure');
        }
    });

    expect(app(AssignNewLead::class)->handleSafely($lead))->toBeNull()
        ->and($lead->fresh()->owner_user_id)->toBeNull()
        ->and($rule->fresh()->last_assigned_user_id)->toBeNull()
        ->and(LeadAssignment::query()->count())->toBe(0)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $context === ['lead_id' => $lead->id, 'exception' => RuntimeException::class]);

    app()->instance('assignment.allowed', true);

    expect(app(AssignNewLead::class)->handleSafely($lead)->assigned_user_id)->toBe($this->first->id)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(1);
});

it('gives a new website enquiry an owner once it is stored, and not again for its repeat', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    // A general enquiry has no location, so only staff who work on every property may own it (BR-CRM-6).
    $this->first->forceFill(['has_all_property_access' => true])->save();
    $this->second->forceFill(['has_all_property_access' => true])->save();
    AssignmentRule::factory()->active()->matching(['sources' => [Lead::SOURCE_WEBSITE_FORM]])->withAssignees([$this->first, $this->second])->create();
    $enquiry = ['started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()), 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1'];

    $lead = app(SubmitEnquiry::class)->handle($enquiry, '127.0.0.1');
    $repeat = app(SubmitEnquiry::class)->handle($enquiry, '127.0.0.1');

    expect($repeat->is($lead))->toBeTrue()
        ->and($lead->fresh()->owner_user_id)->toBeIn([$this->first->id, $this->second->id])
        ->and(LeadAssignment::query()->where('lead_id', $lead->id)->sole()->outcome)->toBe(AssignmentOutcome::Assigned)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(1);
});

it('leaves a general enquiry unassigned when the rule\'s people only work on some locations', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    AssignmentRule::factory()->active()->withAssignees([$this->first])->create();

    $lead = app(SubmitEnquiry::class)->handle(['started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()), 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1'], '127.0.0.1');

    expect($lead->property_id)->toBeNull()
        ->and($lead->fresh()->owner_user_id)->toBeNull()
        ->and($lead->assignment->outcome)->toBe(AssignmentOutcome::NoEligibleAssignee);
});

it('stores the enquiry and leaves it unassigned on a database without the assignment tables (the owner\'s server)', function () {
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    Schema::shouldReceive('hasTable')->andReturnUsing(fn (string $table): bool => ! in_array($table, AssignmentRule::TABLES, true));

    $lead = app(SubmitEnquiry::class)->handle(['started' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()), 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => '1'], '127.0.0.1');

    expect($lead->fresh()->owner_user_id)->toBeNull()
        ->and(app(AssignNewLead::class)->handle($lead))->toBeNull()
        ->and(AssignmentRule::isAvailable())->toBeFalse();
});
