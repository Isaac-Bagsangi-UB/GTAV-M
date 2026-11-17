<?php

namespace Database\Factories;

use App\Models\Chapter;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state: a published expo three weeks away with registration open.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addWeeks(3)->setTime(9, 0);

        return [
            'chapter_id' => Chapter::factory(),
            'parent_id' => null,
            'title' => fake()->city().' Trade Expo',
            'type' => 'expo',
            'description' => fake()->sentence(),
            'venue' => fake()->company().' Convention Hall',
            'city' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->setTime(17, 0),
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => $startsAt->copy()->subDay(),
            'capacity' => 50,
            'ticket_price' => 150,
            'status' => 'published',
        ];
    }

    /**
     * Indicate that the event has the given maximum headcount.
     */
    public function capacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => ['capacity' => $capacity]);
    }

    /**
     * Indicate that registration has not opened yet.
     */
    public function registrationNotOpen(): static
    {
        return $this->state(fn (array $attributes) => ['registration_opens_at' => now()->addDays(2)]);
    }

    /**
     * Indicate that the registration cutoff has passed.
     */
    public function registrationClosed(): static
    {
        return $this->state(fn (array $attributes) => ['registration_closes_at' => now()->subHour()]);
    }

    /**
     * Indicate that the event is taking place right now.
     */
    public function happeningNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(6),
            'registration_opens_at' => now()->subWeek(),
            'registration_closes_at' => now()->addHours(5),
        ]);
    }

    /**
     * Indicate that the event is over.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDays(9),
            'registration_opens_at' => now()->subDays(40),
            'registration_closes_at' => now()->subDays(11),
        ]);
    }

    /**
     * Indicate that the event is a draft, not visible to attendees.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }
}
