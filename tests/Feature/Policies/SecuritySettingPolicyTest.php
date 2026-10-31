<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
});

test('a Super Admin can change whether two-step login is required', function () {
    $superAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    expect($superAdmin->can('update', SecuritySetting::class))->toBeTrue();
});

test('other staff cannot change whether two-step login is required', function (StaffRole $role) {
    $staffMember = User::factory()->withRole($role)->create();

    expect($staffMember->can('update', SecuritySetting::class))->toBeFalse();
})->with(fn (): array => collect(StaffRole::cases())
    ->reject(fn (StaffRole $role): bool => $role === StaffRole::SuperAdmin)
    ->all());
