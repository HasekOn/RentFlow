<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoticeFactory extends Factory
{
    public function definition(): array
    {
        $notices = [
            ['title' => 'Hot water maintenance', 'content' => 'Hot water will be unavailable on March 15 from 8:00 to 14:00 due to scheduled maintenance.'],
            ['title' => 'Building entrance repair', 'content' => 'The main entrance door will be replaced next week. Please use the side entrance.'],
            ['title' => 'Waste collection schedule change', 'content' => 'Starting next month, waste collection will be on Tuesdays instead of Mondays.'],
            ['title' => 'Fire alarm testing', 'content' => 'Fire alarms will be tested this Friday between 10:00 and 12:00. No action required.'],
        ];

        $notice = fake()->randomElement($notices);

        return [
            'property_id' => Property::factory(),
            'created_by' => User::factory()->landlord(),
            'title' => $notice['title'],
            'content' => $notice['content'],
            'is_active' => true,
        ];
    }
}
