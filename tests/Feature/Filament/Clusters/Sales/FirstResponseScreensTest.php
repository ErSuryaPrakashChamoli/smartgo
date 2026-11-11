<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use App\Services\Crm\FirstResponseClock;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:15', 'Asia/Kolkata'));
    $this->waiting = Lead::factory()->forProperty($this->property)->create(['created_at' => now()->subMinutes(135)]);
    $this->answered = Lead::factory()->forProperty($this->property)->create(['created_at' => now()->subMinutes(135), 'first_responded_at' => now()->subMinutes(100)]);
});

it('shows how long an enquiry has waited and how long a response took, and filters those still waiting, with no target', function () {
    actingAs($this->sales);

    Livewire::test(ListLeads::class)
        ->assertSee(['2 h 15 min', 'after 35 min'])
        ->assertDontSee(['Due by', 'Overdue since', 'past the first-response target'])
        ->assertTableFilterHidden('first_response_overdue')
        ->filterTable('awaiting_first_response')
        ->assertCanSeeTableRecords([$this->waiting])
        ->assertCanNotSeeTableRecords([$this->answered]);

    get(LeadResource::getUrl('view', ['record' => $this->waiting]))->assertOk()->assertSee('Not yet: waiting 2 h 15 min since it arrived.')->assertDontSee('Due by');
    get(LeadResource::getUrl('view', ['record' => $this->answered]))->assertOk()->assertSee('9 Nov 2026, 10:35 IST, after 35 min.');
});

it('shows the due time, overdue enquiries, their filter and their count once a target is set (mechanism; the target is set by this test, A-41)', function () {
    config(['crm.first_response.target_minutes' => 15, 'crm.first_response.clock' => FirstResponseClock::CALENDAR]);
    $fresh = Lead::factory()->forProperty($this->property)->create(['created_at' => now()->subMinutes(5)]);
    actingAs($this->sales);

    Livewire::test(ListLeads::class)
        ->assertSee(['Overdue since 9 Nov, 10:15', 'Due by 9 Nov, 12:25', 'after 35 min, after its target', '1 enquiry past the first-response target.'])
        ->assertTableFilterVisible('first_response_overdue')
        ->filterTable('first_response_overdue')
        ->assertCanSeeTableRecords([$this->waiting])
        ->assertCanNotSeeTableRecords([$this->answered, $fresh]);

    get(LeadResource::getUrl('view', ['record' => $this->waiting]))->assertOk()->assertSee('Overdue since 9 Nov, 10:15.');
    get(LeadResource::getUrl('view', ['record' => $this->answered]))->assertOk()->assertSee('after 35 min, after its target.');
});

it('says why an enquiry has no due time when the target counts the location\'s hours and it has none (mechanism; set by this test)', function () {
    config(['crm.first_response.target_minutes' => 15, 'crm.first_response.clock' => FirstResponseClock::LOCATION_HOURS]);
    $this->property->forceFill(['operating_hours' => null])->save();
    actingAs($this->sales);

    get(LeadResource::getUrl('view', ['record' => $this->waiting]))->assertOk()->assertSee('No due time: the location has no opening hours recorded.');
});

it('sends nothing about overdue enquiries: the alert stays inside the back office (O-1)', function () {
    Notification::fake();
    Mail::fake();
    config(['crm.first_response.target_minutes' => 15, 'crm.first_response.clock' => FirstResponseClock::CALENDAR]);
    actingAs($this->sales);

    Livewire::test(ListLeads::class)->assertSee('1 enquiry past the first-response target.');

    Notification::assertNothingSent();
    Mail::assertNothingOutgoing();
});
