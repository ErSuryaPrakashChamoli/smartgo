<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Crm\RecordLeadActivity;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadActivityType;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Property;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    CarbonImmutable::setTestNow('2027-03-01 04:30:00'); // Monday 1 March 2027, 10:00 in India
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->property);
    $this->lead = Lead::factory()->forProperty($this->property)->create(['submitted_phone' => '+919876543210', 'submitted_email' => 'asha@example.com']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('calls or emails the visitor in one tap from the enquiry page and records the contact, on the agreed channels only (FR-CRM-3)', function () {
    actingAs($this->sales);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->assertActionHasUrl('call', 'tel:+919876543210')
        ->assertActionHasUrl('email', 'mailto:asha@example.com')
        ->assertDontSee('wa.me')
        ->call('recordContactStarted', 'call')
        ->assertNotified('Call recorded')
        ->call('recordContactStarted', 'whatsapp');

    $activity = LeadActivity::query()->sole();

    expect($activity)->type->toBe(LeadActivityType::Call)->user_id->toBe($this->sales->id)->body->toBe('Call started from the enquiry page.')
        ->and($this->lead->fresh()->first_responded_at)->not->toBeNull();
});

it('offers no contact tool to staff who may only read the enquiry, nor records one for them', function () {
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    actingAs($manager);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->assertActionHidden('call')
        ->assertActionHidden('email')
        ->call('recordContactStarted', 'call')
        ->assertNotified('Not allowed');

    expect(LeadActivity::query()->count())->toBe(0);
});

it('shows each enquiry\'s next open follow-up, marks overdue ones, and lists those due by the end of today', function () {
    $dueToday = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Due today']);
    $overdue = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Overdue']);
    $later = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Next week']);
    $done = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Done already']);
    $record = fn (Lead $lead, string $due) => app(RecordLeadActivity::class)->handle($lead, $this->sales, LeadActivityType::FollowUp, 'Call back', CarbonImmutable::parse($due, 'Asia/Kolkata'));
    $record($dueToday, '2027-03-01 18:00');
    $record($overdue, '2027-03-01 11:00');
    $record($later, '2027-03-08 10:00');
    app(RecordLeadActivity::class)->complete($record($done, '2027-03-01 12:00'), $this->sales);
    CarbonImmutable::setTestNow('2027-03-01 07:00:00'); // 12:30 in India: the 11:00 follow-up is overdue
    actingAs($this->sales);

    Livewire::test(ListLeads::class)
        ->assertSee(['1 Mar, 18:00', 'Overdue', '8 Mar, 10:00'])
        ->filterTable('follow_up_due')
        ->assertCanSeeTableRecords([$dueToday, $overdue])
        ->assertCanNotSeeTableRecords([$later, $done, $this->lead]);
});
