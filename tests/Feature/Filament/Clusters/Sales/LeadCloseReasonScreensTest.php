<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\LeadCloseReasonResource;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\CreateLeadCloseReason;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\EditLeadCloseReason;
use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages\ListLeadCloseReasons;
use App\Models\Lead;
use App\Models\LeadCloseReason;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->admin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
});

it('lets the Super Admin add closing reasons, unique per status, and audits them', function () {
    actingAs($this->admin);

    Livewire::test(ListLeadCloseReasons::class)->assertOk()->assertSee('No closing reasons yet');

    Livewire::test(CreateLeadCloseReason::class)
        ->fillForm(['status' => LeadStatus::Lost->value, 'label' => 'Chose another provider', 'sort_order' => 10, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateLeadCloseReason::class)
        ->fillForm(['status' => LeadStatus::Lost->value, 'label' => 'Chose another provider'])
        ->call('create')
        ->assertHasFormErrors(['label' => 'unique']);

    Livewire::test(CreateLeadCloseReason::class)
        ->fillForm(['status' => LeadStatus::Disqualified->value, 'label' => 'Chose another provider'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LeadCloseReason::query()->offeredFor(LeadStatus::Lost)->pluck('label')->all())->toBe(['Chose another provider'])
        ->and(Activity::query()->inLog('leads')->where('event', 'created')->count())->toBe(2)
        ->and(Activity::query()->inLog('leads')->where('event', 'created')->first()->causer->is($this->admin))->toBeTrue();
});

it('switches a used reason off instead of deleting it, and keeps its status', function () {
    $reason = LeadCloseReason::factory()->create(['label' => 'Budget']);
    Lead::factory()->create(['status' => LeadStatus::Lost, 'close_reason_id' => $reason->id]);
    actingAs($this->admin);

    Livewire::test(EditLeadCloseReason::class, ['record' => $reason->getRouteKey()])
        ->assertFormFieldExists('status', fn (Select $field): bool => $field->isDisabled())
        ->assertActionDoesNotExist('delete')
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($reason->fresh())->is_active->toBeFalse()->status->toBe(LeadStatus::Lost)
        ->and(LeadCloseReason::query()->offeredFor(LeadStatus::Lost)->exists())->toBeFalse();
});

it('keeps the lists from everyone but the Super Admin', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
        ->get(LeadCloseReasonResource::getUrl('index'))
        ->assertForbidden();
})->with([StaffRole::SalesCrmExecutive, StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::MarketingManager]);

it('keeps the lists from a deactivated Super Admin', function () {
    $this->admin->update(['is_active' => false]);

    expect($this->admin->fresh()->can('viewAny', LeadCloseReason::class))->toBeFalse()
        ->and($this->admin->fresh()->can('create', LeadCloseReason::class))->toBeFalse();
});
