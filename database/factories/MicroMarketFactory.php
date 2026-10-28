<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\MicroMarket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicroMarket>
 */
class MicroMarketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 999);

        return [
            'city_id' => City::factory(),
            'name' => "Sector {$number}",
            'slug' => "sector-{$number}",
        ];
    }
}
