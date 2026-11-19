<?php

namespace Tests\Feature;

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

    public function test_the_seeder_adds_one_venue_photo_per_city_when_online(): void
    {
        Storage::fake('local');
        $photo = file_get_contents(base_path('tests/Fixtures/venue.jpg'));
        Http::fake(['commons.wikimedia.org/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg'])]);

        $this->seed(DatabaseSeeder::class);

        foreach (['Singapore', 'Rotterdam', 'Houston', 'Manila', 'Cebu City', 'Davao City', 'Baguio City'] as $city) {
            $events = Event::where('city', $city)->get();
            $this->assertNotEmpty($events, $city);
            $this->assertTrue($events->every(fn (Event $e) => $e->cover_path && str_contains($e->cover_credit, 'Wikimedia Commons')), $city);
            Storage::disk('local')->assertExists($events->first()->cover_path);
        }
        Http::assertSentCount(7);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'Special:FilePath') && $request->hasHeader('User-Agent'));
    }

    public function test_the_philippines_chapter_has_events_across_the_country(): void
    {
        Storage::fake('local');
        Http::fake(fn () => throw new ConnectionException('No internet in tests.'));

        $this->seed(DatabaseSeeder::class);

        $cities = Event::whereHas('chapter', fn ($q) => $q->where('code', 'PH'))->pluck('city')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['Manila', 'Cebu City', 'Davao City', 'Baguio City'], $cities);
        $this->assertSame(0, Event::whereNotNull('cover_path')->count(), 'offline seeding falls back to drawn covers');
    }
}
