<?php

namespace Database\Factories;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * A testimonial that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live testimonial with consent on file and a verified statement, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quote' => fake()->sentence(12),
            'person_name' => fake()->name(),
            'person_title' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'consent_on_file' => true,
            'consent_reference' => 'CONSENT-'.fake()->numerify('####'),
            'statement_verified' => true,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
