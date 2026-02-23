<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        $items = [
            ['name' => 'Refrigerator', 'category' => 'Appliance'],
            ['name' => 'Washing machine', 'category' => 'Appliance'],
            ['name' => 'Dishwasher', 'category' => 'Appliance'],
            ['name' => 'Microwave', 'category' => 'Appliance'],
            ['name' => 'Sofa', 'category' => 'Furniture'],
            ['name' => 'Bed frame', 'category' => 'Furniture'],
            ['name' => 'Dining table', 'category' => 'Furniture'],
            ['name' => 'Wardrobe', 'category' => 'Furniture'],
            ['name' => 'Desk', 'category' => 'Furniture'],
            ['name' => 'Bookshelf', 'category' => 'Furniture'],
        ];

        $item = fake()->randomElement($items);

        return [
            'property_id' => Property::factory(),
            'name' => $item['name'],
            'category' => $item['category'],
            'condition' => fake()->randomElement(['new', 'good', 'fair', 'poor']),
            'purchase_date' => fake()->dateTimeBetween('-5 years', '-6 months'),
            'purchase_price' => fake()->randomFloat(2, 2000, 30000),
            'note' => null,
        ];
    }
}
