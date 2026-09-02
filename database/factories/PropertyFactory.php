<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => strtoupper($this->faker->unique()->bothify('REF-####')),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(['apartment', 'villa', 'house', 'office', 'land', 'commercial']),
            'transaction_type' => $this->faker->randomElement(['sale', 'rent']),
            'price' => $this->faker->numberBetween(50000, 900000),
            'surface' => $this->faker->numberBetween(30, 400),
            'bedrooms' => $this->faker->numberBetween(0, 6),
            'bathrooms' => $this->faker->numberBetween(0, 4),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'status' => 'draft',
            'featured' => false,
            'published_at' => null,
        ];
    }
}
