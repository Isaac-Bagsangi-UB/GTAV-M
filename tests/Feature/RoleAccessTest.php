<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page_from_every_private_area(): void
    {
        foreach (['/manage', '/my/registrations', '/gate', '/speaker/sessions', '/admin/users', '/admin/audit-log'] as $path) {
            $this->get($path)->assertRedirectToRoute('login');
        }
    }

    public function test_each_role_lands_on_its_own_home_screen(): void
    {
        $chapter = Chapter::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirectToRoute('manage.dashboard');
        $this->actingAs(User::factory()->chapterManager($chapter)->create())->get('/dashboard')->assertRedirectToRoute('manage.dashboard');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirectToRoute('registrations.index');
        $this->actingAs(User::factory()->speaker()->create())->get('/dashboard')->assertRedirectToRoute('speaker.sessions');
        $this->actingAs(User::factory()->gateStaff($chapter)->create())->get('/dashboard')->assertRedirectToRoute('gate.scanner');
    }

    public function test_attendees_cannot_enter_management_admin_or_gate_areas(): void
    {
        $attendee = User::factory()->create();

        foreach (['/manage', '/manage/reports', '/admin/users', '/gate'] as $path) {
            $this->actingAs($attendee)->get($path)->assertForbidden();
        }
    }

    public function test_chapter_managers_only_manage_events_in_their_own_chapter(): void
    {
        $ownChapter = Chapter::factory()->create();
        $otherEvent = Event::factory()->create();
        $ownEvent = Event::factory()->for($ownChapter)->create();
        $manager = User::factory()->chapterManager($ownChapter)->create();

        $this->actingAs($manager)->get(route('manage.events.show', $ownEvent))->assertOk();
        $this->actingAs($manager)->get(route('manage.events.show', $otherEvent))->assertForbidden();
        $this->actingAs($manager)->get(route('manage.events.export', $otherEvent))->assertForbidden();
        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    }

    public function test_events_created_by_a_manager_are_forced_into_their_own_chapter(): void
    {
        $ownChapter = Chapter::factory()->create();
        $otherChapter = Chapter::factory()->create();
        $manager = User::factory()->chapterManager($ownChapter)->create();

        $this->actingAs($manager)->post(route('manage.events.store'), [
            'chapter_id' => $otherChapter->id,
            'title' => 'Logistics Forum',
            'type' => 'summit',
            'venue' => 'Main Hall',
            'starts_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addMonth()->addHours(8)->format('Y-m-d\TH:i'),
            'registration_opens_at' => now()->format('Y-m-d\TH:i'),
            'registration_closes_at' => now()->addMonth()->subDay()->format('Y-m-d\TH:i'),
            'capacity' => 80,
            'ticket_price' => 50,
            'status' => 'published',
        ])->assertRedirect();

        $this->assertSame($ownChapter->id, Event::where('title', 'Logistics Forum')->value('chapter_id'));
    }
}
