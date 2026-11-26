<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_over_a_thousand_consistent_records(): void
    {
        Storage::fake('local');
        Http::fake(fn () => throw new ConnectionException('No internet in tests.'));

        $this->seed(DatabaseSeeder::class);

        $total = collect(['users', 'chapters', 'events', 'registrations', 'check_ins', 'event_speaker', 'materials'])
            ->sum(fn (string $table) => DB::table($table)->count());
        $this->assertGreaterThanOrEqual(1000, $total);

        // Capacity rule holds for every seeded event.
        Event::withCount('confirmedRegistrations')->get()
            ->each(fn (Event $e) => $this->assertLessThanOrEqual($e->capacity, $e->confirmed_registrations_count, $e->title));

        // Every ticket marked as checked in has a matching successful gate scan.
        $this->assertSame(0, Registration::whereNotNull('checked_in_at')
            ->whereDoesntHave('checkIns', fn ($q) => $q->where('result', 'admitted'))
            ->count());

        // Nobody holds two live tickets for the same event.
        $this->assertSame(0, Registration::where('status', Registration::CONFIRMED)
            ->select('user_id', 'event_id')->groupBy('user_id', 'event_id')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_the_seeder_creates_the_eight_regional_chapters(): void
    {
        Storage::fake('local');
        Http::fake(fn () => throw new ConnectionException('No internet in tests.'));

        $this->seed(DatabaseSeeder::class);

        $this->assertSame([
            'North America Chapter', 'South America Chapter', 'Europe Chapter', 'North Asia Chapter',
            'West Asia Chapter', 'East Asia Chapter', 'South Asia Chapter', 'South East Asia Chapter',
        ], Chapter::orderBy('id')->pluck('name')->all());

        // Every chapter has an organizer and staff, and hosts events.
        Chapter::withCount(['managers', 'gateStaff', 'events'])->get()->each(function (Chapter $chapter) {
            $this->assertSame(1, $chapter->managers_count, $chapter->name);
            $this->assertSame(2, $chapter->gate_staff_count, $chapter->name);
            $this->assertGreaterThan(0, $chapter->events_count, $chapter->name);
        });
    }

    public function test_the_seeder_gives_every_event_a_photo_of_its_venue_when_online(): void
    {
        Storage::fake('local');
        $photo = file_get_contents(base_path('tests/Fixtures/venue.jpg'));
        Http::fake(['commons.wikimedia.org/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Event::whereNull('cover_path')->count());
        $this->assertSame(0, Event::whereNotNull('cover_credit')->count(), 'no photo credits are shown');

        // Events in the venue or one of its halls show the venue building; workshops in a room show a meeting room.
        $houston = 'event-covers/demo/houston-convention-center-exterior-1.jpg';
        $this->assertSame($houston, Event::where('title', 'Houston Supply Chain Expo')->value('cover_path'));
        $this->assertSame($houston, Event::where('title', 'Houston Members Briefing')->value('cover_path'));
        $this->assertStringContainsString('unsplash', Event::where('venue', 'like', '%, Room %')->value('cover_path'));

        // Events shown side by side never share a picture: one briefing and one workshop per chapter.
        $this->assertCount(8, Event::where('title', 'like', '% Members Briefing')->pluck('cover_path')->unique());
        $this->assertCount(8, Event::where('venue', 'like', '%, Room 2A')->pluck('cover_path')->unique());
        $this->assertCount(8, Event::where('venue', 'like', '%, Room 3C')->pluck('cover_path')->unique());

        Event::pluck('cover_path')->unique()->each(fn (string $path) => Storage::disk('local')->assertExists($path));

        // Each photo is downloaded only once: 11 venues and 8 meeting rooms.
        Http::assertSentCount(19);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'Special:FilePath') && $request->hasHeader('User-Agent'));
    }

    public function test_the_south_east_asia_chapter_has_events_across_the_philippines(): void
    {
        Storage::fake('local');
        Http::fake(fn () => throw new ConnectionException('No internet in tests.'));

        $this->seed(DatabaseSeeder::class);

        $cities = Event::whereHas('chapter', fn ($q) => $q->where('code', 'SEA'))->pluck('city')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['Manila', 'Cebu City', 'Davao City', 'Baguio City'], $cities);
        $this->assertSame(0, Event::whereNotNull('cover_path')->count(), 'offline seeding falls back to drawn covers');
    }
}
