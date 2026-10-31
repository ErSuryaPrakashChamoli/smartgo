<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\TourAvailabilityRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test tour hours only: SmartGro's tour rules are not decided (D-28), so tests always state theirs.
 *
 * @extends Factory<TourAvailabilityRule>
 */
class TourAvailabilityRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'weekday' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'slot_minutes' => 30,
            'max_concurrent_tours' => 1,
            'created_by' => User::factory(),
        ];
    }

    /**
     * A window on that weekday (1 is Monday), e.g. window(2, '10:00', '11:00', 30, 1).
     */
    public function window(int $weekday, string $start, string $end, int $minutes, int $atOnce): static
    {
        return $this->state(['weekday' => $weekday, 'start_time' => $start.':00', 'end_time' => $end.':00', 'slot_minutes' => $minutes, 'max_concurrent_tours' => $atOnce]);
    }
}
