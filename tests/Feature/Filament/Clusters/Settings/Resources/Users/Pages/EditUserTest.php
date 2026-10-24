<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('shows the current roles and replaces them on save with an audit entry', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->withRole(StaffRole::Finance)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $staffMember->getRouteKey()])
        ->assertSchemaStateSet(['roles' => ['finance']])
        ->fillForm(['roles' => [StaffRole::MarketingManager->value]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staffMember->fresh()->getRoleNames()->all())->toBe(['marketing_manager'])
        ->and(Activity::query()->where('event', 'roles_changed')->whereMorphedTo('subject', $staffMember)->whereMorphedTo('causer', $superAdmin)->exists())->toBeTrue();
});

it('deactivates another staff member and audits the change', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->withRole(StaffRole::Finance)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $staffMember->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staffMember->fresh()->is_active)->toBeFalse();

    $entry = Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $staffMember)->sole();

    expect($entry->attribute_changes['attributes'])->toBe(['is_active' => false]);
});

it('keeps the password when the password field is left blank', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->withRole(StaffRole::Finance)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $staffMember->getRouteKey()])
        ->fillForm(['name' => 'Renamed Staff Member', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($staffMember->fresh()->name)->toBe('Renamed Staff Member')
        ->and(Hash::check('password', $staffMember->fresh()->password))->toBeTrue();
});

it('rejects a Super Admin removing their own Super Admin role and keeps their record unchanged', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create(['name' => 'Original Name']);

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->fillForm(['name' => 'Changed Name', 'roles' => [StaffRole::Finance->value]])
        ->call('save')
        ->assertHasFormErrors(['roles'])
        ->assertSee('You cannot remove the Super Admin role from your own account.');

    expect($superAdmin->fresh()->hasRole(StaffRole::SuperAdmin))->toBeTrue()
        ->and($superAdmin->fresh()->name)->toBe('Original Name');
});

it('does not let staff deactivate their own account', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->assertFormFieldDisabled('is_active')
        ->set('data.is_active', false)
        ->call('save');

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});

it('resets another user\'s two-step login and audits the reset', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();
    $staffMember->saveAppAuthenticationSecret('SYNTHETICSECRET234567');
    $staffMember->saveAppAuthenticationRecoveryCodes(['synthetic-code-1']);

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $staffMember->getRouteKey()])
        ->callAction('resetTwoFactorAuthentication')
        ->assertNotified('Two-step login reset');

    $staffMember->refresh();

    expect($staffMember->getAppAuthenticationSecret())->toBeNull()
        ->and($staffMember->getAppAuthenticationRecoveryCodes())->toBeNull()
        ->and($staffMember->hasEmailAuthentication())->toBeFalse()
        ->and(Activity::query()->where('event', 'two_factor_reset')->whereMorphedTo('subject', $staffMember)->whereMorphedTo('causer', $superAdmin)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'two_factor_changed')->whereMorphedTo('causer', $superAdmin)->exists())->toBeFalse();
});

it('hides the two-step login reset on your own account', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $superAdmin->getRouteKey()])
        ->assertActionHidden('resetTwoFactorAuthentication');
});

it('offers no way to delete a staff user', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->withRole(StaffRole::Finance)->create();

    actingAs($superAdmin);

    Livewire::test(EditUser::class, ['record' => $staffMember->getRouteKey()])
        ->assertActionDoesNotExist('delete');
});
