<?php

namespace Database\Factories;

use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\Building;
use App\Models\City;
use App\Models\Property;
use App\Models\PropertyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * A planned property, not on the website, without a building.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'T-'.fake()->unique()->numberBetween(100, 9999);

        return [
            'city_id' => City::factory(),
            'micro_market_id' => null,
            'code' => $code,
            'name' => $code,
            'slug' => strtolower($code),
            'timezone' => 'Asia/Kolkata',
            'operating_model' => OperatingModel::OwnedOperated,
            'operational_status' => PropertyStatus::Planned,
        ];
    }

    public function open(): static
    {
        return $this->state(['operational_status' => PropertyStatus::Open]);
    }

    /**
     * An open property on the website: complete address and a published profile, as approving and
     * publishing the profile leaves it. Offerings are added separately.
     */
    public function listed(): static
    {
        return $this->open()->withPrimaryBuilding()->afterCreating(function (Property $property): void {
            PropertyProfile::factory()->published()->for($property)->create();
        });
    }

    /**
     * Adds a primary building whose first address line is the property's name.
     */
    public function withPrimaryBuilding(): static
    {
        return $this->afterCreating(function (Property $property): void {
            Building::factory()->for($property)->create([
                'city_id' => $property->city_id,
                'address_line_1' => $property->name,
                'locality' => $property->microMarket?->name ?? 'Sector 1',
                'is_primary' => true,
            ]);
        });
    }
}
