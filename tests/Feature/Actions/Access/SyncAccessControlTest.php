<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
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
        'backoffice.access',
        'content.approve',
        'content.edit',
        'content.publish',
        'content.view',
        'media.manage',
        'testimonials.publish',
        'users.manage',
    ]);
});

it('grants the Super Admin exactly the capabilities listed for it', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::SuperAdmin->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['audit_log.view', 'backoffice.access', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'media.manage', 'testimonials.publish', 'users.manage']);
});

it('lets the Marketing Manager view, edit, approve and publish website content', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::MarketingManager->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['backoffice.access', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'media.manage', 'testimonials.publish']);
});

it('gives the other roles back-office access and nothing else yet', function (StaffRole $role) {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName($role->value)->permissions->pluck('name')->all())->toBe(['backoffice.access']);
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

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

    expect(Role::findByName(StaffRole::Finance->value)->permissions->pluck('name')->all())->toBe(['backoffice.access']);
});

it('audits the capabilities it grants to each role', function () {
    app(SyncAccessControl::class)->handle();

    $superAdminRole = Role::findByName(StaffRole::SuperAdmin->value);
    $entry = Activity::query()->where('event', 'role_capabilities_changed')->whereMorphedTo('subject', $superAdminRole)->sole();

    expect(Activity::query()->where('event', 'role_capabilities_changed')->count())->toBe(count(StaffRole::cases()))
        ->and($entry->causer)->toBeNull()
        ->and($entry->properties->all())->toEqual([
            'role' => 'super_admin',
            'old' => [],
            'new' => ['audit_log.view', 'backoffice.access', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'media.manage', 'testimonials.publish', 'users.manage'],
        ]);
});

it('audits a capability it removes from a role', function () {
    app(SyncAccessControl::class)->handle();
    Role::findByName(StaffRole::Finance->value)->givePermissionTo(Capability::ManageUsers);

    app(SyncAccessControl::class)->handle();

    $entry = Activity::query()->where('event', 'role_capabilities_changed')->latest('id')->first();

    expect($entry->properties->all())->toEqual(['role' => 'finance', 'old' => ['backoffice.access', 'users.manage'], 'new' => ['backoffice.access']]);
});

it('writes no audit entry when the roles already match the code', function () {
    app(SyncAccessControl::class)->handle();

    app(SyncAccessControl::class)->handle();

    expect(Activity::query()->where('event', 'role_capabilities_changed')->count())->toBe(count(StaffRole::cases()));
});
