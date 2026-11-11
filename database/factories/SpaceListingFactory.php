<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpaceListing>
 */
class SpaceListingFactory extends Factory
{
    /**
     * A listing that has never been published: its content exists only in revisions (ADR-0005).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'draft',
        ];
    }

    /**
     * A live listing in SmartGro's own words at an open location, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(function (array $attributes): array {
            $name = 'Cabin '.fake()->unique()->numberBetween(1, 99999);

            return [
                'property_id' => $attributes['property_id'] ?? Property::factory()->open(),
                'space_type_id' => $attributes['space_type_id'] ?? SpaceType::factory(),
                'space_id' => null,
                'name' => $name,
                'slug' => Str::slug($name),
                'summary' => 'A quiet room for a small team.',
                'details' => null,
                'seats' => null,
                'features' => [],
                'is_sample' => false,
                'sort_order' => 0,
                'status' => 'published',
                'published_at' => now(),
            ];
        });
    }

    /**
     * A live example listing, as the sample seeder leaves it.
     */
    public function sample(): static
    {
        return $this->published()->state(fn (array $attributes): array => ['is_sample' => true]);
    }
}
