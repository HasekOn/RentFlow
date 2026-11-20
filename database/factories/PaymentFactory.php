<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $dueDate = fake()->dateTimeBetween('-6 months', '-1 month');

        return [
            'lease_id' => Lease::factory(),
            'type' => PaymentType::Rent,
            'amount' => fake()->randomElement([8000, 10000, 12000, 15000, 18000, 22000]),
            'due_date' => $dueDate,
            'paid_date' => null,
            'variable_symbol' => null,
            'status' => PaymentStatus::Unpaid,
            'note' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $dueDate = now()->parse($attributes['due_date']);
            // Paid 0-3 days after due date (mostly on time)
            $paidDate = $dueDate->copy()->addDays(fake()->numberBetween(0, 3));
            // Make sure paid_date is not in the future
            if ($paidDate->isFuture()) {
                $paidDate = now();
            }

            return [
                'paid_date' => $paidDate,
                'status' => PaymentStatus::Paid,
            ];
        });
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'paid_date' => null,
            'status' => PaymentStatus::Unpaid,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-3 months', '-1 month'),
            'paid_date' => null,
            'status' => PaymentStatus::Overdue,
        ]);
    }

    public function latePayment(): static
    {
        return $this->state(function (array $attributes) {
            $dueDate = now()->parse($attributes['due_date']);
            // Paid 10-20 days late (hurts trust score)
            $paidDate = $dueDate->copy()->addDays(fake()->numberBetween(10, 20));
            // Make sure paid_date is not in the future
            if ($paidDate->isFuture()) {
                $paidDate = now();
            }

            return [
                'paid_date' => $paidDate,
                'status' => PaymentStatus::Paid,
            ];
        });
    }
}
