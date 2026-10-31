<?php

namespace App\Models;

use Database\Factories\FloorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A floor SmartGro operates inside one of a property's buildings (DATABASE_ERD §3, BR-PROP-6). Levels
 * are unique within a building. Floors exist only once staff record them from the owner's facts: none
 * is assumed (OD-30, OD-32). Changes are audited by SaveFloor.
 */
class Floor extends Model
{
    /** @use HasFactory<FloorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['building_level' => 'integer', 'sort_order' => 'integer'];
    }

    /**
     * Whether this database has the inventory tables (Space::isAvailable()).
     */
    public static function isAvailable(): bool
    {
        return Space::isAvailable();
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * The floor as staff see it, e.g. "Level 2 (Second floor)".
     */
    public function displayName(): string
    {
        return 'Level '.$this->building_level.' ('.$this->label.')';
    }
}
