<?php

namespace Database\Factories;

use App\Models\HomeBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeBanner>
 */
class HomeBannerFactory extends Factory
{
    /**
     * A banner that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live banner, as publishing a revision leaves it. Give it an image with `media_asset_id`.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'heading' => rtrim(fake()->sentence(5), '.').'.',
            'text' => fake()->sentence(14),
            'primary_link' => ['label' => 'All workspace types', 'type' => 'section', 'page_id' => null, 'section' => 'workspace_types', 'product_id' => null, 'url' => null],
            'sort_order' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
