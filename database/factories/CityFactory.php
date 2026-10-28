<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word()).' City';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'aliases' => [],
            'state' => null,
            'country_code' => 'IN',
            'is_target_market' => true,
            'is_publicly_visible' => false,
        ];
    }
}
