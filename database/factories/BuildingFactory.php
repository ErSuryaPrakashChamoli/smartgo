<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'code' => 'B-'.fake()->unique()->numberBetween(1000, 99999),
            'name' => null,
            'address_line_1' => 'A-'.fake()->numberBetween(1, 200),
            'address_line_2' => null,
            'locality' => 'Sector '.fake()->numberBetween(1, 150),
            'postal_code' => null,
            'city_id' => fn (array $attributes) => Property::query()->find($attributes['property_id'])?->city_id,
            'state' => null,
            'country_code' => 'IN',
            'is_primary' => false,
        ];
    }
}
