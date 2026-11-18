<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Enums\Role;
use App\Enums\VerificationStatus;

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
            'verification_status' => VerificationStatus::Unverified,            
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
    // User::factory()->seeker()->create() : pengguna uji dengan peran tertentu.
    public function seeker(): static
    {
        return $this->state(fn () => ['role' => Role::Seeker]);
    }

    public function employer(): static
    {
        return $this->state(fn () => ['role' => Role::Employer]);
    }

    // Diberi nama identityVerified (bukan verified) supaya tidak tertukar dengan
    // verifikasi EMAIL bawaan Laravel.
    public function identityVerified(): static
    {
        return $this->state(fn () => ['verification_status' => VerificationStatus::Verified]);
    }
}
