<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\DesignPreviews\Pages\BuildingsAndFloors;
use App\Filament\Clusters\DesignPreviews\Pages\Cities;
use App\Filament\Clusters\DesignPreviews\Pages\DashboardOverview;
use App\Filament\Clusters\DesignPreviews\Pages\MixedZones;
use App\Filament\Clusters\DesignPreviews\Pages\PooledInventory;
use App\Filament\Clusters\DesignPreviews\Pages\PropertyEditor;
use App\Filament\Clusters\DesignPreviews\Pages\SeatInventory;
use App\Filament\Clusters\DesignPreviews\Pages\WorkspaceProducts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

dataset('design preview pages', [
    'dashboard overview' => [DashboardOverview::class],
    'property editor' => [PropertyEditor::class],
    'buildings and floors' => [BuildingsAndFloors::class],
    'workspace products' => [WorkspaceProducts::class],
    'individual seats' => [SeatInventory::class],
    'pooled capacity' => [PooledInventory::class],
    'mixed zones' => [MixedZones::class],
    'cities' => [Cities::class],
]);

it('renders for the Super Admin', function (string $page) {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test($page)->assertOk()->assertSee('Design preview');
})->with('design preview pages');

it('returns 403 to other staff roles', function (string $page) {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create());

    $this->get($page::getUrl())->assertForbidden();
})->with('design preview pages');

it('is not available in production, even to the Super Admin', function (string $page) {
    app()->detectEnvironment(fn (): string => 'production');

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());

    $this->get($page::getUrl())->assertForbidden();
})->with('design preview pages');

it('never saves anything when a preview form is submitted', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(DashboardOverview::class)
        ->call('save')
        ->assertNotified('Design preview: nothing was saved');
});

it('refuses instant online booking for a category until the payment decisions are made', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(WorkspaceProducts::class)
        ->fillForm(['acquisition_workflow' => 'instant_booking'])
        ->call('save')
        ->assertHasFormErrors(['acquisition_workflow'])
        ->assertNotNotified();
});

it('validates a preview form before reporting that nothing was saved', function () {
    actingAs(User::factory()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(BuildingsAndFloors::class)
        ->fillForm(['property' => null])
        ->call('save')
        ->assertHasFormErrors(['property' => 'required']);
});
