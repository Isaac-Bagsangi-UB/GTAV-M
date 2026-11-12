<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_are_redirected_away_from_login_and_registration_pages(): void
    {
        $user = User::factory()->create(['role' => 'attendee']);

        $this->actingAs($user)->get('/login')->assertRedirectToRoute('dashboard');
        $this->actingAs($user)->get('/register')->assertRedirectToRoute('dashboard');
    }

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirectToRoute('login');
    }

    public function test_new_registration_notifies_event_managers_and_shows_awaiting_verification(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $admin = User::factory()->create(['role' => 'admin']);
        $attendee = User::factory()->create(['role' => 'attendee']);
        $chapter = Chapter::create(['name' => 'Test Chapter', 'manager_id' => $manager->id]);
        $event = Event::create([
            'chapter_id' => $chapter->id,
            'created_by' => $admin->id,
            'name' => 'Test Event',
            'type' => 'Summit',
            'venue' => 'Test Venue',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
            'registration_cutoff' => now()->addDays(6),
            'capacity' => 100,
            'ticket_price' => 100,
        ]);

        $response = $this->actingAs($attendee)->post(route('events.register', $event), [
            'payment_method' => 'gcash',
        ]);

        $response->assertRedirectToRoute('events.show', $event);
        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $admin->id,
            'notifiable_type' => User::class,
        ]);

        $manager->notifications()->delete();
        $this->actingAs($manager)->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
            'notifiable_type' => User::class,
        ]);
        $this->actingAs($manager)->get(route('notifications'))
            ->assertOk()
            ->assertSee(route('events.show', $event), false);
        $this->actingAs($manager)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee($attendee->name)
            ->assertSee('1 pending')
            ->assertSee('Mark paid');
        $this->actingAs($manager)->post(route('registrations.verify-payment', $event->registrations()->first()))
            ->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'payment_status' => 'paid',
            'verified_by' => $manager->id,
        ]);
        $this->actingAs($attendee)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Registered');
    }

    public function test_managers_can_reject_registrations_and_assign_staff_without_exposing_revenue_to_staff(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $attendee = User::factory()->create(['role' => 'attendee']);
        $chapter = Chapter::create(['name' => 'Managed Chapter', 'manager_id' => $manager->id]);
        $event = Event::create([
            'chapter_id' => $chapter->id,
            'created_by' => $manager->id,
            'name' => 'Managed Event',
            'type' => 'Workshop',
            'venue' => 'Managed Venue',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
            'registration_cutoff' => now()->addDays(6),
            'capacity' => 100,
            'ticket_price' => 100,
        ]);
        $this->actingAs($manager)->post(route('staff.store'), [
            'name' => 'Created Gate Staff',
            'email' => 'created-staff@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();
        $staff = User::where('email', 'created-staff@example.com')->firstOrFail();
        $this->assertFalse($staff->assignedEvents()->exists());
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_code' => 'GTAV-REJECT1',
            'payment_method' => 'cash',
            'registered_at' => now(),
        ]);
        $bulkRegistration = Registration::create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create(['role' => 'attendee'])->id,
            'ticket_code' => 'GTAV-REJECT2',
            'payment_method' => 'gcash',
            'registered_at' => now(),
        ]);

        $this->actingAs($manager)->put(route('events.staff.update', $event), [
            'staff_ids' => [$staff->id],
        ])->assertRedirect();
        $this->assertDatabaseHas('event_staff', [
            'event_id' => $event->id,
            'user_id' => $staff->id,
            'assigned_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('registrations.reject', $registration), [
            'rejection_reason' => 'Payment could not be confirmed.',
        ])->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'id' => $registration->id,
            'payment_status' => 'rejected',
            'rejected_by' => $manager->id,
            'rejection_reason' => 'Payment could not be confirmed.',
        ]);
        $this->actingAs($manager)->post(route('events.registrations.reject', $event), [
            'registration_ids' => [$bulkRegistration->id],
            'rejection_reason' => 'Bulk rejection.',
        ])->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'id' => $bulkRegistration->id,
            'payment_status' => 'rejected',
            'rejected_by' => $manager->id,
        ]);

        $this->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('REVENUE');
        $this->actingAs($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('REVENUE');
    }

    public function test_only_verified_attendees_can_view_their_ticket(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $attendee = User::factory()->create(['role' => 'attendee']);
        $chapter = Chapter::create(['name' => 'Ticket Chapter', 'manager_id' => $manager->id]);
        $event = Event::create([
            'chapter_id' => $chapter->id,
            'created_by' => $manager->id,
            'name' => 'Ticket Event',
            'type' => 'Expo',
            'venue' => 'Ticket Venue',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(2),
            'registration_cutoff' => now()->addDays(6),
            'capacity' => 100,
            'ticket_price' => 100,
        ]);
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'ticket_code' => 'GTAV-TICKET1',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'registered_at' => now(),
        ]);

        $this->actingAs($attendee)->get(route('registrations.ticket', $registration))
            ->assertOk()
            ->assertSee('GTAV-TICKET1');
    }

    public function test_public_registration_cannot_create_gate_staff_accounts(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Unmanaged Staff',
            'email' => 'unmanaged-staff@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'gate_staff',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'unmanaged-staff@example.com']);
    }

    public function test_event_form_preserves_submitted_values_after_validation_error(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $chapter = Chapter::create(['name' => 'Preserved Chapter', 'manager_id' => $manager->id]);

        $response = $this->actingAs($manager)->post(route('events.store'), [
            'chapter_id' => $chapter->id,
            'name' => 'Preserved Event',
            'type' => 'Workshop',
            'venue' => 'Preserved Venue',
            'starts_at' => '2026-12-01T10:00',
            'ends_at' => '2026-12-01T09:00',
            'registration_cutoff' => '2026-11-30T23:59',
            'capacity' => 80,
            'ticket_price' => '650.00',
            'description' => 'Preserved description.',
        ]);

        $response->assertSessionHasErrors('ends_at');
        $this->actingAs($manager)->get(route('events.create'))
            ->assertOk()
            ->assertSee('value="Preserved Event"', false)
            ->assertSee('value="Preserved Venue"', false)
            ->assertSee('value="2026-12-01T10:00"', false)
            ->assertSee('value="2026-12-01T09:00"', false)
            ->assertSee('Preserved description.');
    }
}
