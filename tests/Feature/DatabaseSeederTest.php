<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_over_a_thousand_consistent_records(): void
    {
        Storage::fake('local');

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
}
