<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const EVENT_FIELDS = [
        'id', 'title', 'type', 'venue', 'city', 'chapter' => ['id', 'name', 'code'],
        'starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at',
        'ticket_price', 'capacity', 'confirmed', 'seats_left', 'registration_state',
    ];

    public function test_a_token_is_issued_for_valid_credentials_only(): void
    {
        User::factory()->create(['email' => 'jordan@example.com']);

        $this->postJson('/api/v1/auth/token', ['email' => 'jordan@example.com', 'password' => 'nope'])->assertUnauthorized();

        $this->postJson('/api/v1/auth/token', ['email' => 'jordan@example.com', 'password' => 'password', 'device_name' => 'phone'])
            ->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'company', 'chapter_id']])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('user.password');
    }

    public function test_protected_endpoints_need_a_bearer_token(): void
    {
        foreach (['/api/v1/me', '/api/v1/registrations', '/api/v1/reports/chapters'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_the_issued_token_works_until_it_is_revoked(): void
    {
        User::factory()->create(['email' => 'jordan@example.com']);
        $token = $this->postJson('/api/v1/auth/token', ['email' => 'jordan@example.com', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'jordan@example.com');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_the_public_event_list_is_paginated_json(): void
    {
        Event::factory()->count(3)->create();
        Event::factory()->draft()->create();

        $this->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => ['*' => self::EVENT_FIELDS], 'links', 'meta' => ['current_page', 'total']]);
    }

    public function test_one_event_shows_its_sub_events_and_drafts_stay_hidden(): void
    {
        $expo = Event::factory()->create();
        Event::factory()->for($expo->chapter)->create(['parent_id' => $expo->id, 'type' => 'workshop']);

        $this->getJson('/api/v1/events/'.$expo->id)
            ->assertOk()
            ->assertJsonStructure(['data' => self::EVENT_FIELDS + ['sub_events' => ['*' => ['id', 'title']]]])
            ->assertJsonCount(1, 'data.sub_events');

        $this->getJson('/api/v1/events/'.Event::factory()->draft()->create()->id)->assertNotFound();
    }

    public function test_an_attendee_registers_and_lists_tickets_through_the_api(): void
    {
        $event = Event::factory()->create();
        $attendee = User::factory()->create();
        $token = $attendee->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/events/{$event->id}/registrations")
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'reference_code', 'qr_token', 'status', 'amount_paid', 'checked_in_at', 'registered_at', 'event' => ['id', 'title']]])
            ->assertJsonPath('data.status', Registration::CONFIRMED);

        $this->withToken($token)->postJson("/api/v1/events/{$event->id}/registrations")->assertUnprocessable();

        $this->withToken($token)->getJson('/api/v1/registrations')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_attendees_cannot_read_chapter_reports_but_administrators_can(): void
    {
        Event::factory()->create();

        $this->withToken(User::factory()->create()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/reports/chapters')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken(User::factory()->admin()->create()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/reports/chapters')
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'tickets', 'revenue', 'fill_rate', 'attendance_rate']]]);
    }
}
