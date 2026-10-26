<?php

namespace Database\Factories;

use App\Enums\Content\RedirectMatch;
use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    /**
     * A permanent redirect between two paths on this website.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(2),
            'match_type' => RedirectMatch::Exact,
            'to_path' => '/'.fake()->unique()->slug(2),
            'status_code' => 301,
        ];
    }
}
