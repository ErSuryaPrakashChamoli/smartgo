<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Activities\ActivityResource;
use App\Filament\Clusters\Settings\Resources\Activities\Pages\ManageActivities;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('lists audit entries for a Super Admin and opens one for reading', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $entry = activity('access')->causedBy($superAdmin)->event('roles_changed')->withProperties(['new' => ['finance']])->log('Staff roles changed');

    actingAs($superAdmin);

    Livewire::test(ManageActivities::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$entry])
        ->mountAction(TestAction::make('view')->table($entry))
        ->assertMountedActionModalSee('finance');
});

it('returns 403 to staff without the audit-log capability', function () {
    $financeUser = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();

    actingAs($financeUser)->get(ActivityResource::getUrl('index'))->assertForbidden();
});

it('offers no way to add, edit or delete audit entries', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $entry = activity('access')->log('Synthetic entry');

    actingAs($superAdmin);

    Livewire::test(ManageActivities::class)
        ->assertActionDoesNotExist('create')
        ->assertActionDoesNotExist(TestAction::make('edit')->table($entry))
        ->assertActionDoesNotExist(TestAction::make('delete')->table($entry));
});
