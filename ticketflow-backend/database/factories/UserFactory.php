<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 *
 * Téléphone : format togolais +228XXXXXXXX (préfixes 90/91/92/93 Moov, 70/71/79/98 Mixx).
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+228' . fake()->unique()->numerify('9# ######'),
            'password' => Hash::make('password'),
            'role' => 'buyer',
        ];
    }

    public function organizer(): static
    {
        return $this->state(fn () => ['role' => 'organizer']);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }
}
