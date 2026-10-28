<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A workspace category (OD-07), managed by administrators. Booking, pricing and cancellation rules
 * (DATABASE_ERD §4.1) are added with those features.
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity;

    /**
     * The fields the back-office form sets; every other write goes through forceFill in an action.
     *
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'slug', 'sort_order', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Property, $this>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'offerings')->withPivot('is_active')->withTimestamps();
    }

    /**
     * The properties where operations staff have recorded this category, active or not.
     *
     * @return HasMany<Offering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    /**
     * Active categories in their set order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeShown(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'name', 'slug', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('properties');
    }
}
