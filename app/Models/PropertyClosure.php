<?php

namespace App\Models;

use Database\Factories\PropertyClosureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A holiday or other closure of a property (DATABASE_ERD §5), stored in UTC and entered in India time.
 * No tour is offered while one runs; bookings will respect closures too (BR-AV-5, Phase 8). A closure
 * never cancels anything already arranged: staff are told which tours fall inside it.
 */
class PropertyClosure extends Model
{
    /** @use HasFactory<PropertyClosureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_public' => 'boolean'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Closures that overlap the period.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
