<?php

namespace Database\Factories;

use App\Enums\Crm\PipelineOutcome;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test stages only: the owner has not decided the stage names, order or probabilities (D-09), so none is
 * seeded. An active open stage without a probability unless given.
 *
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test stage '.fake()->unique()->numberBetween(1, 100000),
            'sort_order' => fake()->numberBetween(1, 1000),
            'probability_pct' => null,
            'outcome' => PipelineOutcome::Open,
            'is_active' => true,
        ];
    }

    public function won(): static
    {
        return $this->state(['outcome' => PipelineOutcome::Won]);
    }

    public function lost(): static
    {
        return $this->state(['outcome' => PipelineOutcome::Lost]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
