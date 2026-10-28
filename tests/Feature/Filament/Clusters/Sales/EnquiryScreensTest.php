<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Filament\Clusters\Sales\Resources\Leads\RelationManagers\ActivitiesRelationManager;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCloseReason;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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
    $this->lead = Lead::factory()->forProperty($this->property)->create(['submitted_name' => 'Asha Verma', 'message' => 'Two desks please']);
});

it('lists enquiries with their status and owner, and counts new unassigned ones', function () {
    actingAs($this->sales);

    Livewire::test(ListLeads::class)
        ->assertCanSeeTableRecords([$this->lead])
        ->assertSee(['Asha Verma', 'New', 'Unassigned', 'SmartGro A-70'])
        ->assertActionHidden('export');

    expect(LeadResource::getNavigationBadge())->toBe('1');
});

it('shows one enquiry with the agreement recorded and saying it is not a booking', function () {
    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->lead]))
        ->assertOk()
        ->assertSee('An enquiry is not a booking')
        ->assertSee('Two desks please')
        ->assertSee('/legal/privacy (version 1)');
});

it('assigns, changes status and records notes from the enquiry page', function () {
    actingAs($this->sales);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->callAction('assign', ['owner' => $this->sales->id])
        ->assertNotified('Owner updated')
        ->callAction('changeStatus', ['status' => LeadStatus::Contacted->value])
        ->assertNotified('Status updated');

    Livewire::test(ActivitiesRelationManager::class, ['ownerRecord' => $this->lead->fresh(), 'pageClass' => ViewLead::class])
        ->callAction(TestAction::make('record')->table(), ['type' => 'follow_up', 'body' => 'Send options', 'due_at' => now()->addDay()->format('Y-m-d H:i:s')])
        ->assertNotified('Saved');

    $followUp = LeadActivity::query()->sole();

    Livewire::test(ActivitiesRelationManager::class, ['ownerRecord' => $this->lead->fresh(), 'pageClass' => ViewLead::class])
        ->callAction(TestAction::make('complete')->table($followUp))
        ->assertNotified('Follow-up done');

    expect($this->lead->fresh())->owner_user_id->toBe($this->sales->id)->status->toBe(LeadStatus::Contacted)
        ->and($followUp->fresh()->completed_at)->not->toBeNull()
        ->and(Activity::query()->inLog('leads')->pluck('event')->all())->toBe(['lead_assigned', 'lead_status_changed', 'lead_activity_recorded', 'lead_follow_up_completed']);
});

it('closes an enquiry from the page only with a reason from the list for that status', function () {
    $lost = LeadCloseReason::factory()->create(['label' => 'Chose another provider']);
    $spam = LeadCloseReason::factory()->disqualifying()->create(['label' => 'Spam']);
    actingAs($this->sales);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->callAction('changeStatus', ['status' => LeadStatus::Lost->value])
        ->assertHasActionErrors(['close_reason_id' => 'required']);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->callAction('changeStatus', ['status' => LeadStatus::Lost->value, 'close_reason_id' => $spam->id]);

    expect($this->lead->fresh()->status)->toBe(LeadStatus::New);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->callAction('changeStatus', ['status' => LeadStatus::Lost->value, 'close_reason_id' => $lost->id, 'details' => 'Signed elsewhere'])
        ->assertNotified('Status updated');

    expect($this->lead->fresh())->status->toBe(LeadStatus::Lost)->close_reason_id->toBe($lost->id);

    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->lead]))->assertSeeInOrder(['Reason', 'Chose another provider', 'Details', 'Signed elsewhere']);
});

it('refuses to close an enquiry from the page while no reasons are set up, and changes nothing', function () {
    actingAs($this->sales);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->callAction('changeStatus', ['status' => LeadStatus::Disqualified->value])
        ->assertHasActionErrors(['close_reason_id' => 'required']);

    expect($this->lead->fresh()->status)->toBe(LeadStatus::New);
});

it('shows a Property Manager the enquiries of their property without any action', function () {
    $manager = User::factory()->withEmailAuthentication()->withRole(StaffRole::PropertyManager)->create();
    $manager->assignedProperties()->attach($this->property);
    actingAs($manager);

    Livewire::test(ViewLead::class, ['record' => $this->lead->getRouteKey()])
        ->assertActionHidden('assign')
        ->assertActionHidden('changeStatus');
});

it('lets the Super Admin export the filtered list', function () {
    actingAs($this->admin);

    Livewire::test(ListLeads::class)
        ->assertActionVisible('export')
        ->callAction('export')
        ->assertFileDownloaded();

    expect(Activity::query()->where('event', 'leads_exported')->sole()->causer->is($this->admin))->toBeTrue();
});
