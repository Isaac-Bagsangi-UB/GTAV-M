<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Registration;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    /**
     * Define the model's default state: a successful scan of a confirmed ticket.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'event_id' => fn (array $attributes) => Registration::find($attributes['registration_id'])->event_id,
            'scanned_by' => User::factory()->state(['role' => User::ROLE_GATE]),
            'result' => 'admitted',
            'code_scanned' => fn (array $attributes) => Registration::find($attributes['registration_id'])->reference_code,
            'device' => fake()->randomElement(['web-scanner', 'handheld', 'api']),
        ];
    }

    /**
     * Indicate that the given gate staff member scanned this ticket at the given time and let the holder in.
     */
    public function admits(Registration $registration, User $staff, CarbonInterface $scannedAt): static
    {
        return $this->state(fn (array $attributes) => [
            'registration_id' => $registration->id,
            'event_id' => $registration->event_id,
            'scanned_by' => $staff->id,
            'result' => 'admitted',
            'code_scanned' => $registration->reference_code,
            'created_at' => $scannedAt,
            'updated_at' => $scannedAt,
        ])->afterCreating(function (CheckIn $checkIn) use ($registration) {
            // The ticket records the moment of its first successful scan; repeat scans leave it alone.
            if ($checkIn->result === 'admitted' && ! $registration->checked_in_at) {
                $registration->forceFill(['checked_in_at' => $checkIn->created_at])->saveQuietly();
            }
        });
    }

    /**
     * Indicate that the scan was refused or was a repeat scan (duplicate, invalid, wrong_event or cancelled).
     */
    public function refused(string $result = 'duplicate'): static
    {
        return $this->state(fn (array $attributes) => ['result' => $result]);
    }
}
