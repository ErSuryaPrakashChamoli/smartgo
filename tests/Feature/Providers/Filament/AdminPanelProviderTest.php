<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
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

it('returns 403 to deactivated staff', function () {
    $user = User::factory()->inactive()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($user)->get(Filament::getUrl())->assertForbidden();
});

it('sends staff without two-step login to set it up before they can continue', function () {
    $user = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($user)->get(Filament::getUrl())
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('offers authenticator-app and email two-step login with recovery codes', function () {
    $providers = Filament::getPanel('admin')->getMultiFactorAuthenticationProviders();

    expect($providers)->toHaveKeys(['app', 'email_code'])
        ->and($providers['app']->isRecoverable())->toBeTrue();
});
