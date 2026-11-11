<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test deals only: an open deal on a new enquiry in a new open test stage, opened and owned by a new user,
 * without values or proposed spaces. Real deals are opened with OpenDeal.
 *
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => Lead::factory(),
            'pipeline_stage_id' => PipelineStage::factory(),
            'created_by' => User::factory(),
            'owner_user_id' => fn (array $attributes): mixed => $attributes['created_by'],
            'seats' => null,
            'monthly_value_minor' => null,
            'expected_close_on' => null,
        ];
    }

    public function forLead(Lead $lead): static
    {
        return $this->state(['lead_id' => $lead->getKey()]);
    }

    public function inStage(PipelineStage $stage): static
    {
        return $this->state(['pipeline_stage_id' => $stage->getKey()]);
    }
}
