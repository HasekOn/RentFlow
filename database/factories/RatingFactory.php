<?php

namespace Database\Factories;

use App\Enums\RatingCategory;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RatingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'rated_by' => User::factory()->landlord(),
            'category' => fake()->randomElement([RatingCategory::ApartmentCondition, RatingCategory::Communication, RatingCategory::Rules, RatingCategory::Overall]),
            'score' => fake()->numberBetween(2, 5),
            'comment' => fake()->sentence(8),
        ];
    }
}
