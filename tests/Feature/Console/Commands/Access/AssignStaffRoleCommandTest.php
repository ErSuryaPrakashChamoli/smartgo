<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('gives an existing user a staff role and audits it', function () {
    $user = User::factory()->create(['email' => 'first.admin@example.test']);

    artisan('access:assign-role', ['email' => 'first.admin@example.test', 'role' => 'super_admin'])
        ->expectsOutputToContain('first.admin@example.test now has the Super Admin role.')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(StaffRole::SuperAdmin))->toBeTrue()
        ->and(Activity::query()->where('event', 'roles_changed')->whereMorphedTo('subject', $user)->sole()->properties->all())
        ->toEqual(['added' => 'super_admin', 'via' => 'console']);
});

it('lets a first Super Admin created from the console into the back office', function () {
    $user = User::factory()->withEmailAuthentication()->create(['email' => 'first.admin@example.test']);

    artisan('access:assign-role', ['email' => 'first.admin@example.test', 'role' => 'super_admin'])->assertSuccessful();

    actingAs($user->fresh())->get(Filament::getUrl())->assertOk();
});

it('fails with the list of valid roles when the role is unknown', function () {
    User::factory()->create(['email' => 'first.admin@example.test']);

    artisan('access:assign-role', ['email' => 'first.admin@example.test', 'role' => 'owner'])
        ->expectsOutputToContain('Unknown role. Valid roles: super_admin, operations_manager, property_manager, sales_crm_executive, marketing_manager, finance.')
        ->assertFailed();
});

it('fails when no user has the email address', function () {
    artisan('access:assign-role', ['email' => 'nobody@example.test', 'role' => 'super_admin'])
        ->expectsOutputToContain('No user exists with that email address.')
        ->assertFailed();
});

it('does not write a second audit entry when the user already has the role', function () {
    User::factory()->withRole(StaffRole::Finance)->create(['email' => 'finance@example.test']);

    artisan('access:assign-role', ['email' => 'finance@example.test', 'role' => 'finance'])->assertSuccessful();

    expect(Activity::query()->where('event', 'roles_changed')->exists())->toBeFalse();
});
