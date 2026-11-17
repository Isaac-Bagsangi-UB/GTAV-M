<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_chapter_with_staff_or_events_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $withStaff = Chapter::factory()->create();
        User::factory()->gateStaff($withStaff)->create();
        $withEvents = Chapter::factory()->has(Event::factory())->create();
        $empty = Chapter::factory()->create();

        $this->actingAs($admin)->delete(route('admin.chapters.destroy', $withStaff))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.chapters.destroy', $withEvents))->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('admin.chapters.destroy', $empty))->assertSessionHas('success');

        $this->assertModelExists($withStaff);
        $this->assertModelExists($withEvents);
        $this->assertModelMissing($empty);
    }

    public function test_an_event_with_confirmed_registrations_cannot_be_deleted(): void
    {
        $registration = Registration::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('manage.events.destroy', $registration->event))
            ->assertSessionHas('error');

        $this->assertModelExists($registration->event);
    }

    public function test_capacity_cannot_be_lowered_below_tickets_already_sold(): void
    {
        $event = Event::factory()->capacity(10)->create();
        Registration::factory()->count(3)->for($event)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('manage.events.update', $event), [
                'chapter_id' => $event->chapter_id,
                'title' => $event->title,
                'type' => $event->type,
                'venue' => $event->venue,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
                'registration_opens_at' => $event->registration_opens_at->format('Y-m-d\TH:i'),
                'registration_closes_at' => $event->registration_closes_at->format('Y-m-d\TH:i'),
                'capacity' => 2,
                'ticket_price' => 150,
                'status' => 'published',
            ])
            ->assertSessionHasErrors('capacity');

        $this->assertSame(10, $event->fresh()->capacity);
    }

    public function test_administrators_cannot_remove_their_own_administrator_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => User::ROLE_ATTENDEE,
        ])->assertSessionHas('error');

        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_materials_are_stored_privately_and_executable_files_are_rejected(): void
    {
        Storage::fake('local');
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)->post(route('materials.store', $event), [
            'title' => 'Slides',
            'file' => UploadedFile::fake()->create('slides.pdf', 120, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->actingAs($manager)->post(route('materials.store', $event), [
            'title' => 'Script',
            'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
        ])->assertSessionHasErrors('file');

        $material = $event->materials()->sole();
        Storage::disk('local')->assertExists($material->path);

        $this->actingAs(User::factory()->create())->get(route('materials.download', $material))->assertForbidden();
        $registered = Registration::factory()->for($event)->create();
        $this->actingAs($registered->user)->get(route('materials.download', $material))->assertOk();
    }
}
