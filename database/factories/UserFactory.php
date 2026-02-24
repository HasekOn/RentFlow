<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'tenant',
            'trust_score' => fake()->randomFloat(2, 30, 100),
            'phone' => fake()->phoneNumber(),
        ];
    }

    public function landlord(): static
    {
        return $this->state(fn () => [
            'role' => 'landlord',
            'trust_score' => 0,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn () => [
            'role' => 'manager',
            'trust_score' => 0,
        ]);
    }

    public function tenant(): static
    {
        return $this->state(fn () => [
            'role' => 'tenant',
        ]);
    }
}
