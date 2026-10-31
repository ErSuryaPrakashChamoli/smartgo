<?php

namespace App\Models;

use Database\Factories\SpaceSeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An individually tracked seat in a per-seat or hybrid zone (DATABASE_ERD §3). Deactivated, never
 * deleted, so allocations keep their reference. Changes go through SaveSeats, under the zone's lock.
 */
class SpaceSeat extends Model
{
    /** @use HasFactory<SpaceSeatFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return HasMany<SpaceAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SpaceAllocation::class);
    }
}
