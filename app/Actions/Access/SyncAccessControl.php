<?php

namespace App\Actions\Access;

use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the stored roles and capabilities in line with the code (StaffRole, Capability).
 * Idempotent, so it is safe to run on every deployment. Every role whose capabilities change is
 * recorded in the audit log (SECURITY_MODEL §7, "role and permission changes").
 */
class SyncAccessControl
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            foreach (Capability::cases() as $capability) {
                Permission::findOrCreate($capability);
            }

            foreach (StaffRole::cases() as $staffRole) {
                $role = Role::findOrCreate($staffRole);
                $previousCapabilities = $role->permissions()->pluck('name')->sort()->values()->all();
                $newCapabilities = collect($staffRole->capabilities())->map(fn (Capability $capability): string => $capability->value)->sort()->values()->all();

                if ($previousCapabilities === $newCapabilities) {
                    continue;
                }

                $role->syncPermissions($newCapabilities);

                activity('access')
                    ->performedOn($role)
                    ->event('role_capabilities_changed')
                    ->withProperties(['role' => $staffRole->value, 'old' => $previousCapabilities, 'new' => $newCapabilities])
                    ->log('Role capabilities synchronised from code');
            }
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
