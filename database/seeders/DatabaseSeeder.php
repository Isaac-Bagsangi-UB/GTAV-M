<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'admin@gtav.test'], ['name' => 'Global Administrator', 'password' => 'password', 'role' => 'admin', 'company' => 'GTAV Association']);
        $manager = User::updateOrCreate(['email' => 'manager@gtav.test'], ['name' => 'Regional Manager', 'password' => 'password', 'role' => 'manager', 'company' => 'GTAV Association']);
        User::updateOrCreate(['email' => 'attendee@gtav.test'], ['name' => 'Corporate Attendee', 'password' => 'password', 'role' => 'attendee', 'company' => 'Northstar Trading']);
        User::updateOrCreate(['email' => 'gate@gtav.test'], ['name' => 'Venue Gate Staff', 'password' => 'password', 'role' => 'gate_staff']);

        $chapter = Chapter::updateOrCreate(['name' => 'Northern Luzon Chapter'], ['location' => 'Baguio City', 'manager_id' => $manager->id]);
        Event::updateOrCreate(['name' => 'Global Trade Summit 2026'], [
            'chapter_id' => $chapter->id, 'created_by' => $admin->id, 'type' => 'Summit',
            'description' => 'The annual meeting point for the people moving trade forward.', 'venue' => 'Baguio Convention Center',
            'starts_at' => now()->addDays(18)->setTime(9, 0), 'ends_at' => now()->addDays(18)->setTime(17, 0),
            'registration_cutoff' => now()->addDays(16)->setTime(23, 59), 'capacity' => 320, 'ticket_price' => 1800,
        ]);
        Event::updateOrCreate(['name' => 'Export Readiness Workshop'], [
            'chapter_id' => $chapter->id, 'created_by' => $manager->id, 'type' => 'Workshop',
            'description' => 'Practical sessions for teams preparing to enter new markets.', 'venue' => 'SIT Innovation Lab',
            'starts_at' => now()->addDays(31)->setTime(13, 0), 'ends_at' => now()->addDays(31)->setTime(17, 0),
            'registration_cutoff' => now()->addDays(29)->setTime(23, 59), 'capacity' => 80, 'ticket_price' => 650,
        ]);
    }
}
