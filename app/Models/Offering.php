<?php

namespace App\Models;

use Database\Factories\OfferingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A workspace category that operations staff have recorded as available at a property (OD-08,
 * DATABASE_ERD §4). Unique per property and category. It is never deleted, only deactivated, so its
 * profile and the enquiries about it keep their reference. It is enquiry-only: capacity, prices and
 * availability come with inventory and pricing.
 */
class Offering extends Model
{
    /** @use HasFactory<OfferingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasOne<OfferingProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(OfferingProfile::class);
    }

    /**
     * Offerings shown on the website: active, of an active category, at a listed property, with a
     * published profile.
     *
     * @param  Builder<self>  $query
     */
    public function scopePubliclyListed(Builder $query): void
    {
        $query->where('offerings.is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->where('is_active', true))
            ->whereHas('property', fn (Builder $property) => $property->publiclyListed())
            ->whereHas('profile', fn (Builder $profile) => $profile->where('status', 'published'));
    }

    public function isPubliclyListed(): bool
    {
        return $this->is_active && $this->product->is_active && $this->property->isPubliclyListed() && $this->profile?->isPublished() === true;
    }

    /**
     * The public address, e.g. /locations/noida/a-70/private-cabins, or null while it is not listed.
     */
    public function path(): ?string
    {
        return $this->isPubliclyListed() ? $this->property->path().'/'.$this->product->slug : null;
    }

    /**
     * The name shown for the offering, e.g. "Private offices and cabins at SmartGro A-70".
     */
    public function displayName(): string
    {
        return $this->product->name.' at SmartGro '.$this->property->name;
    }
}
