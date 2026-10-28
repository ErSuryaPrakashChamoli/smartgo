<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A market city (OD-03, OD-16). It is "operating" when it has an open property; that is derived, never
 * typed. A city without one is public only when switched visible (OD-18).
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory, LogsActivity;

    /**
     * Addresses a city can never have: /locations/compare is the comparison page (FR-DISC-4).
     *
     * @var list<string>
     */
    public const array RESERVED_SLUGS = ['compare'];

    /**
     * The fields the back-office form sets; every other write goes through forceFill in an action.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'slug', 'state', 'aliases', 'is_target_market', 'is_publicly_visible'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'is_target_market' => 'boolean',
            'is_publicly_visible' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * @return HasMany<MicroMarket, $this>
     */
    public function microMarkets(): HasMany
    {
        return $this->hasMany(MicroMarket::class);
    }

    /**
     * Cities with at least one property on the website ("operating" is derived, never typed, OD-18).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOperating(Builder $query): void
    {
        $query->whereHas('properties', fn (Builder $query) => $query->publiclyListed());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'aliases', 'state', 'is_target_market', 'is_publicly_visible'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('properties');
    }
}
