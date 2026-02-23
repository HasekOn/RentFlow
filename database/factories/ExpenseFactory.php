<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'type' => fake()->randomElement(['repair', 'insurance', 'tax', 'maintenance', 'other']),
            'amount' => fake()->randomFloat(2, 500, 50000),
            'expense_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => fake()->sentence(6),
            'invoice_path' => null,
        ];
    }
}
