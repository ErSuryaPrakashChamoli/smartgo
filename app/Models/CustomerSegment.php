<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One of the eight customer segments (OD-04), an optional answer on enquiry forms (BR-CRM-1a). No
 * marketing priority until the owner sets one.
 */
class CustomerSegment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }
}
