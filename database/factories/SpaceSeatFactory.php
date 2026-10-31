<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\SpaceSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceSeat>
 */
class SpaceSeatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'label' => (string) fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
