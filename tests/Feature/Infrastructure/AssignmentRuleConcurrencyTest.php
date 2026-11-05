<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\AssignmentOutcome;
use App\Models\AssignmentRule;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concurrency\PublishingHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PublishingHarness::reset();
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);

    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->first = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->second = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->first->assignedProperties()->attach($this->property);
    $this->second->assignedProperties()->attach($this->property);
});

afterEach(function () {
    PublishingHarness::reset();
});

it('takes round-robin turns one after another when four new enquiries arrive at the same instant', function () {
    $rule = AssignmentRule::factory()->active()->withAssignees([$this->first, $this->second])->create();
    $leads = Lead::factory()->forProperty($this->property)->count(4)->create();

    $results = PublishingHarness::runWorkers($leads->map(fn (Lead $lead): array => ['mode' => 'auto_assign', 'lead_id' => $lead->id])->all());

    $owners = Lead::query()->whereKey($leads->modelKeys())->pluck('owner_user_id');

    expect(collect($results)->pluck('outcome')->every(fn (string $outcome): bool => str_starts_with($outcome, AssignmentOutcome::Assigned->value.':')))->toBeTrue()
        ->and($owners->countBy()->all())->toEqual([$this->first->id => 2, $this->second->id => 2])
        ->and(LeadAssignment::query()->count())->toBe(4)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(4)
        ->and($rule->fresh()->last_assigned_user_id)->toBe($this->second->id);
})->group('mysql', 'concurrency');

it('decides once when the same new enquiry is assigned twice at the same instant', function () {
    AssignmentRule::factory()->active()->withAssignees([$this->first, $this->second])->create();
    $lead = Lead::factory()->forProperty($this->property)->create();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'auto_assign', 'lead_id' => $lead->id],
        ['mode' => 'auto_assign', 'lead_id' => $lead->id],
    ]);

    expect(collect($results)->pluck('outcome')->unique()->all())->toBe([AssignmentOutcome::Assigned->value.':'.$this->first->id])
        ->and(LeadAssignment::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', 'lead_auto_assigned')->count())->toBe(1)
        ->and($lead->fresh()->owner_user_id)->toBe($this->first->id);
})->group('mysql', 'concurrency');
