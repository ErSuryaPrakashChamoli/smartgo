<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * An article that has never been published: its content exists only in revisions (ADR-0005).
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
     * A live article with one text section, as publishing a revision leaves it.
     */
    public function published(): static
    {
        return $this->state(function (array $attributes): array {
            $title = Str::title(fake()->unique()->words(3, true));

            return [
                'title' => $title,
                'slug' => Str::slug($title),
                'excerpt' => fake()->sentence(),
                'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>'.fake()->sentence().'</p>']]],
                'status' => 'published',
                'published_at' => now(),
            ];
        });
    }
}
