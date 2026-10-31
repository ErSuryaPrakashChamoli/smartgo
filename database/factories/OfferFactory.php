<?php

namespace Database\Factories;

use App\Enums\Pricing\DiscountType;
use App\Enums\Pricing\OfferStatus;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only offers; tests of the rules go through the pricing actions.
 *
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test offer '.fake()->unique()->numberBetween(1, 99999),
            'code' => null,
            'discount_type' => DiscountType::Percent,
            'percent_bps' => 100,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'terms' => 'Test terms.',
            'status' => OfferStatus::Draft,
            'created_by' => User::factory(),
            'editor_ids' => [],
        ];
    }
}
