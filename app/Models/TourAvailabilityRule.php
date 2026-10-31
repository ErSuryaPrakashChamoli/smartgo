<?php

namespace App\Models;

use Database\Factories\TourAvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A weekly window when tours can happen at a property (DATABASE_ERD §5, BR-TR-1): the weekday (1 is
 * Monday), the first and last time in India time, the length of each tour and how many tours may run at
 * once. Windows of one property never overlap on the same day. Entered by staff from SmartGro's tour
 * rules (D-28); changes are audited by SaveTourRule.
 */
class TourAvailabilityRule extends Model
{
    /** @use HasFactory<TourAvailabilityRuleFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const array WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'slot_minutes' => 'integer', 'max_concurrent_tours' => 'integer'];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * The first time, e.g. "10:00".
     */
    public function startsAt(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    /**
     * The time by which the last tour ends, e.g. "13:00".
     */
    public function endsAt(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    /**
     * E.g. "Monday, 10:00 to 13:00: 30-minute tours, up to 2 at a time".
     */
    public function summary(): string
    {
        return self::WEEKDAYS[$this->weekday].', '.$this->startsAt().' to '.$this->endsAt().': '.$this->slot_minutes.'-minute tours, up to '.$this->max_concurrent_tours.' at a time';
    }
}
