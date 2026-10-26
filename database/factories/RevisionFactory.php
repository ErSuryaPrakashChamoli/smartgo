<?php

namespace Database\Factories;

use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Revision>
 */
class RevisionFactory extends Factory
{
    /**
     * A first draft of a page.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'revisionable_type' => (new Page)->getMorphClass(),
            'revisionable_id' => Page::factory(),
            'number' => 1,
            'payload' => [
                'title' => $title,
                'slug' => str($title)->slug()->toString(),
                'template' => 'standard',
                'blocks' => [],
            ],
            'state' => RevisionState::Draft,
            'author_id' => User::factory(),
            'editor_ids' => [],
        ];
    }
}
