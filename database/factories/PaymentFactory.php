<?php

namespace Database\Factories;

use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-6 months', '+1 month');
        $isPaid = fake()->boolean(70); // 70% paid

        return [
            'lease_id' => Lease::factory(),
            'type' => 'rent',
            'amount' => fake()->randomElement([8000, 10000, 12000, 15000, 18000, 22000]),
            'due_date' => $dueDate,
            'paid_date' => $isPaid ? fake()->dateTimeBetween($dueDate, now()) : null,
            'variable_symbol' => null, // Set from lease in seeder
            'status' => $isPaid ? 'paid' : 'unpaid',
            'note' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $dueDate = $attributes['due_date'];
            // Paid 0-3 days after due date (mostly on time)
            return [
                'paid_date' => now()->parse($dueDate)->addDays(fake()->numberBetween(0, 3)),
                'status' => 'paid',
            ];
        });
    }

    public function unpaid(): static
    {
        return $this->state(fn() => [
            'paid_date' => null,
            'status' => 'unpaid',
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn() => [
            'due_date' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'paid_date' => null,
            'status' => 'overdue',
        ]);
    }

    public function latePayment(): static
    {
        return $this->state(function (array $attributes) {
            $dueDate = $attributes['due_date'];
            // Paid 10-20 days late (hurts trust score)
            return [
                'paid_date' => now()->parse($dueDate)->addDays(fake()->numberBetween(10, 20)),
                'status' => 'paid',
            ];
        });
    }
}
