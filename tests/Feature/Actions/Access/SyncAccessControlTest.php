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
        'brand.approve',
        'brand.manage',
        'brand.publish',
        'content.approve',
        'content.edit',
        'content.publish',
        'content.view',
        'leads.assign',
        'leads.export',
        'leads.manage',
        'leads.settings.manage',
        'leads.view',
        'media.manage',
        'products.manage',
        'profiles.edit',
        'profiles.view',
        'properties.manage',
        'properties.view',
        'redirects.approve',
        'redirects.manage',
        'redirects.publish',
        'settings.contact.approve',
        'settings.contact.manage',
        'settings.contact.publish',
        'settings.legal.manage',
        'settings.legal.verify',
        'settings.legal.view',
        'settings.tracking.approve',
        'settings.tracking.manage',
        'settings.tracking.publish',
        'testimonials.publish',
        'twin_models.manage',
        'twin_models.view',
        'users.manage',
    ]);
});

it('grants the Super Admin exactly the capabilities listed for it', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::SuperAdmin->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['audit_log.view', 'backoffice.access', 'brand.approve', 'brand.manage', 'brand.publish', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'leads.assign', 'leads.export', 'leads.manage', 'leads.settings.manage', 'leads.view', 'media.manage', 'products.manage', 'profiles.edit', 'profiles.view', 'properties.manage', 'properties.view', 'redirects.approve', 'redirects.manage', 'redirects.publish', 'settings.contact.approve', 'settings.contact.manage', 'settings.contact.publish', 'settings.legal.manage', 'settings.legal.verify', 'settings.legal.view', 'settings.tracking.approve', 'settings.tracking.manage', 'settings.tracking.publish', 'testimonials.publish', 'twin_models.manage', 'twin_models.view', 'users.manage']);
});

it('lets the Marketing Manager view, edit, approve and publish website content, property profiles included, and upload 3D models', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::MarketingManager->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['backoffice.access', 'brand.approve', 'brand.manage', 'brand.publish', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'media.manage', 'profiles.edit', 'profiles.view', 'properties.view', 'redirects.approve', 'redirects.manage', 'redirects.publish', 'settings.contact.approve', 'settings.contact.manage', 'settings.contact.publish', 'settings.tracking.approve', 'settings.tracking.manage', 'settings.tracking.publish', 'testimonials.publish', 'twin_models.manage', 'twin_models.view']);
});

it('lets the Operations Manager see and manage properties and workspace categories, and read profiles and 3D models', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::OperationsManager->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['backoffice.access', 'products.manage', 'profiles.view', 'properties.manage', 'properties.view', 'twin_models.view']);
});

it('lets the Property Manager see and edit properties, write profiles, add photos and read enquiries, limited to assigned properties', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::PropertyManager->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['backoffice.access', 'leads.view', 'media.manage', 'profiles.edit', 'profiles.view', 'properties.manage', 'properties.view']);
});

it('lets the Sales/CRM Executive see properties and profiles and work on enquiries', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::SalesCrmExecutive->value)->permissions->pluck('name')->sort()->values()->all())->toBe(['backoffice.access', 'leads.assign', 'leads.manage', 'leads.view', 'profiles.view', 'properties.view']);
});

it('lets Finance see, edit and verify the legal entity details and see properties', function () {
    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::Finance->value)->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['backoffice.access', 'properties.view', 'settings.legal.manage', 'settings.legal.verify', 'settings.legal.view']);
});

it('can run repeatedly without duplicating roles or capabilities', function () {
    app(SyncAccessControl::class)->handle();
    app(SyncAccessControl::class)->handle();

    expect(Role::query()->count())->toBe(6)
        ->and(Permission::query()->count())->toBe(count(Capability::cases()));
});

it('removes a capability from a role when the code no longer grants it', function () {
    app(SyncAccessControl::class)->handle();
    Role::findByName(StaffRole::SalesCrmExecutive->value)->givePermissionTo(Capability::ManageUsers);

    app(SyncAccessControl::class)->handle();

    expect(Role::findByName(StaffRole::SalesCrmExecutive->value)->permissions->pluck('name')->sort()->values()->all())->toBe(['backoffice.access', 'leads.assign', 'leads.manage', 'leads.view', 'profiles.view', 'properties.view']);
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
            'new' => ['audit_log.view', 'backoffice.access', 'brand.approve', 'brand.manage', 'brand.publish', 'content.approve', 'content.edit', 'content.publish', 'content.view', 'leads.assign', 'leads.export', 'leads.manage', 'leads.settings.manage', 'leads.view', 'media.manage', 'products.manage', 'profiles.edit', 'profiles.view', 'properties.manage', 'properties.view', 'redirects.approve', 'redirects.manage', 'redirects.publish', 'settings.contact.approve', 'settings.contact.manage', 'settings.contact.publish', 'settings.legal.manage', 'settings.legal.verify', 'settings.legal.view', 'settings.tracking.approve', 'settings.tracking.manage', 'settings.tracking.publish', 'testimonials.publish', 'twin_models.manage', 'twin_models.view', 'users.manage'],
        ]);
});

it('audits a capability it removes from a role', function () {
    app(SyncAccessControl::class)->handle();
    Role::findByName(StaffRole::SalesCrmExecutive->value)->givePermissionTo(Capability::ManageUsers);

    app(SyncAccessControl::class)->handle();

    $entry = Activity::query()->where('event', 'role_capabilities_changed')->latest('id')->first();

    expect($entry->properties->all())->toEqual(['role' => 'sales_crm_executive', 'old' => ['backoffice.access', 'leads.assign', 'leads.manage', 'leads.view', 'profiles.view', 'properties.view', 'users.manage'], 'new' => ['backoffice.access', 'leads.assign', 'leads.manage', 'leads.view', 'profiles.view', 'properties.view']]);
});

it('writes no audit entry when the roles already match the code', function () {
    app(SyncAccessControl::class)->handle();

    app(SyncAccessControl::class)->handle();

    expect(Activity::query()->where('event', 'role_capabilities_changed')->count())->toBe(count(StaffRole::cases()));
});
