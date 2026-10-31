<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

/**
 * The one canonical record of a physical workspace: a cabin, room, suite or desk zone (DATABASE_ERD §3,
 * BR-SOT-1). Operations staff keep its capacity, status and the categories it can be sold as; marketing
 * never changes them (BR-SOT-3). Availability is never stored: the availability service derives it from
 * the status, seats and allocations (FR-INV-6). Codes are permanent (BR-INV-2).
 *
 * Every change goes through the inventory actions, which lock this row first (ADR-0004) and audit the
 * change with its reason (BR-INV-1).
 */
class Space extends Model
{
    /** @use HasFactory<SpaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The tables the inventory needs; a database not migrated for them (the owner's server, OD-120) has
     * no inventory screens and no inventory queries.
     *
     * @var list<string>
     */
    private const array TABLES = ['space_types', 'floors', 'spaces', 'space_seats', 'offering_space', 'space_allocations', 'amenities', 'amenity_space'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inventory_mode' => InventoryMode::class,
            'operational_status' => SpaceStatus::class,
            'capacity_seats' => 'integer',
            'area_sqft' => 'integer',
            'is_publicly_listable' => 'boolean',
            'turnaround_minutes' => 'integer',
            'min_booking_minutes' => 'integer',
            'max_booking_minutes' => 'integer',
        ];
    }

    /**
     * Whether this database has the inventory tables (checked once per request).
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => collect(self::TABLES)->every(fn (string $table): bool => Schema::hasTable($table)));
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Floor, $this>
     */
    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    /**
     * @return BelongsTo<SpaceType, $this>
     */
    public function spaceType(): BelongsTo
    {
        return $this->belongsTo(SpaceType::class);
    }

    /**
     * @return HasMany<SpaceSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(SpaceSeat::class)->orderBy('id');
    }

    /**
     * @return HasMany<SpaceAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(SpaceAllocation::class);
    }

    /**
     * The property's workspace categories this space can be sold as (BR-AV-3c: one availability for all).
     *
     * @return BelongsToMany<Offering, $this>
     */
    public function offerings(): BelongsToMany
    {
        return $this->belongsToMany(Offering::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->withTimestamps();
    }

    /**
     * The spaces the user may see: all of them, or only those of their assigned properties.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Capability $capability = Capability::ViewProperties): void
    {
        if (! $user->hasCapabilityForEveryProperty($capability)) {
            $query->whereHas('property.staff', fn (Builder $staff) => $staff->whereKey($user->getKey()));
        }
    }

    /**
     * The space as staff see it, e.g. "A70-C01 (Cabin 1)".
     */
    public function displayName(): string
    {
        return $this->code.($this->name !== null ? ' ('.$this->name.')' : '');
    }
}
