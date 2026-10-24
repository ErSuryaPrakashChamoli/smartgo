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
 * Idempotent, so it is safe to run on every deployment.
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
                Role::findOrCreate($staffRole)->syncPermissions($staffRole->capabilities());
            }
        });

        $this->permissionRegistrar->forgetCachedPermissions();
    }
}
