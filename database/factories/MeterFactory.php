<?php

namespace Database\Factories;

use App\Enums\MeterType;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'meter_type' => fake()->randomElement([MeterType::Water, MeterType::Electricity, MeterType::Gas, MeterType::Heat]),
            'serial_number' => strtoupper(fake()->bothify('??-######')),
            'location' => fake()->randomElement(['Kitchen', 'Bathroom', 'Hallway', 'Utility room']),
        ];
    }
}
