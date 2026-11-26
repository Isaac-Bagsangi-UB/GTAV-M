<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function photo(string $name = 'venue.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(base_path('tests/Fixtures/'.$name)));
    }

    /**
     * @return array<string, mixed>
     */
    private function form(Event $event, array $extra = []): array
    {
        return [
            'title' => $event->title, 'type' => $event->type, 'venue' => $event->venue, 'city' => $event->city,
            'starts_at' => $event->starts_at->format('Y-m-d H:i'), 'ends_at' => $event->ends_at->format('Y-m-d H:i'),
            'registration_opens_at' => $event->registration_opens_at->format('Y-m-d H:i'),
            'registration_closes_at' => $event->registration_closes_at->format('Y-m-d H:i'),
            'capacity' => $event->capacity, 'ticket_price' => $event->ticket_price, 'status' => $event->status,
        ] + $extra;
    }

    public function test_a_manager_uploads_a_venue_photo_that_shows_on_the_card_and_event_page(): void
    {
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)
            ->put(route('manage.events.update', $event), $this->form($event, ['cover' => $this->photo(), 'cover_credit' => 'Photo: Jane Cruz']))
            ->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertStringStartsWith("event-covers/{$event->id}/", $event->cover_path);
        Storage::disk('local')->assertExists($event->cover_path);

        $this->get(route('events.index'))->assertSee($event->coverUrl(), false);
        $this->get(route('events.show', $event))->assertSee($event->coverUrl(), false)->assertDontSee('Photo: Jane Cruz');
        $this->get($event->coverUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_photos_must_be_real_images_of_a_usable_size(): void
    {
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)->put(route('manage.events.update', $event), $this->form($event, ['cover' => $this->photo('venue-small.jpg')]))
            ->assertSessionHasErrors('cover');
        $this->actingAs($manager)->put(route('manage.events.update', $event), $this->form($event, [
            'cover' => UploadedFile::fake()->createWithContent('venue.jpg', '<?php echo "not an image";'),
        ]))->assertSessionHasErrors('cover');

        $this->assertNull($event->fresh()->cover_path);
    }

    public function test_replacing_or_removing_a_photo_deletes_the_old_file(): void
    {
        $event = Event::factory()->create();
        $manager = User::factory()->chapterManager($event->chapter)->create();

        $this->actingAs($manager)->put(route('manage.events.update', $event), $this->form($event, ['cover' => $this->photo()]));
        $first = $event->fresh()->cover_path;

        $this->actingAs($manager)->put(route('manage.events.update', $event), $this->form($event, ['cover' => $this->photo()]));
        $second = $event->fresh()->cover_path;
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->actingAs($manager)->put(route('manage.events.update', $event), $this->form($event, ['remove_cover' => '1']));
        $this->assertNull($event->fresh()->cover_path);
        Storage::disk('local')->assertMissing($second);
    }

    public function test_a_photo_shared_by_several_events_is_kept_until_the_last_one_lets_go(): void
    {
        Storage::disk('local')->put('event-covers/demo/manila.jpg', 'photo');
        [$first, $second] = Event::factory()->count(2)->create();
        Event::whereKey([$first->id, $second->id])->update(['cover_path' => 'event-covers/demo/manila.jpg']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('manage.events.update', $first), $this->form($first->fresh(), ['chapter_id' => $first->chapter_id, 'remove_cover' => '1']));

        Storage::disk('local')->assertExists('event-covers/demo/manila.jpg');
        $this->assertSame('event-covers/demo/manila.jpg', $second->fresh()->cover_path);
    }

    public function test_events_without_a_photo_get_the_drawn_cover(): void
    {
        $event = Event::factory()->create(['city' => 'Baguio City']);

        $this->get(route('events.show', $event))->assertOk()->assertSee('BAGUIO CITY')->assertSee('cover-watermark', false);
        $this->get(route('events.cover', $event))->assertNotFound();
    }

    public function test_draft_event_photos_are_hidden_from_the_public(): void
    {
        Storage::disk('local')->put('event-covers/1/hall.jpg', 'photo');
        $draft = Event::factory()->draft()->create();
        $draft->forceFill(['cover_path' => 'event-covers/1/hall.jpg'])->save();

        $this->get(route('events.cover', $draft))->assertNotFound();
        $this->actingAs(User::factory()->chapterManager($draft->chapter)->create())->get(route('events.cover', $draft))->assertOk();
    }

    public function test_the_api_includes_the_cover_address(): void
    {
        $event = Event::factory()->create();

        $this->getJson('/api/v1/events/'.$event->id)->assertOk()->assertJsonPath('data.cover_url', null);

        Storage::disk('local')->put('event-covers/x/hall.jpg', 'photo');
        $event->forceFill(['cover_path' => 'event-covers/x/hall.jpg', 'cover_credit' => 'Photo: Jane Cruz'])->save();

        $this->getJson('/api/v1/events/'.$event->id)
            ->assertJsonPath('data.cover_url', $event->coverUrl())
            ->assertJsonPath('data.cover_credit', 'Photo: Jane Cruz');
    }
}
