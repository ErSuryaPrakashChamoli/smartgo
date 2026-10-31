<?php

namespace Database\Factories;

use App\Enums\Pricing\PricePlanStatus;
use App\Enums\Pricing\PriceUnit;
use App\Models\Offering;
use App\Models\PricePlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only plans with test-only amounts; tests of the rules go through the pricing actions.
 *
 * @extends Factory<PricePlan>
 */
class PricePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => Offering::factory(),
            'space_id' => null,
            'unit' => PriceUnit::PerHour,
            'amount_minor' => 100,
            'currency' => 'INR',
            'tax_treatment' => 'exclusive',
            'valid_from' => today(),
            'valid_to' => null,
            'status' => PricePlanStatus::Draft,
            'created_by' => User::factory(),
            'editor_ids' => [],
        ];
    }
}
