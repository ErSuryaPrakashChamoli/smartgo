<?php

namespace Database\Factories;

use App\Enums\Inventory\AllocationStatus;
use App\Models\Space;
use App\Models\SpaceAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only allocations; tests of the rules record them through RecordAllocation instead.
 *
 * @extends Factory<SpaceAllocation>
 */
class SpaceAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'space_seat_id' => null,
            'agreement_reference' => 'AG-TEST-'.fake()->unique()->numberBetween(1, 99999),
            'seats_allocated' => 1,
            'starts_on' => today()->subMonth(),
            'ends_on' => null,
            'notice_given_on' => null,
            'status' => AllocationStatus::Active,
            'source' => 'manual',
            'created_by' => User::factory(),
        ];
    }
}
