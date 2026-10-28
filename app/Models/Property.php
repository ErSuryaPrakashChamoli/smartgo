<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A SmartGro-operated property (OD-08, OD-13): the operational and marketed unit. Its address is on
 * its primary building. Floors, spaces, capacity and prices are added from real inventory later, so
 * nothing about them is shown until they exist.
 *
 * Operational status (operations staff) and website publication (the profile's approval workflow) are
 * separate: a property is on the website only while both allow it.
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, LogsActivity;

    /**
     * The weekdays opening hours are recorded for, in display order.
     *
     * @var array<string, string>
     */
    public const array WEEKDAYS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operating_model' => OperatingModel::class,
            'operational_status' => PropertyStatus::class,
            'opens_on' => 'date',
            'operating_hours' => 'array',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<MicroMarket, $this>
     */
    public function microMarket(): BelongsTo
    {
        return $this->belongsTo(MicroMarket::class);
    }

    /**
     * @return HasMany<Building, $this>
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    /**
     * @return HasOne<Building, $this>
     */
    public function primaryBuilding(): HasOne
    {
        return $this->hasOne(Building::class)->where('is_primary', true);
    }

    /**
     * The workspace categories operations staff have recorded for this property.
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'offerings')->withPivot('is_active')->withTimestamps();
    }

    /**
     * Every workspace category recorded for the property, active or not.
     *
     * @return HasMany<Offering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(Offering::class);
    }

    /**
     * The offerings shown on the website: active, of an active category, with a published profile, at
     * this property while it is on the website. Public pages read only this relation.
     *
     * @return HasMany<Offering, $this>
     */
    public function publishedOfferings(): HasMany
    {
        return $this->hasMany(Offering::class)->publiclyListed();
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function activeProducts(): BelongsToMany
    {
        return $this->products()->wherePivot('is_active', true)->where('products.is_active', true)->orderBy('sort_order');
    }

    /**
     * The website profile: headline, summary, directions and photos, changed only through revisions.
     *
     * @return HasOne<PropertyProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(PropertyProfile::class);
    }

    /**
     * Staff assigned to this property (`property_user`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assigned_by')->withTimestamps();
    }

    /**
     * The properties the user may see in the back office: all of them, or only their assigned ones
     * when their roles reach only assigned properties (SECURITY_MODEL §1, "enforced in policies and in
     * query scopes").
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Capability $capability = Capability::ViewProperties): void
    {
        if (! $user->hasCapabilityForEveryProperty($capability)) {
            $query->whereHas('staff', fn (Builder $staff) => $staff->whereKey($user->getKey()));
        }
    }

    /**
     * Opening hours as shown on the website, Monday first; empty until any day is recorded. A day
     * without hours is shown as closed once others are recorded.
     *
     * @return list<array{day: string, hours: string}>
     */
    public function openingHours(): array
    {
        $hours = $this->operating_hours ?? [];

        if (collect($hours)->filter(fn (mixed $day): bool => filled($day['opens'] ?? null))->isEmpty()) {
            return [];
        }

        return collect(self::WEEKDAYS)->map(fn (string $label, string $key): array => [
            'day' => $label,
            'hours' => filled($hours[$key]['opens'] ?? null) && filled($hours[$key]['closes'] ?? null)
                ? $hours[$key]['opens'].' to '.$hours[$key]['closes']
                : 'Closed',
        ])->values()->all();
    }

    /**
     * Properties shown on the website: in a state that allows it (open, or planned or pre-opening with
     * a known opening date, BR-AV-6) and with a published profile.
     *
     * @param  Builder<self>  $query
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where(fn (Builder $state) => $state
            ->whereIn('properties.operational_status', PropertyStatus::listableWithoutOpeningDate())
            ->orWhere(fn (Builder $opening) => $opening->whereIn('properties.operational_status', PropertyStatus::listableWithOpeningDate())->whereNotNull('properties.opens_on')))
            ->whereHas('profile', fn (Builder $profile) => $profile->where('status', 'published'));
    }

    public function isPubliclyListed(): bool
    {
        return $this->operational_status->allowsListing($this->opens_on !== null) && $this->profile?->isPublished() === true;
    }

    /**
     * "Opening March 2027" for a listed property that is not open yet, otherwise null.
     */
    public function openingLabel(): ?string
    {
        return $this->operational_status !== PropertyStatus::Open && $this->opens_on !== null
            ? 'Opening '.$this->opens_on->format('F Y')
            : null;
    }

    /**
     * The public address, e.g. /locations/noida/a-70, or null while it is not listed.
     */
    public function path(): ?string
    {
        return $this->isPubliclyListed() ? '/locations/'.$this->city->slug.'/'.$this->slug : null;
    }

    /**
     * The address as known so far, e.g. "A-70, Sector 2, Noida".
     */
    public function addressLine(): string
    {
        $building = $this->primaryBuilding;

        return collect([$building?->address_line_1 ?? $this->name, $building?->address_line_2, $building?->locality ?? $this->microMarket?->name, $this->city->name, $building?->postal_code])
            ->filter()
            ->unique()
            ->join(', ');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['city_id', 'micro_market_id', 'code', 'name', 'slug', 'operating_model', 'operational_status', 'opens_on', 'operating_hours'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('properties');
    }
}
