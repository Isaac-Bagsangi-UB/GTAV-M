<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
     * Indicate that the registration was cancelled: a day after it was made, or yesterday.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Registration::CANCELLED,
            'cancelled_at' => isset($attributes['created_at'])
                ? Carbon::parse($attributes['created_at'])->addDay()
                : now()->subDay(),
        ]);
    }

    /**
     * Indicate that the ticket was bought at the event's price at a random moment inside its registration window.
     */
    public function registeredDuring(Event $event): static
    {
        return $this->state(function (array $attributes) use ($event) {
            $registeredAt = $this->momentBetween($event->registration_opens_at, $event->registration_closes_at->min(now()));

            return [
                'amount_paid' => $event->ticket_price,
                'created_at' => $registeredAt,
                'updated_at' => $registeredAt,
            ];
        });
    }

    private function momentBetween(CarbonInterface $from, CarbonInterface $to): CarbonInterface
    {
        $minutes = max(1, (int) $from->diffInMinutes($to));

        return $from->copy()->addMinutes(fake()->numberBetween(0, $minutes));
    }
}
