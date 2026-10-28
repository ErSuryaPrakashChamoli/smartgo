<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\AssignLead;
use App\Actions\Crm\ChangeLeadStatus;
use App\Actions\Crm\ExportLeads;
use App\Actions\Crm\RecordLeadActivity;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\LeadCloseReason;
use App\Models\Property;
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
    $this->admin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['name' => 'Ravi Sales']);
    $this->sales->assignedProperties()->attach($this->property);
    $this->lead = Lead::factory()->forProperty($this->property)->create();
});

it('assigns an enquiry to staff who may work on its location, and audits it', function () {
    app(AssignLead::class)->handle($this->lead, $this->admin, $this->sales, expectedOwnerId: null);

    $entry = Activity::query()->inLog('leads')->where('event', 'lead_assigned')->sole();

    expect($this->lead->fresh()->owner_user_id)->toBe($this->sales->id)
        ->and($entry->causer->is($this->admin))->toBeTrue()
        ->and($entry->properties->all())->toMatchArray(['from_user_id' => null, 'to_user_id' => $this->sales->id])
        ->and(app(AssignLead::class)->candidates($this->lead))->toHaveKey($this->sales->id);
});

it('refuses an owner who cannot work on the location, a deactivated one, or a change made by someone else meanwhile', function () {
    $elsewhere = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();
    $inactive = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create(['is_active' => false]);
    $inactive->assignedProperties()->attach($this->property);

    expect(fn () => app(AssignLead::class)->handle($this->lead, $this->admin, $elsewhere, null))->toThrow(ValidationException::class, 'This person cannot work on enquiries about this location.')
        ->and(fn () => app(AssignLead::class)->handle($this->lead, $this->admin, $inactive, null))->toThrow(ValidationException::class, 'This person cannot work on enquiries about this location.')
        ->and(app(AssignLead::class)->candidates($this->lead))->not->toHaveKey($elsewhere->id)->not->toHaveKey($inactive->id);

    app(AssignLead::class)->handle($this->lead, $this->admin, $this->sales, null);

    expect(fn () => app(AssignLead::class)->handle($this->lead, $this->admin, null, expectedOwnerId: null))
        ->toThrow(ValidationException::class, 'Someone else changed who owns this enquiry.')
        ->and($this->lead->fresh()->owner_user_id)->toBe($this->sales->id);
});

it('moves an enquiry along the allowed statuses, records the first response, and closes it only with a listed reason (BR-CRM-5)', function () {
    $lostReason = LeadCloseReason::factory()->create(['label' => 'Chose another provider']);
    $disqualifyingReason = LeadCloseReason::factory()->disqualifying()->create();
    $switchedOffReason = LeadCloseReason::factory()->inactive()->create();

    app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Contacted, LeadStatus::New);

    expect($this->lead->fresh())->status->toBe(LeadStatus::Contacted)->first_responded_at->not->toBeNull()
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Lost, LeadStatus::Contacted))->toThrow(ValidationException::class, 'Choose why the enquiry is lost from the list.')
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Lost, LeadStatus::Contacted, $disqualifyingReason->id))->toThrow(ValidationException::class, 'Choose why the enquiry is lost from the list.')
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Lost, LeadStatus::Contacted, $switchedOffReason->id))->toThrow(ValidationException::class, 'Choose why the enquiry is lost from the list.')
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::New, LeadStatus::Contacted))->toThrow(ValidationException::class, 'cannot be marked new')
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Qualified, LeadStatus::New))->toThrow(ValidationException::class, 'Someone else changed this enquiry\'s status.');

    app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Lost, LeadStatus::Contacted, $lostReason->id, ' Signed with a centre nearer home. ');

    expect($this->lead->fresh())->status->toBe(LeadStatus::Lost)->close_reason_id->toBe($lostReason->id)->closed_reason->toBe('Signed with a centre nearer home.')
        ->and(Activity::query()->where('event', 'lead_status_changed')->latest('id')->first()->properties->all())->toMatchArray(['from' => 'contacted', 'to' => 'lost', 'reason' => 'Chose another provider', 'details' => 'Signed with a centre nearer home.']);

    app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Contacted, LeadStatus::Lost);

    expect($this->lead->fresh())->status->toBe(LeadStatus::Contacted)->close_reason_id->toBeNull()->closed_reason->toBeNull();
});

it('refuses to close an enquiry while no reason is set up for that status', function () {
    expect(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $this->sales, LeadStatus::Disqualified, LeadStatus::New))
        ->toThrow(ValidationException::class, 'No reasons for marking an enquiry disqualified are set up yet.')
        ->and($this->lead->fresh()->status)->toBe(LeadStatus::New);
});

it('records notes, contacts and follow-ups, and completes a follow-up once', function () {
    app(RecordLeadActivity::class)->handle($this->lead, $this->sales, LeadActivityType::Note, 'Asked for a call after 5 pm.');

    expect($this->lead->fresh()->first_responded_at)->toBeNull();

    app(RecordLeadActivity::class)->handle($this->lead, $this->sales, LeadActivityType::Call, 'Called; sending options.');
    $followUp = app(RecordLeadActivity::class)->handle($this->lead, $this->sales, LeadActivityType::FollowUp, 'Send the proposal.', now()->addDay());

    expect($this->lead->fresh()->first_responded_at)->not->toBeNull()
        ->and(fn () => app(RecordLeadActivity::class)->handle($this->lead, $this->sales, LeadActivityType::FollowUp, 'Too late', now()->subHour()))->toThrow(ValidationException::class, 'Choose when the follow-up is due')
        ->and(fn () => app(RecordLeadActivity::class)->handle($this->lead, $this->sales, LeadActivityType::Note, '   '))->toThrow(ValidationException::class);

    app(RecordLeadActivity::class)->complete($followUp, $this->sales);

    expect($followUp->fresh())->completed_by->toBe($this->sales->id)
        ->and(fn () => app(RecordLeadActivity::class)->complete($followUp, $this->sales))->toThrow(ValidationException::class, 'This follow-up is already done.')
        ->and(Activity::query()->where('event', 'lead_activity_recorded')->pluck('properties')->pluck('type')->all())->toBe(['note', 'call', 'follow_up']);
});

it('lets a Property Manager read the enquiries of their property but not change them', function () {
    $manager = User::factory()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);

    expect($manager->can('view', $this->lead))->toBeTrue()
        ->and(fn () => app(RecordLeadActivity::class)->handle($this->lead, $manager, LeadActivityType::Note, 'Hello'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->lead, $manager, LeadStatus::Contacted, LeadStatus::New))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AssignLead::class)->handle($this->lead, $manager, $this->sales, null))->toThrow(AuthorizationException::class);
});

it('exports enquiries for the Super Admin only, audited, with cells that cannot run as formulas', function () {
    Lead::factory()->forProperty($this->property)->create(['submitted_name' => '=HYPERLINK("http://evil.example")', 'message' => '+SUM(A1)']);

    $response = app(ExportLeads::class)->handle($this->admin, Lead::query()->visibleTo($this->admin));
    ob_start();
    $response->sendContent();
    $csv = (string) ob_get_clean();

    expect($csv)->toContain("'=HYPERLINK")->toContain("'+SUM(A1)")->not->toContain(',=HYPERLINK')
        ->and(Activity::query()->where('event', 'leads_exported')->sole()->properties['rows'])->toBe(2)
        ->and(fn () => app(ExportLeads::class)->handle($this->sales, Lead::query()->visibleTo($this->sales)))->toThrow(AuthorizationException::class);
});
