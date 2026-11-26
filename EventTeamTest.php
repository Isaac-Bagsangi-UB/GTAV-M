<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_event_page_shows_its_organizers_speakers_and_staff(): void
    {
        $event = Event::factory()->create();
        User::factory()->chapterManager($event->chapter)->create(['name' => 'Maria Santos', 'job_title' => 'Event Organizer']);
        User::factory()->gateStaff($event->chapter)->create(['name' => 'Paolo Villanueva', 'job_title' => 'Gate Supervisor']);
        $speaker = User::factory()->speaker()->create(['name' => 'Dr. Helena Vos']);
        $event->speakers()->attach($speaker->id, ['topic' => 'Paperless trade corridors']);

        // People from another chapter do not appear.
        User::factory()->chapterManager(Event::factory()->create()->chapter)->create(['name' => 'Someone Elsewhere']);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSeeInOrder(['Organizers', 'Maria Santos', 'Event Organizer', 'Speakers', 'Dr. Helena Vos', 'Paperless trade corridors', 'Staff', 'Paolo Villanueva', 'Gate Supervisor'])
            ->assertDontSee('Someone Elsewhere');
    }

    public function test_an_empty_part_of_the_team_is_shown_as_to_be_announced(): void
    {
        $event = Event::factory()->create();

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSeeInOrder(['Organizers', 'To be announced.', 'Speakers', 'To be announced.', 'Staff', 'To be announced.']);
    }
}
