<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeterReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meter_id' => Meter::factory(),
            'reading_value' => fake()->randomFloat(3, 100, 9999),
            'reading_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'submitted_by' => User::factory()->tenant(),
            'photo_proof' => null,
        ];
    }
}
