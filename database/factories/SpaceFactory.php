<?php

namespace Database\Factories;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Models\Floor;
use App\Models\Space;
use App\Models\SpaceSeat;
use App\Models\SpaceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'floor_id' => Floor::factory(),
            'property_id' => fn (array $attributes) => Floor::query()->find($attributes['floor_id'])?->property_id,
            'space_type_id' => SpaceType::factory(),
            'code' => 'T-'.fake()->unique()->numberBetween(1000, 999999),
            'name' => null,
            'inventory_mode' => InventoryMode::Exclusive,
            'capacity_seats' => 4,
            'area_sqft' => null,
            'operational_status' => SpaceStatus::Active,
            'is_publicly_listable' => false,
        ];
    }

    public function onFloor(Floor $floor): static
    {
        return $this->state(fn (): array => ['floor_id' => $floor->getKey(), 'property_id' => $floor->property_id]);
    }

    public function pooled(int $seats): static
    {
        return $this->state(fn (): array => ['inventory_mode' => InventoryMode::Pooled, 'capacity_seats' => $seats]);
    }

    /**
     * A per-seat or hybrid zone with seats labelled 1 to $seats.
     */
    public function withSeats(int $seats, InventoryMode $mode = InventoryMode::PerSeat): static
    {
        return $this->state(fn (): array => ['inventory_mode' => $mode, 'capacity_seats' => $seats])
            ->afterCreating(function (Space $space) use ($seats): void {
                foreach (range(1, $seats) as $number) {
                    (new SpaceSeat)->forceFill(['space_id' => $space->getKey(), 'label' => (string) $number, 'is_active' => true])->save();
                }
            });
    }
}
