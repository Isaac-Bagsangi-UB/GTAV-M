<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationService;
use Tests\TestCase;

class RegistrationRulesTest extends TestCase
{
    /**
     * Builds an unsaved ticket for an unsaved event that starts and ends at the given offsets from now, in hours.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function ticket(int $startsInHours, int $endsInHours, array $attributes = []): Registration
    {
        $event = Event::factory()->make([
            'chapter_id' => 1,
            'starts_at' => now()->addHours($startsInHours),
            'ends_at' => now()->addHours($endsInHours),
        ]);

        $registration = new Registration($attributes + ['status' => Registration::CONFIRMED]);

        return $registration->setRelation('event', $event);
    }

    public function test_a_ticket_can_be_cancelled_until_the_event_starts(): void
    {
        $this->assertTrue($this->ticket(24, 30)->canCancel());
        $this->assertFalse($this->ticket(-1, 5)->canCancel());
    }

    public function test_a_ticket_that_was_scanned_or_already_cancelled_cannot_be_cancelled(): void
    {
        $this->assertFalse($this->ticket(24, 30, ['checked_in_at' => now()])->canCancel());
        $this->assertFalse($this->ticket(24, 30, ['status' => Registration::CANCELLED])->canCancel());
    }

    public function test_a_certificate_needs_a_gate_scan_and_a_finished_event(): void
    {
        $this->assertTrue($this->ticket(-30, -20, ['checked_in_at' => now()->subHours(29)])->canDownloadCertificate());
        $this->assertFalse($this->ticket(-30, -20)->canDownloadCertificate(), 'no-shows get no certificate');
        $this->assertFalse($this->ticket(-2, 5, ['checked_in_at' => now()->subHour()])->canDownloadCertificate(), 'not over yet');
    }

    public function test_qr_tokens_are_long_random_and_unique(): void
    {
        $tokens = collect(range(1, 200))->map(fn () => RegistrationService::newToken());

        $this->assertSame(200, $tokens->unique()->count());
        $tokens->each(fn (string $token) => $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $token));
    }
}
