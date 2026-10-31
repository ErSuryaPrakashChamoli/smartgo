<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One finding of an inventory import's validation (INVENTORY_IMPORT_SPEC §4): where, which rule, how
 * serious, and the value found (truncated, personal data masked).
 */
class InventoryImportIssue extends Model
{
    /**
     * Written only by ValidateInventoryImport.
     *
     * @var list<string>
     */
    protected $fillable = ['sheet', 'row', 'column', 'rule', 'severity', 'message', 'value_excerpt'];

    /**
     * @return BelongsTo<InventoryImport, $this>
     */
    public function inventoryImport(): BelongsTo
    {
        return $this->belongsTo(InventoryImport::class);
    }
}
