<?php

namespace Database\Factories;

use App\Models\Offering;
use App\Models\OfferingProfile;
use App\Models\Product;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    /**
     * An active offering of a new category at a new property, with an empty, unpublished profile, as
     * operations staff recording it leaves it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'product_id' => Product::factory(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Offering $offering): void {
            if ($offering->profile()->doesntExist()) {
                OfferingProfile::factory()->for($offering)->create();
            }
        });
    }

    /**
     * With a published profile, as approving and publishing it leaves it.
     */
    public function published(): static
    {
        return $this->afterCreating(function (Offering $offering): void {
            $offering->profile()->firstOrFail()->forceFill([
                'headline' => fake()->sentence(5),
                'description' => fake()->paragraph(),
                'included' => ['Wi-Fi', 'Reception'],
                'not_included' => ['Parking'],
                'gallery_asset_ids' => [],
                'status' => 'published',
                'published_at' => now(),
            ])->save();
        });
    }
}
