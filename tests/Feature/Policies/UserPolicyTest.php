<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

dataset('roles other than Super Admin', fn (): array => collect(StaffRole::cases())
    ->reject(fn (StaffRole $role): bool => $role === StaffRole::SuperAdmin)
    ->all());

test('a Super Admin can list and add staff users', function (string $ability) {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    expect($superAdmin->can($ability, User::class))->toBeTrue();
})->with(['viewAny', 'create']);

test('a Super Admin can view and edit a staff user', function (string $ability) {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->create();

    expect($superAdmin->can($ability, $staffMember))->toBeTrue();
})->with(['view', 'update']);

test('other staff roles cannot list or add staff users', function (StaffRole $role, string $ability) {
    $user = User::factory()->withRole($role)->create();

    expect($user->can($ability, User::class))->toBeFalse();
})->with('roles other than Super Admin')->with(['viewAny', 'create']);

test('other staff roles cannot view or edit a staff user', function (StaffRole $role, string $ability) {
    $user = User::factory()->withRole($role)->create();
    $staffMember = User::factory()->create();

    expect($user->can($ability, $staffMember))->toBeFalse();
})->with('roles other than Super Admin')->with(['view', 'update']);

test('nobody can delete, restore or permanently delete a staff user', function (string $ability) {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $staffMember = User::factory()->create();

    expect($superAdmin->can($ability, $staffMember))->toBeFalse();
})->with(['delete', 'restore', 'forceDelete']);
