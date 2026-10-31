<?php

namespace App\Models;

use App\Enums\Inventory\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * One uploaded SG-INV workbook (INVENTORY_IMPORT_SPEC §3, §5; ADR-0012): its checksum, validation
 * findings, preview summary, the uploader who submitted it, the different person who approved it, and
 * when it was committed. Changed only by the inventory import actions; audited.
 */
class InventoryImport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'summary' => 'array',
            'warnings_acknowledged_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * Whether this database has the import tables; the owner's server does not (OD-120).
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Space::isAvailable() && Schema::hasTable('inventory_imports') && Schema::hasTable('inventory_import_issues'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<InventoryImportIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(InventoryImportIssue::class);
    }

    public function errorCount(): int
    {
        return $this->issues()->where('severity', 'error')->count();
    }

    public function warningCount(): int
    {
        return $this->issues()->where('severity', 'warning')->count();
    }
}
