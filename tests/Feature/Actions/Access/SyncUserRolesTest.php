<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Access\SyncUserRoles;
use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

it('replaces the roles and records old and new roles in the audit log', function () {
    $actor = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $user = User::factory()->withRole(StaffRole::Finance)->create();

    app(SyncUserRoles::class)->handle($user, [StaffRole::OperationsManager, StaffRole::PropertyManager], $actor);

    expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['operations_manager', 'property_manager']);

    $entry = Activity::query()->where('event', 'roles_changed')->sole();

    expect($entry->subject->is($user))->toBeTrue()
        ->and($entry->causer->is($actor))->toBeTrue()
        ->and($entry->properties->all())->toEqual([
            'old' => ['finance'],
            'new' => ['operations_manager', 'property_manager'],
        ]);
});

it('does not write an audit entry when the roles are unchanged', function () {
    $actor = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $user = User::factory()->withRole(StaffRole::Finance)->create();

    app(SyncUserRoles::class)->handle($user, [StaffRole::Finance], $actor);

    expect(Activity::query()->where('event', 'roles_changed')->exists())->toBeFalse();
});

it('rejects a Super Admin removing their own Super Admin role', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    expect(fn () => app(SyncUserRoles::class)->handle($superAdmin, [StaffRole::Finance], $superAdmin))
        ->toThrow(ValidationException::class, 'You cannot remove the Super Admin role from your own account.');

    expect($superAdmin->fresh()->hasRole(StaffRole::SuperAdmin))->toBeTrue();
});

it('lets a Super Admin remove the Super Admin role from someone else', function () {
    $actor = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $otherSuperAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    app(SyncUserRoles::class)->handle($otherSuperAdmin, [StaffRole::Finance], $actor);

    expect($otherSuperAdmin->fresh()->getRoleNames()->all())->toBe(['finance']);
});
