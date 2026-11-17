<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attendee_registers_and_receives_a_ticket_code(): void
    {
        $attendee = User::factory()->create();
        $event = Event::factory()->create(['ticket_price' => 220]);

        $this->actingAs($attendee)->post(route('registrations.store', $event))
            ->assertRedirectToRoute('registrations.index')
            ->assertSessionHas('success');

        $registration = Registration::where('user_id', $attendee->id)->where('event_id', $event->id)->sole();
        $this->assertSame(Registration::CONFIRMED, $registration->status);
        $this->assertMatchesRegularExpression('/^EX-[A-Z0-9]{8}$/', $registration->reference_code);
        $this->assertEquals(220, $registration->amount_paid);
    }

    public function test_a_person_cannot_register_twice_for_the_same_event(): void
    {
        $attendee = User::factory()->create();
        $event = Event::factory()->create();

        $this->actingAs($attendee)->post(route('registrations.store', $event));
        $this->actingAs($attendee)->post(route('registrations.store', $event))->assertSessionHas('error', 'You are already registered for this event.');

        $this->assertSame(1, Registration::where('event_id', $event->id)->count());
    }

    public function test_registration_stops_at_the_maximum_headcount(): void
    {
        $event = Event::factory()->capacity(1)->create();
        Registration::factory()->for($event)->create();

        $this->actingAs(User::factory()->create())->post(route('registrations.store', $event))
            ->assertSessionHas('error', 'This event has reached its maximum capacity of 1.');
    }

    public function test_registration_respects_the_opening_date_and_the_cutoff(): void
    {
        $attendee = User::factory()->create();

        $notOpen = Event::factory()->registrationNotOpen()->create();
        $this->actingAs($attendee)->post(route('registrations.store', $notOpen))->assertSessionHas('error');

        $closed = Event::factory()->registrationClosed()->create();
        $this->actingAs($attendee)->post(route('registrations.store', $closed))->assertSessionHas('error');

        $this->assertSame(0, Registration::count());
    }

    public function test_a_cancelled_place_frees_a_seat_and_the_person_can_register_again(): void
    {
        $attendee = User::factory()->create();
        $event = Event::factory()->capacity(1)->create();
        $registration = Registration::factory()->for($event)->for($attendee)->create();

        $this->actingAs($attendee)->delete(route('registrations.destroy', $registration))->assertSessionHas('success');
        $this->assertSame(Registration::CANCELLED, $registration->fresh()->status);

        $this->actingAs($attendee)->post(route('registrations.store', $event))->assertSessionHas('success');
        $this->assertSame(Registration::CONFIRMED, $registration->fresh()->status);
    }

    public function test_attendees_download_their_own_entry_pass_but_nobody_elses(): void
    {
        $registration = Registration::factory()->create();

        $this->actingAs($registration->user)->get(route('registrations.pass', $registration))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs(User::factory()->create())->get(route('registrations.pass', $registration))->assertForbidden();
    }

    public function test_certificates_are_issued_only_after_the_event_to_people_who_were_checked_in(): void
    {
        $attended = Registration::factory()->for(Event::factory()->ended())->checkedIn()->create();
        $absent = Registration::factory()->for(Event::factory()->ended())->create();

        $this->actingAs($attended->user)->get(route('registrations.certificate', $attended))->assertOk();
        $this->actingAs($absent->user)->get(route('registrations.certificate', $absent))->assertForbidden();
    }
}
