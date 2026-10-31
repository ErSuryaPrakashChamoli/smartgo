<?php

namespace Database\Factories;

use App\Enums\Inventory\InventoryMode;
use App\Models\SpaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceType>
 */
class SpaceTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'type_'.fake()->unique()->numberBetween(1000, 99999),
            'name' => 'Test space type '.fake()->unique()->numberBetween(1, 99999),
            'default_inventory_mode' => InventoryMode::Exclusive,
            'allowed_inventory_modes' => array_column(InventoryMode::cases(), 'value'),
            'is_bookable_by_time' => false,
            'sort_order' => 0,
        ];
    }
}
