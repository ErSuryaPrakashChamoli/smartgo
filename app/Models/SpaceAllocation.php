<?php

namespace App\Models;

use App\Enums\Inventory\AllocationStatus;
use App\Enums\Inventory\InventoryMode;
use Carbon\CarbonImmutable;
use Database\Factories\SpaceAllocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Long-term occupancy of a space, or of one seat in a per-seat or hybrid zone (DATABASE_ERD §3,
 * FR-INV-3). Dates are whole days, both inclusive; an allocation without an end date is open-ended.
 * Only the inventory actions write allocations, under the space's lock (ADR-0004), and only the
 * availability service reads them for availability (FR-INV-6, architecture test).
 */
class SpaceAllocation extends Model
{
    /** @use HasFactory<SpaceAllocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AllocationStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'notice_given_on' => 'immutable_date',
            'seats_allocated' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * @return BelongsTo<SpaceSeat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(SpaceSeat::class, 'space_seat_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Allocations that occupy their space on some day from $from to $until (both inclusive; null is
     * open-ended).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOccupyingBetween(Builder $query, CarbonImmutable $from, ?CarbonImmutable $until): void
    {
        $query->whereIn('status', AllocationStatus::occupying())
            ->where(fn (Builder $end) => $end->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString()))
            ->when($until !== null, fn (Builder $start) => $start->whereDate('starts_on', '<=', $until?->toDateString()));
    }

    /**
     * Whether it occupies its space on this day.
     */
    public function occupiesOn(CarbonImmutable $day): bool
    {
        return $this->status->occupies()
            && $this->starts_on->lessThanOrEqualTo($day)
            && ($this->ends_on === null || $this->ends_on->greaterThanOrEqualTo($day));
    }

    /**
     * Its status on a day, as staff see it: a reserved allocation is active once it starts, and any is
     * ended once its end date has passed.
     */
    public function statusOn(CarbonImmutable $day): AllocationStatus
    {
        return match (true) {
            ! $this->status->occupies() => $this->status,
            $this->ends_on !== null && $this->ends_on->lessThan($day) => AllocationStatus::Ended,
            $this->starts_on->greaterThan($day) => AllocationStatus::Reserved,
            $this->notice_given_on !== null => AllocationStatus::NoticePeriod,
            default => AllocationStatus::Active,
        };
    }

    /**
     * What it is shown as in lists of conflicts, e.g. "AG-104 (seat 3, 1 May 2027 to open-ended)".
     */
    public function summary(): string
    {
        $what = match (true) {
            $this->seat !== null => 'seat '.$this->seat->label,
            $this->space?->inventory_mode === InventoryMode::Exclusive => 'whole space',
            default => $this->seats_allocated.' '.str('seat')->plural($this->seats_allocated),
        };

        return $this->agreement_reference.' ('.$what.', '.$this->starts_on->format('j M Y').' to '.($this->ends_on?->format('j M Y') ?? 'open-ended').')';
    }
}
