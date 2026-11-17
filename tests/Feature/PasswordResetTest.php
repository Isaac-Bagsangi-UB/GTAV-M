<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_message_is_shown_whether_or_not_the_email_has_an_account(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('success');
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_a_reset_link_changes_the_password_once(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email]);

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPass2026',
            'password_confirmation' => 'NewPass2026',
        ])->assertRedirectToRoute('login');

        $this->assertTrue(Hash::check('NewPass2026', $user->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Another2026',
            'password_confirmation' => 'Another2026',
        ])->assertSessionHasErrors('email');
    }

    public function test_new_passwords_need_letters_and_numbers(): void
    {
        $this->post(route('register'), [
            'role' => 'attendee',
            'name' => 'Test Person',
            'email' => 'test.person@example.com',
            'company' => 'Test Ltd',
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'test.person@example.com']);
    }
}
