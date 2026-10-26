<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Models\Revision;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('redirects guests to the back office sign-in page', function () {
    get(Filament::getUrl())->assertRedirect(Filament::getLoginUrl());
});

it('lets active staff with two-step login into the back office', function (StaffRole $role) {
    $user = User::factory()->withEmailAuthentication()->withRole($role)->create();

    actingAs($user)->get(Filament::getUrl())->assertOk();
})->with(StaffRole::cases());

it('returns 403 to a signed-in user without a staff role', function () {
    $user = User::factory()->withEmailAuthentication()->create();

    actingAs($user)->get(Filament::getUrl())->assertForbidden();
});

it('refuses, on every back-office page, staff whose role has not been given back-office access', function () {
    Role::findByName(StaffRole::SuperAdmin->value)->revokePermissionTo(Capability::AccessBackoffice);
    $superAdmin = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();

    $pages = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn (RoutingRoute $route, string $name): bool => str_starts_with($name, 'filament.admin.')
            && (! str_starts_with($name, 'filament.admin.auth.') || $name === 'filament.admin.auth.profile')
            && in_array('GET', $route->methods(), true))
        ->keys();

    expect($pages)->not->toBeEmpty();

    $revision = Revision::factory()->create();

    foreach ($pages as $name) {
        actingAs($superAdmin)->get(route($name, ['record' => $superAdmin, 'revision' => $revision]))->assertForbidden();
    }
});

it('lets in a staff member given back-office access as an exception, until it is revoked', function () {
    Role::findByName(StaffRole::Finance->value)->revokePermissionTo(Capability::AccessBackoffice);
    $user = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();

    actingAs($user)->get(Filament::getUrl())->assertForbidden();

    $user->givePermissionTo(Capability::AccessBackoffice);
    actingAs($user)->get(Filament::getUrl())->assertOk();

    $user->revokePermissionTo(Capability::AccessBackoffice);
    actingAs($user)->get(Filament::getUrl())->assertForbidden();
});

it('never lets in a user without a staff role, even with back-office access granted directly', function () {
    $user = User::factory()->withEmailAuthentication()->create();
    $user->givePermissionTo(Capability::AccessBackoffice);

    actingAs($user)->get(Filament::getUrl())->assertForbidden();
});

it('keeps every staff member in the back office once access:sync has run', function () {
    $staff = collect(StaffRole::cases())->map(fn (StaffRole $role): User => User::factory()->withEmailAuthentication()->withRole($role)->create());
    collect(StaffRole::cases())->each(fn (StaffRole $role) => Role::findByName($role->value)->revokePermissionTo(Capability::AccessBackoffice));

    $staff->each(fn (User $user) => actingAs($user)->get(Filament::getUrl())->assertForbidden());

    artisan('access:sync')->assertSuccessful();

    $staff->each(fn (User $user) => actingAs($user)->get(Filament::getUrl())->assertOk());
});

it('returns 403 to deactivated staff', function () {
    $user = User::factory()->inactive()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($user)->get(Filament::getUrl())->assertForbidden();
});

it('sends staff without two-step login to set it up before they can continue', function () {
    $user = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($user)->get(Filament::getUrl())
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('shows the SmartGro tab icon in the back office', function () {
    get(Filament::getLoginUrl())->assertSee(asset('favicon.ico'), escape: false);
});

it('offers authenticator-app and email two-step login with recovery codes', function () {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->toHaveKeys(['app', 'email_code'])
        ->and($providers['app']->isRecoverable())->toBeTrue();
});
