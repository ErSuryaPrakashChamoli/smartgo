<?php

namespace App\Models;

use Database\Factories\MicroMarketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A business district or sector within a city, such as "Sector 62". It has a website page while at
 * least one of its properties is on the website (INFORMATION_ARCHITECTURE §2).
 */
class MicroMarket extends Model
{
    /** @use HasFactory<MicroMarketFactory> */
    use HasFactory, LogsActivity;

    /**
     * The fields the back-office form sets; every other write goes through forceFill in an action.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'slug'];

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * The sector's website address; the page exists only while one of its properties is on the website.
     */
    public function path(): string
    {
        return '/locations/'.$this->city->slug.'/area/'.$this->slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['city_id', 'name', 'slug'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('properties');
    }
}
