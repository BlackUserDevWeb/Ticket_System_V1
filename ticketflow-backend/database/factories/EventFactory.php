<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(7, 60));

        return [
            'organizer_id' => User::factory()->organizer(),
            'venue_id' => Venue::factory(),
            'slug' => Str::slug(fake()->unique()->sentence(3)),
            'title' => rtrim(fake()->sentence(4), '.'),
            'category' => fake()->randomElement(['concert', 'sport', 'theatre', 'festival', 'conference']),
            'description' => fake()->paragraph(),
            'lineup' => [fake()->name()],
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(4),
            'sales_open_at' => now()->subDay(),
            'sales_close_at' => $start->copy()->subHour(),
            'status' => 'published',
            'dynamic_pricing_enabled' => false,
            'seated_viewing' => true,
        ];
    }

    /** Événement sponsorisé actif (badge + tête de liste accueil). */
    public function sponsored(): static
    {
        return $this->state(fn () => ['sponsored_until' => now()->addWeek()]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
