<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function signUp(string $role): array
    {
        return [
            'role' => $role,
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'company' => 'Santos Trading',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];
    }

    public function test_a_corporate_attendee_can_register_and_is_signed_in(): void
    {
        $this->post('/register', $this->signUp(User::ROLE_ATTENDEE))->assertRedirectToRoute('events.index');

        $this->assertAuthenticated();
        $user = User::sole();
        $this->assertSame(User::ROLE_ATTENDEE, $user->role);
        $this->assertNotSame('secret123', $user->password, 'passwords are stored hashed');
    }

    public function test_a_guest_speaker_can_register_themselves(): void
    {
        $this->post('/register', $this->signUp(User::ROLE_SPEAKER))->assertRedirectToRoute('speaker.sessions');

        $this->assertSame(User::ROLE_SPEAKER, User::sole()->role);
    }

    public function test_nobody_can_sign_up_as_an_administrator_manager_or_gate_staff(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_GATE] as $role) {
            $this->post('/register', $this->signUp($role))->assertSessionHasErrors('role');
        }

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_users_log_in_with_the_right_password_only(): void
    {
        $user = User::factory()->create(['email' => 'jordan@example.com']);

        $this->post('/login', ['email' => 'jordan@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'jordan@example.com', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_logging_out_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirectToRoute('home');

        $this->assertGuest();
        $this->get('/my/registrations')->assertRedirectToRoute('login');
    }

    public function test_signed_in_users_are_kept_away_from_the_login_and_register_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login')->assertRedirect();
        $this->actingAs($user)->get('/register')->assertRedirect();
    }
}
