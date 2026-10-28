<?php

namespace Database\Factories;

use App\Enums\Crm\LeadStatus;
use App\Models\LeadCloseReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadCloseReason>
 */
class LeadCloseReasonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => LeadStatus::Lost,
            'label' => ucfirst(fake()->unique()->words(3, true)),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function disqualifying(): static
    {
        return $this->state(['status' => LeadStatus::Disqualified]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
