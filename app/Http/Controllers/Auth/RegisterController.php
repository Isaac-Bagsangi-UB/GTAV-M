<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Public sign-up creates a Corporate Attendee or a Guest Speaker.
     * Managers, gate staff and administrators are created by an administrator.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([User::ROLE_ATTENDEE, User::ROLE_SPEAKER])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['required', 'string', 'max:160'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data);

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('user.registered', $user->email.' created a '.$user->roleLabel().' account', $user);

        if ($user->hasRole(User::ROLE_SPEAKER)) {
            return redirect()->route('speaker.sessions')
                ->with('success', 'Welcome, '.$user->name.'. A chapter manager will assign you to your sessions.');
        }

        return redirect()->intended(route('events.index'))
            ->with('success', 'Welcome, '.$user->name.'. Your account is ready. Pick an event to register.');
    }
}
