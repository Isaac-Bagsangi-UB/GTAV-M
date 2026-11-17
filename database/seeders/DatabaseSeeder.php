<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo data: 3 chapters, 5 roles, past and upcoming events with sub-events.
 * Every account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Chapters and their staff -----------------------------------
        $admin = $this->user('Amara Okafor', 'admin@gtavm.test', User::ROLE_ADMIN, 'Global Trade Association');

        $sets = [];
        foreach ([
            ['Asia-Pacific Chapter', 'APAC', 'Asia-Pacific', 'Singapore', 'Marina Bay Convention Centre'],
            ['Europe Chapter', 'EUR', 'Europe, Middle East & Africa', 'Rotterdam', 'Maas Exhibition Halls'],
            ['Americas Chapter', 'AMER', 'North & South America', 'Houston', 'Gulf Coast Trade Center'],
        ] as [$name, $code, $region, $city, $venue]) {
            $chapter = Chapter::create(['name' => $name, 'code' => $code, 'region' => $region]);
            $slug = strtolower($code);

            $this->user("{$code} Chapter Manager", "manager.{$slug}@gtavm.test", User::ROLE_MANAGER, 'Global Trade Association', $chapter->id);
            $gate = $this->user("{$code} Gate Staff", "gate.{$slug}@gtavm.test", User::ROLE_GATE, 'Venue Operations', $chapter->id);

            $sets[] = [$chapter, $city, $venue, $gate];
        }

        $speakers = collect([
            $this->user('Dr. Helena Vos', 'speaker@gtavm.test', User::ROLE_SPEAKER, 'Northsea Logistics Institute'),
            $this->user('Rafael Mendoza', 'speaker2@gtavm.test', User::ROLE_SPEAKER, 'Pacific Customs Advisory'),
        ]);

        // ---- Attendees --------------------------------------------------
        $demoAttendee = $this->user('Jordan Reyes', 'attendee@gtavm.test', User::ROLE_ATTENDEE, 'Reyes Freight Solutions');

        $first = ['Aiko', 'Liam', 'Priya', 'Mateo', 'Sofia', 'Chen', 'Noah', 'Fatima', 'Lucas', 'Mei', 'Omar', 'Elena', 'Kofi', 'Hannah', 'Diego', 'Yuki', 'Ivan', 'Leila', 'Tomas', 'Grace', 'Arjun', 'Nina', 'Samuel', 'Zara', 'Pablo', 'Ingrid', 'Kenji', 'Marta', 'Daniel', 'Aylin'];
        $last = ['Tanaka', 'Murphy', 'Sharma', 'Garcia', 'Rossi', 'Wei', 'Jensen', 'Hassan', 'Silva', 'Lin', 'Farouk', 'Petrova', 'Mensah', 'Schmidt', 'Castillo', 'Sato', 'Novak', 'Rahimi', 'Lopez', 'Kim', 'Patel', 'Larsen', 'Cohen', 'Ahmed', 'Navarro', 'Berg', 'Mori', 'Kowalska', 'Stone', 'Demir'];
        $companies = ['Harbor & Wells Shipping', 'Orient Components Ltd', 'Summit Polymers', 'BlueLine Cargo', 'Aurora Machine Works', 'Delta Agri Exports', 'Keystone Metals', 'Verde Packaging', 'Atlas Cold Chain', 'Nimbus Trade Finance'];

        $attendees = collect([$demoAttendee]);
        foreach ($first as $i => $name) {
            $attendees->push($this->user(
                $name.' '.$last[$i],
                strtolower($name.'.'.$last[$i]).'@example.com',
                User::ROLE_ATTENDEE,
                $companies[$i % count($companies)]
            ));
        }

        // ---- Events -----------------------------------------------------
        foreach ($sets as [$chapter, $city, $venue, $gate]) {
            $isApac = $chapter->code === 'APAC';

            // 1. A finished expo: produces check-in history and certificates.
            $pastStart = now()->subDays(40)->setTime(9, 0);
            $past = $this->event($chapter, [
                'title' => "{$city} Regional Trade Expo ".$pastStart->year,
                'type' => 'expo',
                'venue' => $venue, 'city' => $city,
                'starts_at' => $pastStart, 'ends_at' => $pastStart->copy()->addDays(2)->setTime(17, 0),
                'registration_opens_at' => $pastStart->copy()->subDays(60),
                'registration_closes_at' => $pastStart->copy()->subDays(2),
                'capacity' => 40, 'ticket_price' => 180,
            ]);
            $this->fill($past, $attendees, 22, 0.85, $gate, $isApac ? $demoAttendee : null, true, 2);
            $past->speakers()->attach($speakers[0]->id, ['topic' => 'Opening keynote: resilient supply chains']);

            // 2. An upcoming expo with registration open, plus two workshops inside it.
            $expoStart = now()->addDays(25)->setTime(9, 0);
            $expo = $this->event($chapter, [
                'title' => "{$city} Supply Chain Expo",
                'type' => 'expo',
                'venue' => $venue, 'city' => $city,
                'starts_at' => $expoStart, 'ends_at' => $expoStart->copy()->addDays(2)->setTime(17, 0),
                'registration_opens_at' => now()->subDays(20),
                'registration_closes_at' => $expoStart->copy()->subDays(1),
                'capacity' => 60, 'ticket_price' => 220,
            ]);
            $this->fill($expo, $attendees, 18, 0, null, $isApac ? $demoAttendee : null, true, 3);

            $workshopA = $this->event($chapter, [
                'parent_id' => $expo->id,
                'title' => 'Workshop: Customs Compliance Essentials',
                'type' => 'workshop',
                'venue' => $venue.', Room 2A', 'city' => $city,
                'starts_at' => $expoStart->copy()->setTime(13, 0), 'ends_at' => $expoStart->copy()->setTime(16, 0),
                'registration_opens_at' => now()->subDays(20),
                'registration_closes_at' => $expoStart->copy()->subDays(1),
                'capacity' => 12, 'ticket_price' => 60,
            ]);
            $this->fill($workshopA, $attendees, 11, 0); // one seat left: try the capacity rule
            $workshopA->speakers()->attach($speakers[1]->id, ['topic' => 'Getting tariff classification right the first time']);

            $workshopB = $this->event($chapter, [
                'parent_id' => $expo->id,
                'title' => 'Workshop: Cold Chain Monitoring Hands-On',
                'type' => 'workshop',
                'venue' => $venue.', Room 3C', 'city' => $city,
                'starts_at' => $expoStart->copy()->addDay()->setTime(10, 0), 'ends_at' => $expoStart->copy()->addDay()->setTime(12, 30),
                'registration_opens_at' => now()->subDays(20),
                'registration_closes_at' => $expoStart->copy()->subDays(1),
                'capacity' => 8, 'ticket_price' => 75,
            ]);
            $this->fill($workshopB, $attendees, 8, 0); // sold out
            $workshopB->speakers()->attach($speakers[0]->id, ['topic' => 'Sensors, alerts and what to do at 2 AM']);

            // 3. A summit whose registration has not opened yet (cutoff window rule).
            $summitStart = now()->addDays(75)->setTime(9, 30);
            $summit = $this->event($chapter, [
                'title' => "{$chapter->code} Technical Summit on Trade Digitalization",
                'type' => 'summit',
                'venue' => $venue, 'city' => $city,
                'starts_at' => $summitStart, 'ends_at' => $summitStart->copy()->setTime(18, 0),
                'registration_opens_at' => now()->addDays(10),
                'registration_closes_at' => $summitStart->copy()->subDays(7),
                'capacity' => 150, 'ticket_price' => 320,
            ]);
            $summit->speakers()->attach($speakers[0]->id, ['topic' => 'Keynote: paperless trade corridors']);

            // 4. An event happening today, so the gate scanner can be demonstrated right away.
            $today = $this->event($chapter, [
                'title' => "{$city} Members Briefing",
                'type' => 'summit',
                'venue' => $venue.', Hall B', 'city' => $city,
                'starts_at' => now()->subHours(2), 'ends_at' => now()->addHours(10),
                'registration_opens_at' => now()->subDays(15),
                'registration_closes_at' => now()->addHours(9),
                'capacity' => 30, 'ticket_price' => 0,
            ]);
            $this->fill($today, $attendees, 14, 0.3, $gate, $isApac ? $demoAttendee : null, false);

            // A sample private file attendees can download once registered.
            $path = "materials/{$expo->id}/exhibitor-guide.txt";
            $body = "{$expo->title}\nExhibitor & attendee guide (sample file)\n\nDoors open 08:30. Bring your PDF entry pass; the QR code is scanned at the gate.\n";
            Storage::disk('local')->put($path, $body);
            $expo->materials()->create([
                'uploaded_by' => $admin->id,
                'title' => 'Exhibitor & attendee guide',
                'path' => $path,
                'original_name' => 'exhibitor-guide.txt',
                'size' => strlen($body),
            ]);
        }
    }

    private function user(string $name, string $email, string $role, ?string $company = null, ?int $chapterId = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password', // hashed by the model cast
            'role' => $role,
            'company' => $company,
            'chapter_id' => $chapterId,
        ]);
    }

    private function event(Chapter $chapter, array $attributes): Event
    {
        return Event::create($attributes + [
            'chapter_id' => $chapter->id,
            'status' => 'published',
            'description' => 'Hosted by the '.$chapter->name.'. Meet exhibitors, hear from practitioners and take part in hands-on sessions built for corporate trade teams.',
        ]);
    }

    /**
     * Registers a random set of attendees for an event, dated realistically inside its registration window.
     *
     * @param  float  $checkInRate  share of them already scanned in at the gate
     * @param  bool  $checkInAlways  whether $always is checked in too
     * @param  int  $cancelled  extra attendees who registered and later cancelled
     */
    private function fill(Event $event, $attendees, int $count, float $checkInRate, ?User $gate = null, ?User $always = null, bool $checkInAlways = true, int $cancelled = 0): void
    {
        $others = $attendees->reject(fn ($u) => $always && $u->id === $always->id)->shuffle();
        $pool = $others->take($always ? $count - 1 : $count);
        if ($always) {
            $pool->push($always);
        }
        $cancellers = $others->slice($pool->count())->take($cancelled);

        // Registrations happen between the window opening and the earlier of its cutoff and today.
        $windowStart = $event->registration_opens_at->copy();
        $windowEnd = $event->registration_closes_at->lt(now()) ? $event->registration_closes_at->copy() : now();
        $span = max(60, $windowStart->diffInMinutes($windowEnd));
        $dateIn = fn () => $windowStart->copy()->addMinutes(mt_rand(0, (int) $span));

        foreach ($pool as $attendee) {
            $isAlways = $always && $attendee->id === $always->id;
            $checkedIn = $gate && ($isAlways ? ($checkInAlways && $checkInRate > 0) : mt_rand(1, 100) <= $checkInRate * 100);
            $scannedAt = $checkedIn ? $event->starts_at->copy()->addMinutes(mt_rand(5, 110)) : null;
            $registeredAt = $dateIn();

            $registration = Registration::create([
                'user_id' => $attendee->id,
                'event_id' => $event->id,
                'reference_code' => RegistrationService::newReference(),
                'qr_token' => RegistrationService::newToken(),
                'status' => Registration::CONFIRMED,
                'amount_paid' => $event->ticket_price,
                'checked_in_at' => $scannedAt,
            ]);
            $registration->forceFill(['created_at' => $registeredAt, 'updated_at' => $scannedAt ?? $registeredAt])->saveQuietly();

            if ($checkedIn) {
                $log = new CheckIn([
                    'registration_id' => $registration->id,
                    'event_id' => $event->id,
                    'scanned_by' => $gate->id,
                    'result' => 'admitted',
                    'code_scanned' => $registration->reference_code,
                    'device' => 'seed',
                ]);
                $log->created_at = $scannedAt;
                $log->updated_at = $scannedAt;
                $log->save();
            }
        }

        foreach ($cancellers as $attendee) {
            $registeredAt = $dateIn();
            $cancelledAt = $registeredAt->copy()->addDays(mt_rand(1, 5));
            if ($cancelledAt->gt($windowEnd)) {
                $cancelledAt = $windowEnd->copy();
            }

            $registration = Registration::create([
                'user_id' => $attendee->id,
                'event_id' => $event->id,
                'reference_code' => RegistrationService::newReference(),
                'qr_token' => RegistrationService::newToken(),
                'status' => Registration::CANCELLED,
                'amount_paid' => $event->ticket_price,
                'cancelled_at' => $cancelledAt,
            ]);
            $registration->forceFill(['created_at' => $registeredAt, 'updated_at' => $cancelledAt])->saveQuietly();
        }
    }
}
