<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
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
