<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Demo data: 4 chapters (including the Philippines), 5 roles, past and upcoming events with sub-events, plus a year
 * of factory-generated chapter history (over 2,000 records in total).
 * Every account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    /**
     * One freely licensed Wikimedia Commons photo per city: file name and photographer.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const COVER_PHOTOS = [
        'Singapore' => ['1 Singapore skyline.jpg', 'chensiyuan'],
        'Rotterdam' => ['Rotterdam-Skyline (cropped).jpg', 'acediscovery'],
        'Houston' => ['George R. Brown Convention Center - Houston, Texas - DSC01321.JPG', 'Daderot'],
        'Manila' => ['Manila Bay, Sunset, Philippines.jpg', 'Vyacheslav Argenberg'],
        'Cebu City' => ['Cebu City skyline, CCLEX Bridge west pylon drone view (Cebu City; 01-21-2025).jpg', 'Patrickroque01'],
        'Davao City' => ['Davao City skyline 02.jpg', 'Teemu Väisänen'],
        'Baguio City' => ['Baguio Convention Center, July 2025 (1).jpg', 'Ralff Nestor Nacor'],
    ];

    public function run(): void
    {
        // ---- Chapters and their staff -----------------------------------
        $admin = $this->user('Amara Okafor', 'admin@gtavm.test', User::ROLE_ADMIN, 'Global Trade Association');

        $sets = [];
        foreach ([
            ['Asia-Pacific Chapter', 'APAC', 'Asia-Pacific', 'Singapore', 'Marina Bay Convention Centre'],
            ['Europe Chapter', 'EUR', 'Europe, Middle East & Africa', 'Rotterdam', 'Maas Exhibition Halls'],
            ['Americas Chapter', 'AMER', 'North & South America', 'Houston', 'Gulf Coast Trade Center'],
            ['Philippines Chapter', 'PH', 'Philippines', 'Manila', 'SMX Convention Center Manila'],
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

        [$philippines, , , $philippinesGate] = $sets[3];
        $this->philippineRegions($philippines, $philippinesGate, $attendees, $speakers);

        $this->pastYear($sets, $speakers);
        $this->coverPhotos();
    }

    /**
     * Events of the Philippines Chapter outside Metro Manila: Cebu, Davao and Baguio.
     *
     * @param  Collection<int, User>  $attendees
     * @param  Collection<int, User>  $speakers
     */
    private function philippineRegions(Chapter $chapter, User $gate, Collection $attendees, Collection $speakers): void
    {
        $cebuStart = now()->addDays(40)->setTime(9, 0);
        $cebu = $this->event($chapter, [
            'title' => 'Cebu Maritime & Ports Summit',
            'type' => 'summit',
            'venue' => 'Waterfront Cebu City Hotel', 'city' => 'Cebu City',
            'starts_at' => $cebuStart, 'ends_at' => $cebuStart->copy()->setTime(18, 0),
            'registration_opens_at' => now()->subDays(10),
            'registration_closes_at' => $cebuStart->copy()->subDays(3),
            'capacity' => 120, 'ticket_price' => 150,
        ]);
        $this->fill($cebu, $attendees, 16, 0, null, null, true, 1);
        $cebu->speakers()->attach($speakers[1]->id, ['topic' => 'Inter-island shipping after the new port rules']);

        $davaoStart = now()->addDays(55)->setTime(9, 0);
        $davao = $this->event($chapter, [
            'title' => 'Davao Agri-Trade Expo',
            'type' => 'expo',
            'venue' => 'SMX Convention Center Davao', 'city' => 'Davao City',
            'starts_at' => $davaoStart, 'ends_at' => $davaoStart->copy()->addDays(2)->setTime(17, 0),
            'registration_opens_at' => now()->subDays(5),
            'registration_closes_at' => $davaoStart->copy()->subDays(1),
            'capacity' => 200, 'ticket_price' => 90,
        ]);
        $this->fill($davao, $attendees, 12, 0);
        $davao->speakers()->attach($speakers[0]->id, ['topic' => 'Getting Mindanao produce to export markets fresh']);

        $baguioStart = now()->addDays(32)->setTime(13, 0);
        $baguio = $this->event($chapter, [
            'title' => 'Workshop: Exporting Cordillera Coffee',
            'type' => 'workshop',
            'venue' => 'Baguio Convention and Cultural Center', 'city' => 'Baguio City',
            'starts_at' => $baguioStart, 'ends_at' => $baguioStart->copy()->setTime(17, 0),
            'registration_opens_at' => now()->subDays(12),
            'registration_closes_at' => $baguioStart->copy()->subDays(2),
            'capacity' => 40, 'ticket_price' => 45,
        ]);
        $this->fill($baguio, $attendees, 21, 0);
        $baguio->speakers()->attach($speakers[1]->id, ['topic' => 'From farm to green-bean buyer: grading and paperwork']);
    }

    /**
     * Real venue and city photos from Wikimedia Commons, one per city, downloaded once and kept in
     * storage/app/private/event-covers/demo so later reseeds need no internet. Without internet the
     * events keep the drawn gold covers. Each photo's licence is on its Wikimedia Commons page.
     */
    private function coverPhotos(): void
    {
        $offline = false;

        foreach (self::COVER_PHOTOS as $city => [$fileName, $photographer]) {
            $path = 'event-covers/demo/'.Str::slug($city).'.jpg';

            if (! Storage::disk('local')->exists($path)) {
                $image = $offline ? null : $this->downloadPhoto($fileName, $offline);

                if ($image === null) {
                    continue;
                }
                Storage::disk('local')->put($path, $image);
            }

            Event::where('city', $city)->update([
                'cover_path' => $path,
                'cover_credit' => 'Photo: '.$photographer.' / Wikimedia Commons ('.$fileName.')',
            ]);
        }

        if ($offline) {
            $this->command?->warn('Could not download the venue photos (no internet, or HTTPS blocked), so events use the drawn covers. Run the seeder again online to add them.');
        }
    }

    /** Fetches a 1280-pixel-wide copy of a Wikimedia Commons file. Returns null if it is unavailable. */
    private function downloadPhoto(string $fileName, bool &$offline): ?string
    {
        try {
            $response = Http::withUserAgent('GTAV-M demo seeder (Laravel school project)')
                ->connectTimeout(5)
                ->timeout(25)
                ->get('https://commons.wikimedia.org/wiki/Special:FilePath/'.rawurlencode(str_replace(' ', '_', $fileName)), ['width' => 1280]);
        } catch (Throwable) {
            // No internet, a firewall, or a PHP install without HTTPS certificates: keep the drawn covers.
            $offline = true;

            return null;
        }

        $image = $response->successful() ? $response->body() : '';
        $info = $image !== '' ? getimagesizefromstring($image) : false;

        return $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) ? $image : null;
    }

    /**
     * A year of finished chapter events built entirely with model factories, so the reports,
     * the participation matrix and the certificates have realistic volume behind them.
     * Adds roughly 2,000 records: 210 attendees, 12 events, about 850 tickets and 700 gate scans.
     *
     * @param  array<int, array{0: Chapter, 1: string, 2: string, 3: User}>  $sets
     * @param  Collection<int, User>  $speakers
     */
    private function pastYear(array $sets, Collection $speakers): void
    {
        $crowd = User::factory()->count(210)->create();

        $calendar = [
            // months ago, title, type, days, capacity, ticket price, keynote topic
            [11, '%s Import & Export Forum', 'summit', 1, 80, 150, 'Reading the new rules of origin'],
            [8, '%s Logistics Technology Expo', 'expo', 2, 150, 200, 'Automation on the warehouse floor'],
            [5, 'Workshop: Incoterms in Practice (%s)', 'workshop', 1, 30, 90, 'Who pays when the container is late'],
            [3, '%s Trade Finance Summit', 'summit', 1, 120, 280, 'Letters of credit without the paperwork'],
        ];

        foreach ($sets as [$chapter, $city, $venue, $gate]) {
            foreach ($calendar as $i => [$monthsAgo, $title, $type, $days, $capacity, $price, $topic]) {
                $event = Event::factory()
                    ->for($chapter)
                    ->heldOn(now()->subMonths($monthsAgo)->addDays(fake()->numberBetween(0, 20))->setTime(9, 0), $days)
                    ->create([
                        'title' => sprintf($title, $city),
                        'type' => $type,
                        'venue' => $venue,
                        'city' => $city,
                        'capacity' => $capacity,
                        'ticket_price' => $price,
                        'description' => 'Hosted by the '.$chapter->name.'. Practitioners share what is working now, with time to meet suppliers and peers.',
                    ]);
                $event->speakers()->attach($speakers[$i % $speakers->count()]->id, ['topic' => $topic]);

                $this->ticketsFor($event, $crowd, $gate);
            }
        }
    }

    /**
     * Sells between 60% and 90% of an event's seats to people from the crowd, admits most of them
     * at the gate, records a few repeat scans, and adds a handful of cancelled tickets.
     *
     * @param  Collection<int, User>  $crowd
     */
    private function ticketsFor(Event $event, Collection $crowd, User $gate): void
    {
        $sold = (int) round($event->capacity * fake()->randomFloat(2, 0.6, 0.9));
        $people = $crowd->shuffle();
        $buyers = $people->take($sold)->values();
        $cancellers = $people->slice($sold, fake()->numberBetween(1, 4))->values();

        $tickets = Registration::factory()
            ->count($buyers->count())
            ->for($event)
            ->registeredDuring($event)
            ->sequence(fn (Sequence $sequence) => ['user_id' => $buyers[$sequence->index]->id])
            ->create();

        foreach ($tickets as $ticket) {
            if (fake()->boolean(82)) {
                $scannedAt = $event->starts_at->copy()->addMinutes(fake()->numberBetween(-30, 150));
                CheckIn::factory()->admits($ticket, $gate, $scannedAt)->create();

                if (fake()->boolean(6)) {
                    CheckIn::factory()->admits($ticket, $gate, $scannedAt->copy()->addSeconds(4))->refused()->create();
                }
            }
        }

        Registration::factory()
            ->count($cancellers->count())
            ->for($event)
            ->registeredDuring($event)
            ->cancelled()
            ->sequence(fn (Sequence $sequence) => ['user_id' => $cancellers[$sequence->index]->id])
            ->create();
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
