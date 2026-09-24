<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<City> */
class CityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city(),
            'region' => fake()->randomElement(['Maritime', 'Kara', 'Centrale', 'Plateaux', 'Savanes']),
            'lat' => fake()->randomFloat(4, 6.1, 11.0),
            'lng' => fake()->randomFloat(4, -0.1, 1.8),
        ];
    }
}
