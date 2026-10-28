<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Access\SyncUserProperties;
use App\Actions\Crm\ChangeLeadStatus;
use App\Actions\Crm\RecordLeadActivity;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\ListProperties;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->assigned = Property::query()->where('code', 'A-70')->sole();
    $this->other = Property::query()->where('code', 'H-105')->sole();
    $this->sales = User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create();
    $this->sales->assignedProperties()->attach($this->assigned);

    $this->ownLead = Lead::factory()->forProperty($this->assigned)->create(['submitted_name' => 'Own Visitor']);
    $this->otherLead = Lead::factory()->forProperty($this->other)->create(['submitted_name' => 'Other Visitor']);
    $this->generalLead = Lead::factory()->create(['submitted_name' => 'General Visitor']);
});

it('limits a Sales/CRM Executive to the enquiries and properties of their assigned locations by default', function () {
    expect(Lead::query()->visibleTo($this->sales)->pluck('id')->all())->toBe([$this->ownLead->id])
        ->and($this->sales->can('view', $this->otherLead))->toBeFalse()
        ->and($this->sales->can('view', $this->generalLead))->toBeFalse()
        ->and($this->sales->can('update', $this->ownLead))->toBeTrue()
        ->and(Property::query()->visibleTo($this->sales)->pluck('code')->all())->toBe(['A-70']);

    actingAs($this->sales);
    Livewire::test(ListLeads::class)->assertCanSeeTableRecords([$this->ownLead])->assertCanNotSeeTableRecords([$this->otherLead, $this->generalLead]);
    Livewire::test(ListProperties::class)->assertCanSeeTableRecords([$this->assigned])->assertCanNotSeeTableRecords([$this->other]);
});

it('opens every enquiry, general ones included, to a Sales/CRM Executive recorded as working on every property', function () {
    app(SyncUserProperties::class)->handle($this->sales, [$this->assigned->id], User::factory()->withRole(StaffRole::SuperAdmin)->create(), hasAllPropertyAccess: true);

    expect(Lead::query()->visibleTo($this->sales->fresh())->count())->toBe(3)
        ->and($this->sales->fresh()->can('update', $this->generalLead))->toBeTrue();
});

it('refuses direct addresses, crafted filters and searches outside the scope', function () {
    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->ownLead]))->assertOk()->assertSee('Own Visitor');
    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->otherLead]))->assertNotFound();
    actingAs($this->sales)->get(LeadResource::getUrl('view', ['record' => $this->generalLead]))->assertNotFound();

    actingAs($this->sales);
    Livewire::test(ListLeads::class)
        ->filterTable('property_id', $this->other->id)
        ->assertCanNotSeeTableRecords([$this->otherLead])
        ->searchTable('Other Visitor')
        ->assertCountTableRecords(0);
});

it('withdraws access at once when the property assignment is removed, including for changes already open', function () {
    expect($this->sales->can('view', $this->ownLead))->toBeTrue();

    app(SyncUserProperties::class)->handle($this->sales, [$this->other->id], User::factory()->withRole(StaffRole::SuperAdmin)->create());

    expect($this->sales->fresh()->can('view', $this->ownLead))->toBeFalse()
        ->and($this->sales->fresh()->can('view', $this->otherLead))->toBeTrue()
        ->and(fn () => app(ChangeLeadStatus::class)->handle($this->ownLead, $this->sales->fresh(), LeadStatus::Contacted, LeadStatus::New))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RecordLeadActivity::class)->handle($this->ownLead, $this->sales->fresh(), LeadActivityType::Note, 'Still here?'))->toThrow(AuthorizationException::class);

    actingAs($this->sales->fresh())->get(LeadResource::getUrl('view', ['record' => $this->ownLead]))->assertNotFound();
});

it('gives roles without enquiry access no enquiry at all, and a deactivated account nothing', function (StaffRole $role) {
    $user = User::factory()->withEmailAuthentication()->withRole($role)->create();

    expect(Lead::query()->visibleTo($user)->count())->toBe(0)
        ->and($user->can('view', $this->generalLead))->toBeFalse();
    actingAs($user)->get(LeadResource::getUrl('index'))->assertForbidden();
})->with([StaffRole::OperationsManager, StaffRole::MarketingManager, StaffRole::Finance]);

it('refuses a deactivated Sales/CRM Executive everywhere', function () {
    $this->sales->forceFill(['is_active' => false])->save();

    expect($this->sales->fresh()->can('view', $this->ownLead))->toBeFalse()
        ->and(fn () => app(RecordLeadActivity::class)->handle($this->ownLead, $this->sales->fresh(), LeadActivityType::Note, 'Hello'))->toThrow(AuthorizationException::class);
});

it('does not let a role name alone grant property-wide reach', function () {
    $sales = User::factory()->withRole(StaffRole::SalesCrmExecutive)->create();

    expect($sales->hasCapabilityForEveryProperty(Capability::ViewLeads))->toBeFalse()
        ->and(Lead::query()->visibleTo($sales)->count())->toBe(0);
});
