<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Pages\Security;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('lets a Super Admin make two-step login compulsory', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(Security::class)
        ->assertSee('Two-step login is optional')
        ->assertActionHidden('makeTwoStepLoginOptional')
        ->callAction('requireTwoStepLogin')
        ->assertNotified('Two-step login is now required')
        ->assertSee('Two-step login is required')
        ->assertActionHidden('requireTwoStepLogin');

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeTrue();
});

it('lets a Super Admin make two-step login optional again', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());
    SecuritySetting::query()->update(['two_step_login_required' => true]);

    Livewire::test(Security::class)
        ->callAction('makeTwoStepLoginOptional')
        ->assertNotified('Two-step login is now optional')
        ->assertSee('Two-step login is optional');

    expect(SecuritySetting::query()->sole()->two_step_login_required)->toBeFalse();
});

it('offers no way to make two-step login optional in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());

    Livewire::test(Security::class)
        ->assertSee('It is always required on this site')
        ->assertActionHidden('requireTwoStepLogin')
        ->assertActionHidden('makeTwoStepLoginOptional');
});

it('hides the page on a local database that does not have the setting yet', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create());
    Schema::shouldReceive('hasTable')->once()->with('security_settings')->andReturnFalse();

    expect(Security::canAccess())->toBeFalse();
});

it('refuses the page to staff who may not manage staff access', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create())
        ->get(Security::getUrl())
        ->assertForbidden();
});
