<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyClosure>
 */
class PropertyClosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'reason' => 'Test closure',
            'is_public' => false,
            'created_by' => User::factory(),
        ];
    }
}
