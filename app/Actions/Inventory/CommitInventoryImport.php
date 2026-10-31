<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\ImportStatus;
use App\Events\Inventory\SpaceAvailabilityChanged;
use App\Models\InventoryImport;
use App\Models\Space;
use App\Models\User;
use App\Services\Inventory\Import\InventoryImporter;
use App\Services\Inventory\Import\WorkbookReader;
use App\Services\Inventory\Import\WorkbookValidator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Approves and commits an inventory import, all or nothing (INVENTORY_IMPORT_SPEC §3 "Approval",
 * "Commit"; BR-INV-5). The approver holds `inventory.import.approve` and did not upload it (four-eyes).
 * In one transaction: the import is locked, then the existing spaces it touches, in ascending id order
 * (ADR-0004), so no allocation or status change can race it; every check runs again against SmartGro as
 * it is now, and the commit stops if anything differs from the approved preview. Then every record is
 * written and audited, and each affected space is announced after commit. Any failure rolls back
 * everything; the import is then marked failed with the reason.
 */
class CommitInventoryImport
{
    public function __construct(
        private WorkbookReader $workbookReader,
        private WorkbookValidator $workbookValidator,
        private InventoryImporter $inventoryImporter,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $approver, InventoryImport $import): InventoryImport
    {
        if (! $approver->can(Capability::ApproveInventoryImport) || $import->uploaded_by === $approver->getKey()) {
            throw new AuthorizationException('An import is approved by someone who may approve imports and did not upload it.');
        }

        try {
            return DB::transaction(function () use ($approver, $import): InventoryImport {
                $import = InventoryImport::query()->with('uploader')->lockForUpdate()->findOrFail($import->getKey());

                if ($import->status !== ImportStatus::AwaitingApproval) {
                    throw ValidationException::withMessages(['import' => 'Only an import awaiting approval can be approved. This one is '.strtolower($import->status->label()).'.']);
                }

                $path = Storage::disk('local')->path($import->file_path);
                $workbook = $this->workbookReader->read($path);
                $column = array_search('space_code', $workbook['sheets']['Spaces']['headers'] ?? [], true);
                $codes = $column === false ? [] : collect($workbook['sheets']['Spaces']['rows'])->map(fn (array $cells): string => strtoupper(trim((string) ($cells[$column] ?? ''))))->filter()->all();
                Space::withTrashed()->whereIn('code', $codes)->orderBy('id')->lockForUpdate()->get();

                $result = $this->workbookValidator->validate($workbook, (int) filesize($path), $import->uploader);
                $plan = collect($result['issues'])->contains('severity', 'error') ? null : $this->inventoryImporter->plan($result['records']);

                if ($plan === null || $plan['fingerprint'] !== $import->plan_fingerprint) {
                    throw ValidationException::withMessages(['import' => 'SmartGro changed since this workbook was checked, so nothing was imported. The uploader checks it again and resubmits it.']);
                }

                $import->forceFill(['status' => ImportStatus::Approved, 'approved_by' => $approver->getKey(), 'approved_at' => now()])->save();
                $import->forceFill(['status' => ImportStatus::Committing])->save();
                $affected = $this->inventoryImporter->apply($result['records'], $approver, $import);
                $import->forceFill(['status' => ImportStatus::Committed, 'committed_at' => now()])->save();

                activity('inventory')->performedOn($import)->causedBy($approver)->event('inventory_import_committed')
                    ->withProperties(['file_sha256' => $import->file_sha256, 'counts' => $plan['counts'], 'spaces_affected' => count($affected)])
                    ->log('Inventory import approved and committed');

                foreach (Space::withTrashed()->whereKey($affected)->get() as $space) {
                    SpaceAvailabilityChanged::dispatch($space, 'import');
                }

                return $import;
            });
        } catch (ValidationException|AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $import->forceFill(['status' => ImportStatus::Failed])->save();
            activity('inventory')->performedOn($import)->causedBy($approver)->event('inventory_import_failed')->log('Inventory import failed; nothing was written');

            throw ValidationException::withMessages(['import' => 'The import failed and nothing was written. It can be checked again and resubmitted.']);
        }
    }
}
