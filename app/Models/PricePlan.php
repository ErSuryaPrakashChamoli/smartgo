<?php

namespace App\Models;

use App\Enums\Pricing\DepositType;
use App\Enums\Pricing\PricePlanStatus;
use App\Enums\Pricing\PriceUnit;
use Carbon\CarbonImmutable;
use Database\Factories\PricePlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;

/**
 * Operational price truth for one workspace category at one property, or for one space of it (DATABASE_ERD
 * §4, BR-PR-1). Finance enters it; a different person who may approve prices activates it (BR-PR-2). At
 * most one active plan applies to an offering (or space) and unit on any day; ActivatePricePlan enforces
 * that under the offering's lock. Active plans never change: a new price is a new plan. Changes are
 * audited by the pricing actions. Nothing here reaches the website until prices are shown there (D-04).
 */
class PricePlan extends Model
{
    /** @use HasFactory<PricePlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => PriceUnit::class,
            'status' => PricePlanStatus::class,
            'deposit_type' => DepositType::class,
            'amount_minor' => 'integer',
            'deposit_minor' => 'integer',
            'setup_fee_minor' => 'integer',
            'deposit_months' => 'decimal:2',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'editor_ids' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    /**
     * Whether this database has the pricing tables; the owner's server does not (OD-120), so it has no
     * pricing screens and no pricing queries.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable('price_plans') && Schema::hasTable('offers') && Schema::hasTable('offer_offering'));
    }

    /**
     * @return BelongsTo<Offering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Active plans for the same offering, space (or none) and unit.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSameScope(Builder $query, int $offeringId, ?int $spaceId, PriceUnit $unit): void
    {
        $query->where('offering_id', $offeringId)
            ->where('unit', $unit->value)
            ->when($spaceId === null, fn (Builder $scope) => $scope->whereNull('space_id'), fn (Builder $scope) => $scope->where('space_id', $spaceId));
    }

    /**
     * Plans whose dates cover some day from $from to $until (both inclusive; null is open-ended).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $from, ?CarbonImmutable $until): void
    {
        $query->where(fn (Builder $end) => $end->whereNull('valid_to')->orWhereDate('valid_to', '>=', $from->toDateString()))
            ->when($until !== null, fn (Builder $start) => $start->whereDate('valid_from', '<=', $until?->toDateString()));
    }

    /**
     * How an active plan stands on a day: scheduled, current or ended. Other states as they are.
     */
    public function stateOn(CarbonImmutable $day): string
    {
        if ($this->status !== PricePlanStatus::Active) {
            return $this->status->label();
        }

        return match (true) {
            $this->valid_from->greaterThan($day) => 'Scheduled',
            $this->valid_to !== null && $this->valid_to->lessThan($day) => 'Ended',
            default => 'Current',
        };
    }

    /**
     * The people who wrote or submitted the plan; none of them may activate it (BR-PR-2).
     *
     * @return list<int>
     */
    public function authorIds(): array
    {
        return array_values(array_unique(array_filter([...($this->editor_ids ?? []), $this->created_by, $this->submitted_by])));
    }

    /**
     * The scope as staff see it, e.g. "Meeting rooms at SmartGro A-70" or "… (space A70-M1)".
     */
    public function scopeName(): string
    {
        return $this->offering->displayName().($this->space !== null ? ' (space '.$this->space->code.')' : '');
    }

    /**
     * Rupees as Finance enters them ("12500" or "12500.50") in paise; null when not a valid amount.
     */
    public static function toMinor(mixed $rupees): ?int
    {
        $text = trim((string) $rupees);

        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $text, $parts) !== 1) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '0', 2, '0');
    }

    /**
     * Paise as rupees for an input field, e.g. "12500.50".
     */
    public static function toRupees(?int $minor): ?string
    {
        return $minor === null ? null : intdiv($minor, 100).($minor % 100 === 0 ? '' : '.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT));
    }

    /**
     * Paise in Indian format, e.g. "₹12,500.50".
     */
    public static function format(?int $minor): string
    {
        return $minor === null ? 'Not set' : (string) Number::currency($minor / 100, in: 'INR', locale: 'en_IN', precision: $minor % 100 === 0 ? 0 : 2);
    }
}
