<?php

namespace Database\Factories;

use App\Enums\PropertyStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        $dispositions = ['1+kk', '1+1', '2+kk', '2+1', '3+kk', '3+1', '4+kk'];

        return [
            'landlord_id' => User::factory()->landlord(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip_code' => fake()->postcode(),
            'size' => fake()->randomFloat(2, 25, 150),
            'disposition' => fake()->randomElement($dispositions),
            'floor' => fake()->numberBetween(1, 12),
            'status' => fake()->randomElement([PropertyStatus::Available, PropertyStatus::Occupied, PropertyStatus::Renovation]),
            'purchase_price' => fake()->randomFloat(2, 1500000, 8000000),
            'description' => fake()->sentence(10),
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => ['status' => PropertyStatus::Available]);
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => PropertyStatus::Occupied]);
    }

    public function renovation(): static
    {
        return $this->state(fn () => ['status' => PropertyStatus::Renovation]);
    }
}
