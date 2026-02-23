<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'image_path' => 'properties/placeholder_' . fake()->numberBetween(1, 10) . '.jpg',
            'type' => 'marketing',
            'uploaded_by' => User::factory()->landlord(),
            'description' => fake()->sentence(4),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
