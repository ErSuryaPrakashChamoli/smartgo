<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyProfile>
 */
class PropertyProfileFactory extends Factory
{
    /**
     * A profile that has never been published: its content exists only in revisions (ADR-0005).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'status' => 'draft',
        ];
    }

    /**
     * A live profile without photos, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'headline' => fake()->sentence(5),
            'summary' => fake()->paragraph(),
            'getting_there' => null,
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'hero_asset_id' => null,
            'gallery_asset_ids' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
