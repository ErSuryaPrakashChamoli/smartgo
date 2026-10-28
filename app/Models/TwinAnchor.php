<?php

namespace App\Models;

use App\Enums\Twin\AnchorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named node found in a 3D model at upload (ADR-0009): a space, an amenity or a camera position.
 * Anchors are read from the file and never typed in, so nobody handles coordinates.
 */
class TwinAnchor extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor_type' => AnchorType::class,
        ];
    }

    /**
     * @return BelongsTo<TwinModel, $this>
     */
    public function twinModel(): BelongsTo
    {
        return $this->belongsTo(TwinModel::class);
    }
}
