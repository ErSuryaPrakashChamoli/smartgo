<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\ImportStatus;
use App\Models\InventoryImport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The uploader's and the approver's steps before a commit (INVENTORY_IMPORT_SPEC §3), each audited:
 *
 * - revalidate: the uploader checks the same workbook again against SmartGro as it is now;
 * - submit: the uploader sends a checked workbook for approval, acknowledging every warning first;
 * - reject: a person who may approve imports, not the uploader, sends it back with a reason.
 */
class ChangeInventoryImportState
{
    public function __construct(private ValidateInventoryImport $validateInventoryImport) {}

    /**
     * @param  'revalidate'|'submit'|'reject'  $change
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, InventoryImport $import, string $change, bool $warningsAcknowledged = false, ?string $reason = null): InventoryImport
    {
        $isUploader = $import->uploaded_by === $user->getKey();

        if ($change === 'reject' ? (! $user->can(Capability::ApproveInventoryImport) || $isUploader) : (! $isUploader || ! $user->can(Capability::ImportInventory))) {
            throw new AuthorizationException($change === 'reject' ? 'An import is rejected by someone who may approve imports and did not upload it.' : 'Only the person who uploaded this workbook can do this.');
        }

        if ($change === 'revalidate') {
            if (! in_array($import->status, [ImportStatus::Valid, ImportStatus::Invalid, ImportStatus::AwaitingApproval, ImportStatus::Rejected, ImportStatus::Failed], true)) {
                throw ValidationException::withMessages(['import' => 'This import cannot be checked again now.']);
            }

            $this->validateInventoryImport->handle($import, $user);
            activity('inventory')->performedOn($import)->causedBy($user)->event('inventory_import_revalidated')->log('Inventory workbook checked again');

            return $import->fresh();
        }

        return DB::transaction(function () use ($user, $import, $change, $warningsAcknowledged, $reason): InventoryImport {
            $import = InventoryImport::query()->lockForUpdate()->findOrFail($import->getKey());

            if ($change === 'submit') {
                if ($import->status !== ImportStatus::Valid) {
                    throw ValidationException::withMessages(['import' => 'Only a workbook checked without errors can be submitted.']);
                }

                if ($import->warningCount() > 0 && ! $warningsAcknowledged) {
                    throw ValidationException::withMessages(['warnings' => 'Read and acknowledge every warning before submitting.']);
                }

                $import->forceFill(['status' => ImportStatus::AwaitingApproval, 'submitted_at' => now(),
                    ...($import->warningCount() > 0 ? ['warnings_acknowledged_by' => $user->getKey(), 'warnings_acknowledged_at' => now()] : [])])->save();
                activity('inventory')->performedOn($import)->causedBy($user)->event('inventory_import_submitted')->withProperties(['warnings_acknowledged' => $import->warningCount()])->log('Inventory import submitted for approval');

                return $import;
            }

            $reason = trim((string) $reason);

            if ($import->status !== ImportStatus::AwaitingApproval || $reason === '' || mb_strlen($reason) > 500) {
                throw ValidationException::withMessages(['reason' => $import->status !== ImportStatus::AwaitingApproval ? 'Only an import awaiting approval can be rejected.' : 'Give the reason, up to 500 characters.']);
            }

            $import->forceFill(['status' => ImportStatus::Rejected])->save();
            activity('inventory')->performedOn($import)->causedBy($user)->event('inventory_import_rejected')->withProperties(['reason' => $reason])->log('Inventory import rejected');

            return $import;
        });
    }
}
