<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendees_and_events_are_linked_many_to_many_through_the_registrations_pivot(): void
    {
        [$expo, $summit] = Event::factory()->count(2)->create();
        $jordan = User::factory()->create();
        $maria = User::factory()->create();

        $ticket = Registration::factory()->for($jordan)->for($expo)->checkedIn()->create();
        Registration::factory()->for($jordan)->for($summit)->create();
        Registration::factory()->for($maria)->for($expo)->create();

        $this->assertEqualsCanonicalizing([$expo->id, $summit->id], $jordan->registeredEvents->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$jordan->id, $maria->id], $expo->attendees->pluck('id')->all());

        $pivot = $jordan->registeredEvents->firstWhere('id', $expo->id)->pivot;
        $this->assertSame($ticket->reference_code, $pivot->reference_code);
        $this->assertNotNull($pivot->checked_in_at);
    }

    public function test_speakers_and_events_are_linked_many_to_many_with_a_topic(): void
    {
        $event = Event::factory()->create();
        $speaker = User::factory()->speaker()->create();

        $event->speakers()->attach($speaker, ['topic' => 'Paperless customs']);

        $this->assertSame('Paperless customs', $speaker->sessions()->sole()->pivot->topic);
    }

    public function test_a_chapter_reaches_its_tickets_through_its_events(): void
    {
        $chapter = Chapter::factory()->create();
        $events = Event::factory()->count(2)->for($chapter)->create();
        Registration::factory()->count(3)->for($events[0])->create();
        Registration::factory()->count(2)->for($events[1])->create();
        Registration::factory()->count(4)->create(); // other chapters

        $this->assertSame(5, $chapter->registrations()->count());
        $this->assertTrue($chapter->registrations->every(fn (Registration $r) => $events->contains('id', $r->event_id)));
    }
}
