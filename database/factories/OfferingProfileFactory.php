<?php

namespace Database\Factories;

use App\Models\Offering;
use App\Models\OfferingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferingProfile>
 */
class OfferingProfileFactory extends Factory
{
    /**
     * An offering profile that has never been published: its content exists only in revisions.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offering_id' => Offering::factory(),
            'status' => 'draft',
        ];
    }
}
