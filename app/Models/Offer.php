<?php

namespace App\Models;

use App\Enums\Pricing\DiscountType;
use App\Enums\Pricing\OfferStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A promotional offer (DATABASE_ERD §4, FR-INV-5): its discount, the instants it applies between, the
 * offerings it applies to, and its terms (BR-PR-5). It applies only after a person who may approve prices,
 * other than its authors, approves it, and stops applying at its end on its own: expiry is derived, so no
 * job is needed. No price floor is checked, because none is defined (BR-PR-5, D-04). Nothing reaches the
 * website until prices are shown there.
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'status' => OfferStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'percent_bps' => 'integer',
            'amount_minor' => 'integer',
            'free_period_quantity' => 'integer',
            'max_redemptions' => 'integer',
            'editor_ids' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Offering, $this>
     */
    public function offerings(): BelongsToMany
    {
        return $this->belongsToMany(Offering::class)->withTimestamps();
    }

    /**
     * Offers that apply at an instant: approved, started and not yet ended.
     *
     * @param  Builder<self>  $query
     */
    public function scopeApplyingAt(Builder $query, Carbon $at): void
    {
        $query->where('status', OfferStatus::Active->value)->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    /**
     * Its state at an instant: an approved offer is expired once it has ended.
     */
    public function stateAt(Carbon $at): OfferStatus
    {
        return $this->status === OfferStatus::Active && $this->ends_at->lessThanOrEqualTo($at) ? OfferStatus::Expired : $this->status;
    }

    /**
     * The people who wrote or submitted the offer; none of them may approve it (BR-PR-5, BR-PR-2).
     *
     * @return list<int>
     */
    public function authorIds(): array
    {
        return array_values(array_unique(array_filter([...($this->editor_ids ?? []), $this->created_by, $this->submitted_by])));
    }

    /**
     * The discount as staff see it, e.g. "10% off", "₹2,000 off", "1 month free".
     */
    public function discountSummary(): string
    {
        return match ($this->discount_type) {
            DiscountType::Percent => rtrim(rtrim(number_format($this->percent_bps / 100, 2), '0'), '.').'% off',
            DiscountType::Fixed => PricePlan::format($this->amount_minor).' off',
            DiscountType::FreePeriod => $this->free_period_quantity.' '.str($this->free_period_unit)->plural($this->free_period_quantity).' free',
        };
    }
}
