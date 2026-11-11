<?php

namespace App\Models;

use App\Enums\Inventory\InventoryMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A space proposed on a deal (DATABASE_ERD §6 `deal_space`, FR-CRM-4): a whole space, one seat of a
 * per-seat or hybrid zone, or a number of seats of a pool, as an allocation would take them. Once the deal
 * is won it points to the allocation made for it (BR-CRM-7). Written only by the CRM actions.
 */
class DealSpace extends Model
{
    protected $table = 'deal_space';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['seats' => 'integer'];
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
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
     * The allocation made when the deal was won.
     *
     * @return BelongsTo<SpaceAllocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(SpaceAllocation::class, 'space_allocation_id');
    }

    /**
     * What is proposed, e.g. "The whole space (4 seats)", "Seat 3" or "6 seats".
     */
    public function portion(): string
    {
        return match (true) {
            $this->seat !== null => 'Seat '.$this->seat->label,
            $this->space->inventory_mode === InventoryMode::Exclusive => 'The whole space ('.$this->seats.' '.str('seat')->plural($this->seats).')',
            default => $this->seats.' '.str('seat')->plural($this->seats),
        };
    }
}
