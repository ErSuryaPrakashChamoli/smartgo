<?php

namespace App\Models;

use App\Enums\Inventory\InventoryMode;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A workspace category (OD-07), managed by administrators. Booking, pricing and cancellation rules
 * (DATABASE_ERD §4.1) are added with those features. The inventory modes it can be sold with
 * (BR-PRODCFG-2) and the space types it accepts (`product_space_type`, OD-153) decide which spaces can be
 * sold as it; a database not migrated for either has none, and nothing reads them there.
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
    protected $fillable = ['key', 'name', 'slug', 'sort_order', 'is_active', 'allowed_inventory_modes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'allowed_inventory_modes' => 'array',
        ];
    }

    /**
     * Whether this database has the categories' inventory modes (checked once per request).
     */
    public static function hasInventoryModes(): bool
    {
        return once(fn (): bool => Schema::hasColumn((new self)->getTable(), 'allowed_inventory_modes'));
    }

    /**
     * Whether a space counted this way can be sold as this category (BR-PRODCFG-2). A category whose
     * modes are not set yet, or that uses no physical space (BR-PRODCFG-3), accepts none.
     */
    public function allowsInventoryMode(InventoryMode $mode): bool
    {
        return in_array($mode->value, $this->allowed_inventory_modes ?? [], true);
    }

    /**
     * Whether this database says which space types each category accepts: it has the inventory tables and
     * the list (checked once per request).
     */
    public static function hasSpaceTypes(): bool
    {
        return once(fn (): bool => Space::isAvailable() && Schema::hasTable('product_space_type'));
    }

    /**
     * Whether a space of this type can be sold as this category (BR-PRODCFG-2, ERD §4.1). A category
     * with no space type ticked accepts none. On a database without the list (OD-152 and before), only
     * the inventory mode decides, as it did then.
     */
    public function acceptsSpaceType(SpaceType $spaceType): bool
    {
        return ! self::hasSpaceTypes() || $this->spaceTypes->contains($spaceType);
    }

    /**
     * The space types whose spaces can be sold as this category.
     *
     * @return BelongsToMany<SpaceType, $this>
     */
    public function spaceTypes(): BelongsToMany
    {
        return $this->belongsToMany(SpaceType::class, 'product_space_type')->withTimestamps();
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
            ->logOnly(['key', 'name', 'slug', 'sort_order', 'is_active', 'allowed_inventory_modes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('properties');
    }
}
