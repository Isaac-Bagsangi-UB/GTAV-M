<?php

namespace Tests\Unit;

use App\Models\Event;
use Tests\TestCase;

class EventCapacityRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 6, 1)->setTime(12, 0));
    }

    /**
     * Builds an unsaved event with a given number of confirmed tickets already sold.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function eventWith(int $sold, array $attributes = []): Event
    {
        $event = Event::factory()->make(['chapter_id' => 1] + $attributes);
        $event->setAttribute('confirmed_registrations_count', $sold);

        return $event;
    }

    public function test_an_event_with_seats_inside_its_window_is_open(): void
    {
        $event = $this->eventWith(10, ['capacity' => 50]);

        $this->assertSame('open', $event->registrationState());
        $this->assertSame(40, $event->seatsLeft());
        $this->assertSame(20, $event->fillPercent());
    }

    public function test_the_maximum_headcount_makes_an_event_full(): void
    {
        $event = $this->eventWith(50, ['capacity' => 50]);

        $this->assertSame('full', $event->registrationState());
        $this->assertSame(0, $event->seatsLeft());
        $this->assertSame(100, $event->fillPercent());
    }

    public function test_seats_left_and_fill_never_go_past_the_limits(): void
    {
        $overbooked = $this->eventWith(60, ['capacity' => 50]);
        $noSeats = $this->eventWith(0, ['capacity' => 0]);

        $this->assertSame(0, $overbooked->seatsLeft());
        $this->assertSame(100, $overbooked->fillPercent());
        $this->assertSame(0, $noSeats->fillPercent());
    }

    public function test_the_registration_window_controls_when_tickets_can_be_bought(): void
    {
        $notOpen = $this->eventWith(0, ['registration_opens_at' => now()->addDay()]);
        $closed = $this->eventWith(0, ['registration_closes_at' => now()->subMinute()]);

        $this->assertSame('not_open', $notOpen->registrationState());
        $this->assertSame('closed', $closed->registrationState());
    }

    public function test_finished_and_draft_events_cannot_be_booked(): void
    {
        $ended = $this->eventWith(0, ['starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2)]);
        $draft = $this->eventWith(0, ['status' => 'draft']);

        $this->assertSame('ended', $ended->registrationState());
        $this->assertSame('unavailable', $draft->registrationState());
    }

    public function test_free_events_are_labelled_free_and_paid_events_show_the_price(): void
    {
        $this->assertSame('Free', $this->eventWith(0, ['ticket_price' => 0])->priceLabel());
        $this->assertSame('$1,250.00', $this->eventWith(0, ['ticket_price' => 1250])->priceLabel());
    }
}
