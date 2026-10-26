<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * An FAQ that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live FAQ, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'question' => rtrim(fake()->sentence(), '.').'?',
            'answer' => fake()->paragraph(),
            'sort_order' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
