<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GateCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pass_admits_once_and_a_second_scan_reports_already_checked_in(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $registration = Registration::factory()->for($event)->create();
        Sanctum::actingAs(User::factory()->gateStaff($event->chapter)->create());

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $registration->qr_token])
            ->assertOk()
            ->assertJson(['authorized' => true, 'result' => 'admitted']);

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $registration->reference_code])
            ->assertConflict()
            ->assertJson(['authorized' => false, 'result' => 'duplicate']);

        $this->assertNotNull($registration->fresh()->checked_in_at);
    }

    public function test_the_gate_screen_tells_staff_what_to_do_in_plain_words(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $registration = Registration::factory()->for($event)->create();
        Sanctum::actingAs(User::factory()->gateStaff($event->chapter)->create());

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $registration->reference_code])
            ->assertJson(['instruction' => 'LET IN', 'tone' => 'ok']);

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $registration->reference_code])
            ->assertJson(['instruction' => 'ALREADY INSIDE', 'tone' => 'warn'])
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Entered at ') && str_contains($message, 'Check their ID'));

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => 'EX-UNKNOWN0'])
            ->assertJson(['instruction' => 'DO NOT LET IN', 'tone' => 'bad', 'message' => 'No ticket matches this code. Send the guest to the help desk.']);
    }

    public function test_repeat_scans_of_one_ticket_show_as_one_line_with_a_count(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $jordan = Registration::factory()->for($event)->for(User::factory()->state(['name' => 'Jordan Reyes']))->create();
        $sam = Registration::factory()->for($event)->for(User::factory()->state(['name' => 'Sam Cruz']))->create();
        Sanctum::actingAs(User::factory()->gateStaff($event->chapter)->create());

        foreach ([$jordan, $jordan, $jordan, $sam] as $registration) {
            $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $registration->reference_code]);
        }
        $lastScan = $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $jordan->reference_code]);

        $lastScan->assertJsonPath('activity.scans', 4)->assertJsonPath('activity.status', 'inside');

        $activity = app(CheckInService::class)->recentActivity($event);
        $this->assertCount(2, $activity);
        $this->assertSame(['Jordan Reyes', 'Sam Cruz'], array_column($activity, 'name'));
        $this->assertSame([4, 1], array_column($activity, 'scans'));
        $this->assertStringStartsWith('Scanned 4 times', $activity[0]['repeat_note']);
        $this->assertNull($activity[1]['repeat_note']);
    }

    public function test_unknown_cancelled_and_wrong_event_passes_are_refused(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $cancelled = Registration::factory()->for($event)->cancelled()->create();
        $otherEventPass = Registration::factory()->for(Event::factory()->for($event->chapter))->create();
        Sanctum::actingAs(User::factory()->gateStaff($event->chapter)->create());

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => 'EX-UNKNOWN0'])->assertNotFound()->assertJson(['result' => 'invalid']);
        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $cancelled->reference_code])->assertUnprocessable()->assertJson(['result' => 'cancelled']);
        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => $otherEventPass->reference_code])->assertUnprocessable()->assertJson(['result' => 'wrong_event']);
    }

    public function test_gate_staff_cannot_scan_for_another_chapter(): void
    {
        $event = Event::factory()->happeningNow()->create();
        Sanctum::actingAs(User::factory()->gateStaff(Chapter::factory()->create())->create());

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => 'EX-ANYTHING'])->assertForbidden();
    }

    public function test_attendees_cannot_use_the_scanner_endpoint(): void
    {
        $event = Event::factory()->happeningNow()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/checkins/scan', ['event_id' => $event->id, 'code' => 'EX-ANYTHING'])->assertForbidden();
    }

    public function test_gate_staff_find_a_ticket_holder_by_name(): void
    {
        $event = Event::factory()->happeningNow()->create();
        $attendee = User::factory()->create(['name' => 'Jordan Reyes']);
        Registration::factory()->for($event)->for($attendee)->create();

        $this->actingAs(User::factory()->gateStaff($event->chapter)->create())
            ->getJson(route('gate.search', ['event_id' => $event->id, 'q' => 'Jord']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Jordan Reyes');
    }
}
