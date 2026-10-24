<?php

namespace Database\Factories;

use App\Enums\Access\StaffRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user has already set up email two-factor authentication.
     */
    public function withEmailAuthentication(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_email_authentication' => true,
        ]);
    }

    /**
     * Give the user a staff role. The roles must already exist (see SyncAccessControl).
     */
    public function withRole(StaffRole $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role));
    }
}
