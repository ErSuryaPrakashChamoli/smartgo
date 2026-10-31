<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Floor>
 */
class FloorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'building_id' => fn (array $attributes) => Building::factory()->create(['property_id' => $attributes['property_id'], 'is_primary' => true])->getKey(),
            'building_level' => fake()->unique()->numberBetween(0, 200),
            'label' => 'Test floor',
        ];
    }

    public function forProperty(Property $property): static
    {
        return $this->state(fn (): array => [
            'property_id' => $property->getKey(),
            'building_id' => $property->primaryBuilding()->value('id') ?? Building::factory()->create(['property_id' => $property->getKey(), 'is_primary' => true])->getKey(),
        ]);
    }
}
