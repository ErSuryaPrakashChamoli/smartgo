<?php

namespace App\Policies;

use App\Enums\Access\Capability;
use App\Models\InventoryImport;
use App\Models\User;

/**
 * Who may see and upload inventory imports (SECURITY_MODEL §3.1 "Inventory import"): those who upload
 * and those who approve. Approval and commit are checked by CommitInventoryImport (not own, under lock).
 * Imports are never deleted.
 */
class InventoryImportPolicy
{
    /**
     * A deactivated account can do nothing.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Capability::ImportInventory) || $user->can(Capability::ApproveInventoryImport);
    }

    public function view(User $user, InventoryImport $inventoryImport): bool
    {
        return $user->can(Capability::ApproveInventoryImport) || $inventoryImport->uploaded_by === $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::ImportInventory);
    }

    public function update(User $user, InventoryImport $inventoryImport): bool
    {
        return false;
    }

    public function delete(User $user, InventoryImport $inventoryImport): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
