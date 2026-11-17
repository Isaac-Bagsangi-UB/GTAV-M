<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state: a confirmed, not yet checked-in registration.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'reference_code' => RegistrationService::newReference(),
            'qr_token' => RegistrationService::newToken(),
            'status' => Registration::CONFIRMED,
            'amount_paid' => 150,
            'checked_in_at' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate that the attendee has been admitted at the gate.
     */
    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => ['checked_in_at' => now()->subMinutes(30)]);
    }

    /**
     * Indicate that the registration was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Registration::CANCELLED, 'cancelled_at' => now()->subDay()]);
    }
}
