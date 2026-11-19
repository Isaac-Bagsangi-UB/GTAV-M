<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Material;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaterialFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_a_chapter_manager_uploads_a_file_to_private_storage(): void
    {
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)->post(route('materials.store', $event), [
            'title' => 'Keynote slides',
            'file' => UploadedFile::fake()->create('keynote.pdf', 300, 'application/pdf'),
        ])->assertSessionHas('success');

        $material = $event->materials()->sole();
        $this->assertSame('keynote.pdf', $material->original_name);
        $this->assertSame($manager->id, $material->uploaded_by);
        $this->assertStringStartsWith("materials/{$event->id}/", $material->path);
        Storage::disk('local')->assertExists($material->path);
    }

    public function test_uploads_are_checked_for_type_and_size(): void
    {
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)->post(route('materials.store', $event), [
            'title' => 'Too big',
            'file' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($manager)->post(route('materials.store', $event), [
            'title' => 'Program',
            'file' => UploadedFile::fake()->create('setup.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_attendees_and_other_chapters_cannot_upload(): void
    {
        $event = Event::factory()->create();
        $file = fn () => ['title' => 'Slides', 'file' => UploadedFile::fake()->create('slides.pdf', 10, 'application/pdf')];

        $this->actingAs(User::factory()->create())->post(route('materials.store', $event), $file())->assertForbidden();
        $this->actingAs(User::factory()->chapterManager(Event::factory()->create()->chapter)->create())
            ->post(route('materials.store', $event), $file())->assertForbidden();

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_registered_attendees_download_the_original_file(): void
    {
        $material = Material::factory()->create(['title' => 'Venue map']);
        $ticket = Registration::factory()->for($material->event)->create();

        $this->actingAs($ticket->user)
            ->get(route('materials.download', $material))
            ->assertOk()
            ->assertDownload($material->original_name);
    }

    public function test_people_without_a_valid_ticket_cannot_download(): void
    {
        $material = Material::factory()->create();
        $cancelled = Registration::factory()->for($material->event)->cancelled()->create();

        $this->get(route('materials.download', $material))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create())->get(route('materials.download', $material))->assertForbidden();
        $this->actingAs($cancelled->user)->get(route('materials.download', $material))->assertForbidden();
    }

    public function test_a_missing_file_returns_not_found_instead_of_an_error(): void
    {
        $material = Material::factory()->create();
        $ticket = Registration::factory()->for($material->event)->create();
        Storage::disk('local')->delete($material->path);

        $this->actingAs($ticket->user)->get(route('materials.download', $material))->assertNotFound();
    }
}
