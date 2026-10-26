<?php

namespace Database\Factories;

use App\Models\CaseStudy;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseStudy>
 */
class CaseStudyFactory extends Factory
{
    /**
     * A customer story that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live customer story with consent on file and one text section, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(function (array $attributes): array {
            $title = Str::title(fake()->unique()->words(3, true));

            return [
                'title' => $title,
                'slug' => Str::slug($title),
                'company_name' => fake()->company(),
                'summary' => fake()->sentence(),
                'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>'.fake()->sentence().'</p>']]],
                'consent_on_file' => true,
                'consent_reference' => 'CONSENT-'.fake()->numerify('####'),
                'status' => 'published',
                'published_at' => now(),
            ];
        });
    }
}
