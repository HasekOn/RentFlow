<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        $titles = [
            'Broken faucet in bathroom',
            'Heating not working',
            'Window seal damaged',
            'Dishwasher leaking',
            'Doorbell not functioning',
            'Mold in bathroom ceiling',
            'Hot water intermittent',
            'Electrical outlet sparking',
            'Toilet running constantly',
            'Refrigerator making noise',
        ];

        return [
            'property_id' => Property::factory(),
            'tenant_id' => User::factory()->tenant(),
            'title' => fake()->randomElement($titles),
            'description' => fake()->paragraph(2),
            'category' => fake()->randomElement(['plumbing', 'electrical', 'heating', 'structural', 'appliance', 'other']),
            'status' => fake()->randomElement(['new', 'in_progress', 'resolved']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'assigned_to' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => 'resolved',
            'resolved_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => 'new',
            'resolved_at' => null,
        ]);
    }
}
