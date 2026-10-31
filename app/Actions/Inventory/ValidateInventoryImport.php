<?php

namespace App\Actions\Inventory;

use App\Enums\Inventory\ImportStatus;
use App\Models\InventoryImport;
use App\Models\User;
use App\Services\Inventory\Import\InventoryImporter;
use App\Services\Inventory\Import\WorkbookReader;
use App\Services\Inventory\Import\WorkbookValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Checks an uploaded workbook (INVENTORY_IMPORT_SPEC §3, §4) and records what it found, row by row, with
 * a preview of the changes it would make: a dry run that writes nothing to the inventory. Any earlier
 * findings, acknowledgement and submission are replaced, so a workbook is always submitted on its latest
 * check. Values in findings are truncated and personal data in them masked.
 */
class ValidateInventoryImport
{
    public function __construct(
        private WorkbookReader $workbookReader,
        private WorkbookValidator $workbookValidator,
        private InventoryImporter $inventoryImporter,
    ) {}

    /**
     * @return array{issues: list<array<string, mixed>>, records: array<string, mixed>, plan: array<string, mixed>|null}
     */
    public function handle(InventoryImport $import, User $uploader): array
    {
        $path = Storage::disk('local')->path($import->file_path);
        $workbook = $this->workbookReader->read($path);
        $result = $this->workbookValidator->validate($workbook, (int) filesize($path), $uploader);
        $hasErrors = collect($result['issues'])->contains('severity', 'error');
        $plan = $hasErrors ? null : $this->inventoryImporter->plan($result['records']);

        DB::transaction(function () use ($import, $workbook, $result, $hasErrors, $plan): void {
            $import->issues()->delete();
            $import->issues()->createMany(collect($result['issues'])->map(fn (array $issue): array => [
                'sheet' => $issue['sheet'], 'row' => $issue['row'], 'column' => $issue['column'], 'rule' => $issue['rule'], 'severity' => $issue['severity'],
                'message' => Str::limit($issue['message'], 495), 'value_excerpt' => $issue['value'] === null ? null : Str::limit($this->mask($issue['value']), 75),
            ])->all());

            $import->forceFill([
                'template_id' => $workbook['meta']['template_id'] ?? null,
                'status' => $hasErrors ? ImportStatus::Invalid : ImportStatus::Valid,
                'summary' => $plan === null ? null : collect($plan)->except('fingerprint')->all(),
                'plan_fingerprint' => $plan['fingerprint'] ?? null,
                'warnings_acknowledged_by' => null, 'warnings_acknowledged_at' => null, 'submitted_at' => null,
            ])->save();
        });

        return [...$result, 'plan' => $plan];
    }

    /**
     * Email addresses and phone numbers are never kept in findings (W-170).
     */
    private function mask(string $value): string
    {
        return (string) preg_replace(['/[^\s@]+@[^\s@]+\.[a-z]{2,}/i', '/\+?\d[\d\s-]{8,}\d/'], ['[email removed]', '[number removed]'], $value);
    }
}
