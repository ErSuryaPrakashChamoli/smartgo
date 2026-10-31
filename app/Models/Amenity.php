<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An amenity in the controlled vocabulary (DATABASE_ERD §3), from the inventory import's Amenities sheet.
 * Only names staff supply exist; none is assumed.
 */
class Amenity extends Model
{
    /**
     * @return BelongsToMany<Space, $this>
     */
    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class)->withTimestamps();
    }
}
