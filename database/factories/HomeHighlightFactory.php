<?php

namespace Database\Factories;

use App\Models\HomeHighlight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeHighlight>
 */
class HomeHighlightFactory extends Factory
{
    /**
     * A highlight that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live highlight in SmartGro's own words, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'text' => ucfirst(fake()->words(3, true)),
            'icon' => 'sparkles',
            'is_sample' => false,
            'sort_order' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Live example text, as the sample seeder leaves it.
     */
    public function sample(): static
    {
        return $this->published()->state(fn (array $attributes): array => ['is_sample' => true]);
    }
}
