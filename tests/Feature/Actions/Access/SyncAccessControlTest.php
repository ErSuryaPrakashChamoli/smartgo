<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates the six staff roles and the capability catalogue', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe([
        'finance',
        'marketing_manager',
        'operations_manager',
        'property_manager',
        'sales_crm_executive',
        'super_admin',
    ])->and(Permission::query()->orderBy('name')->pluck('name')->all())->toBe([
        'audit_log.view',
        'users.manage',
    ]);
});

it('grants the Super Admin every capability explicitly', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::SuperAdmin->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['audit_log.view', 'users.manage']);
});

it('grants no capabilities to the other roles until their modules are built', function (StaffRole $role) {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName($role->value)->permissions)->toBeEmpty();
})->with(collect(StaffRole::cases())->reject(fn (StaffRole $role): bool => $role === StaffRole::SuperAdmin)->all());

it('can run repeatedly without duplicating roles or capabilities', function () {
    app(SyncAccessControl::class)->handle();
    app(SyncAccessControl::class)->handle();

    expect(Role::query()->count())->toBe(6)
        ->and(Permission::query()->count())->toBe(count(Capability::cases()));
});

it('removes a capability from a role when the code no longer grants it', function () {
    app(SyncAccessControl::class)->handle();
    Role::findByName(StaffRole::Finance->value)->givePermissionTo(Capability::ManageUsers);

    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::Finance->value)->permissions)->toBeEmpty();
});
