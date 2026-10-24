<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Settings\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('lists staff users with their roles for a Super Admin', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $financeUser = User::factory()->withRole(StaffRole::Finance)->create();

    actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$superAdmin, $financeUser])
        ->assertSee('Finance');
});

it('returns 403 to staff without the manage-users capability', function () {
    $operationsManager = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();

    actingAs($operationsManager)->get(UserResource::getUrl('index'))->assertForbidden();
});

it('escapes staff names in the list', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    User::factory()->create(['name' => "O'Reilly <script>alert('xss')</script>"]);

    actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->assertSee('&lt;script&gt;', escape: false)
        ->assertDontSee("<script>alert('xss')</script>", escape: false);
});
