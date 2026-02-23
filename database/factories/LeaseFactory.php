<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaseFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'property_id' => Property::factory(),
            'tenant_id' => User::factory()->tenant(),
            'start_date' => $startDate,
            'end_date' => fake()->dateTimeBetween($startDate, '+2 years'),
            'rent_amount' => fake()->randomElement([8000, 10000, 12000, 15000, 18000, 22000]),
            'deposit_amount' => fake()->randomElement([15000, 20000, 30000, 45000]),
            'utility_advances' => fake()->randomElement([2000, 2500, 3000, 3500, 4000]),
            'variable_symbol' => (string)fake()->unique()->numberBetween(10000, 99999),
            'status' => 'active',
        ];
    }

    public function ended(): static
    {
        return $this->state(fn() => [
            'start_date' => fake()->dateTimeBetween('-2 years', '-6 months'),
            'end_date' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'status' => 'ended',
        ]);
    }
}
