<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

test('a Super Admin can read the audit log', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $entry = activity('access')->log('Synthetic entry');

    expect($superAdmin->can('viewAny', Activity::class))->toBeTrue()
        ->and($superAdmin->can('view', $entry))->toBeTrue();
});

test('other staff roles cannot read the audit log', function (StaffRole $role) {
    $user = User::factory()->withRole($role)->create();
    $entry = activity('access')->log('Synthetic entry');

    expect($user->can('viewAny', Activity::class))->toBeFalse()
        ->and($user->can('view', $entry))->toBeFalse();
})->with(collect(StaffRole::cases())->reject(fn (StaffRole $role): bool => $role === StaffRole::SuperAdmin)->all());

test('nobody can add audit log entries by hand', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    expect($superAdmin->can('create', Activity::class))->toBeFalse();
});

test('nobody can change or delete an audit log entry', function (string $ability) {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $entry = activity('access')->log('Synthetic entry');

    expect($superAdmin->can($ability, $entry))->toBeFalse();
})->with(['update', 'delete']);
