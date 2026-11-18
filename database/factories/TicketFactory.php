<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
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
            'category' => fake()->randomElement([TicketCategory::Plumbing, TicketCategory::Electrical, TicketCategory::Heating, TicketCategory::Structural, TicketCategory::Appliance, TicketCategory::Other]),
            'status' => fake()->randomElement([TicketStatus::New, TicketStatus::InProgress, TicketStatus::Resolved]),
            'priority' => fake()->randomElement([TicketPriority::Low, TicketPriority::Medium, TicketPriority::High, TicketPriority::Urgent]),
            'assigned_to' => null,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Resolved,
            'resolved_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::New,
            'resolved_at' => null,
        ]);
    }
}
