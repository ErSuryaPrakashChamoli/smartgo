<?php

namespace Database\Factories;

use App\Enums\Crm\AssignmentStrategy;
use App\Models\AssignmentRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test rules only: inactive, matching every enquiry, round-robin, with no people unless given.
 *
 * @extends Factory<AssignmentRule>
 */
class AssignmentRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test rule '.fake()->unique()->numberBetween(1, 100000),
            'priority' => fake()->unique()->numberBetween(1, 100000),
            'strategy' => AssignmentStrategy::RoundRobin,
            'criteria' => [],
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * @param  array<string, list<int|string>>  $criteria
     */
    public function matching(array $criteria): static
    {
        return $this->state(['criteria' => $criteria]);
    }

    /**
     * The people, in this order.
     *
     * @param  list<User>  $users
     */
    public function withAssignees(array $users): static
    {
        return $this->afterCreating(function (AssignmentRule $rule) use ($users): void {
            foreach (array_values($users) as $index => $user) {
                $rule->assignees()->attach($user, ['position' => $index + 1]);
            }
        });
    }
}
