<?php

namespace App\Actions\Inventory;

use App\Enums\Access\Capability;
use App\Enums\Inventory\ImportStatus;
use App\Models\InventoryImport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Receives an SG-INV workbook (INVENTORY_IMPORT_SPEC §3 "Upload"): staff with `inventory.import` upload an
 * .xlsx of at most 10 MB; it is stored privately with its SHA-256 checksum, audited, and checked at once
 * (ValidateInventoryImport). A file identical to one already imported is accepted and flagged.
 */
class UploadInventoryImport
{
    public function __construct(private ValidateInventoryImport $validateInventoryImport) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, UploadedFile $file): InventoryImport
    {
        if (! $user->can(Capability::ImportInventory)) {
            throw new AuthorizationException('You cannot import inventory.');
        }

        if (Str::lower($file->getClientOriginalExtension()) !== 'xlsx' || $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'Upload the filled-in SG-INV template as an .xlsx file of at most 10 MB.']);
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('inventory-imports', Str::uuid()->toString().'.xlsx', 'local');
        $earlier = InventoryImport::query()->where('file_sha256', $checksum)->where('status', ImportStatus::Committed->value)->latest('committed_at')->first();

        $import = (new InventoryImport)->forceFill([
            'uploaded_by' => $user->getKey(),
            'original_name' => Str::limit($file->getClientOriginalName(), 185),
            'file_path' => $path,
            'file_sha256' => $checksum,
            'status' => ImportStatus::Uploaded,
            'environment' => app()->environment(),
        ]);
        $import->save();

        activity('inventory')->performedOn($import)->causedBy($user)->event('inventory_import_uploaded')
            ->withProperties(['file_sha256' => $checksum, 'already_imported_as' => $earlier?->getKey()])
            ->log('Inventory workbook uploaded');

        $import->forceFill(['status' => ImportStatus::Validating])->save();
        $this->validateInventoryImport->handle($import, $user);

        if ($earlier !== null) {
            $import->forceFill(['summary' => [...($import->summary ?? []), 'already_imported' => 'This exact file was already imported on '.$earlier->committed_at->timezone(config('backoffice.timezone'))->format('j M Y').' (import '.$earlier->getKey().').']])->save();
        }

        return $import->fresh();
    }
}
