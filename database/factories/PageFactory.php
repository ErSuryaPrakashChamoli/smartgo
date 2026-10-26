<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * A page that has never been published: its content exists only in revisions (ADR-0005).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'draft',
        ];
    }
}
