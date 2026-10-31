<?php

namespace App\Models;

use App\Enums\Inventory\InventoryMode;
use Database\Factories\SpaceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An admin-managed physical classification of spaces (DATABASE_ERD §3, §3.1), such as "Meeting room",
 * with the inventory modes its spaces may use. Kept by the staff who manage workspace categories.
 */
class SpaceType extends Model
{
    /** @use HasFactory<SpaceTypeFactory> */
    use HasFactory, LogsActivity;

    /**
     * The fields the back-office form sets.
     *
     * @var list<string>
     */
    protected $fillable = ['key', 'name', 'default_inventory_mode', 'allowed_inventory_modes', 'is_bookable_by_time', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_inventory_mode' => InventoryMode::class,
            'allowed_inventory_modes' => 'array',
            'is_bookable_by_time' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Whether this database has the inventory tables (Space::isAvailable()).
     */
    public static function isAvailable(): bool
    {
        return Space::isAvailable();
    }

    /**
     * @return HasMany<Space, $this>
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * The workspace categories its spaces can be sold as (`product_space_type`, kept on each category).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_space_type')->withTimestamps();
    }

    public function allowsInventoryMode(InventoryMode $mode): bool
    {
        return in_array($mode->value, $this->allowed_inventory_modes ?? [], true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'name', 'default_inventory_mode', 'allowed_inventory_modes', 'is_bookable_by_time', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('inventory');
    }
}
