<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Venue> */
class VenueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Arena',
            'city_id' => \App\Models\City::factory(),
            'capacity' => fake()->numberBetween(200, 5000),
            'address' => fake()->streetAddress(),
            'lat' => fake()->randomFloat(4, 6.1, 11.0),   // bornes géographiques du Togo
            'lng' => fake()->randomFloat(4, -0.1, 1.8),
            'layout' => ['type' => 'generic'],
        ];
    }
}
