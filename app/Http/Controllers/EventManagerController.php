<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventManagerController extends Controller
{
    public function home()
    {
        return Auth::check() ? redirect()->route('dashboard') : view('auth.login');
    }

    public function login()
    {
        return view('auth.login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those credentials do not match our records.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function register()
    {
        return view('auth.register');
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', 'in:attendee,speaker,gate_staff'],
        ]);
        $user = User::create($data);
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard()
    {
        $user = Auth::user();
        $events = Event::with('chapter')->withCount('registrations')->orderBy('starts_at')->get();
        $registrations = $user->isManager()
            ? Registration::with(['event', 'user'])->latest()->take(8)->get()
            : $user->registrations()->with('event.chapter')->latest()->get();
        $weekRegistrations = collect();
        $monthRegistrations = collect();
        if (! $user->isManager()) {
            $weekRegistrations = $user->registrations()
                ->with('event.chapter')
                ->whereHas('event', fn ($query) => $query->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()]))
                ->get()
                ->sortBy(fn (Registration $registration) => $registration->event->starts_at);
            $monthRegistrations = $user->registrations()
                ->with('event.chapter')
                ->whereHas('event', fn ($query) => $query->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()]))
                ->get()
                ->sortBy(fn (Registration $registration) => $registration->event->starts_at);
        }
        $stats = [
            'events' => $events->count(),
            'registrations' => Registration::count(),
            'checked_in' => DB::table('attendances')->count(),
            'revenue' => Registration::where('payment_status', 'paid')->join('events', 'events.id', '=', 'registrations.event_id')->sum('events.ticket_price'),
        ];

        return view('dashboard', compact('events', 'registrations', 'weekRegistrations', 'monthRegistrations', 'stats'));
    }

    public function registrations(Request $request)
    {
        $user = Auth::user();
        abort_if($user->isManager(), 404);
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $registrations = $user->registrations()
            ->with('event.chapter')
            ->when(in_array($status, ['upcoming', 'finished'], true), function ($query) use ($status) {
                $operator = $status === 'upcoming' ? '>=' : '<';
                $query->whereHas('event', fn ($query) => $query->where('ends_at', $operator, now()));
            })
            ->when($search, fn ($query) => $query->whereHas('event', fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('venue', 'like', "%{$search}%")))
            ->latest('registered_at')
            ->get();

        return view('registrations.index', compact('registrations', 'status', 'search'));
    }

    public function calendar(Request $request)
    {
        $user = Auth::user();
        abort_if($user->isManager(), 404);
        $view = $request->string('view', 'month')->toString();
        abort_unless(in_array($view, ['week', 'month', 'year'], true), 400);
        $rangeStart = match ($view) {
            'week' => now()->startOfWeek(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
        $rangeEnd = match ($view) {
            'week' => now()->endOfWeek(),
            'year' => now()->endOfYear(),
            default => now()->endOfMonth(),
        };
        $registrations = $user->registrations()
            ->with('event.chapter')
            ->whereHas('event', fn ($query) => $query->whereBetween('starts_at', [$rangeStart, $rangeEnd]))
            ->get()
            ->sortBy(fn (Registration $registration) => $registration->event->starts_at);

        return view('registrations.calendar', compact('registrations', 'view', 'rangeStart', 'rangeEnd'));
    }

    public function events()
    {
        $search = request()->string('search')->trim()->toString();
        $events = Event::with('chapter')->withCount('registrations')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('venue', 'like', "%{$search}%")
                    ->orWhereHas('chapter', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('starts_at')->get();

        return view('events.index', compact('events', 'search'));
    }

    public function showEvent(Event $event)
    {
        $event->load('chapter');

        return view('events.show', compact('event'));
    }

    public function registerForEvent(Request $request, Event $event)
    {
        abort_unless(Auth::user()->role === 'attendee', 403);
        $data = $request->validate(['payment_method' => ['required', 'in:cash,gcash,bank_transfer']]);
        if ($event->registration_cutoff->isPast() || $event->seatsRemaining() < 1) {
            return back()->withErrors(['event' => 'Registration is closed or this event is full.']);
        }
        $registration = Registration::firstOrCreate(
            ['event_id' => $event->id, 'user_id' => Auth::id()],
            ['ticket_code' => 'GTAV-'.Str::upper(Str::random(8)), 'payment_method' => $data['payment_method'], 'registered_at' => now()]
        );
        if ($registration->wasRecentlyCreated === false && $registration->payment_status === 'pending') {
            $registration->update(['payment_method' => $data['payment_method']]);
        }

        return redirect()->route('events.show', $event)->with('success', 'Registration received. Complete your payment so the event team can verify it.');
    }

    public function pass(Registration $registration)
    {
        abort_unless($registration->user_id === Auth::id() || Auth::user()->isManager(), 403);
        abort_unless($registration->payment_status === 'paid', 403, 'Your pass will be available after payment is verified.');
        $registration->load('event.chapter', 'user');
        $content = "GTAV-M ENTRY PASS\n\n{$registration->event->name}\n{$registration->event->venue}\n{$registration->event->starts_at->format('M d, Y g:i A')}\n\nAttendee: {$registration->user->name}\nTicket code: {$registration->ticket_code}\n";

        return response($content)->header('Content-Type', 'text/plain')->header('Content-Disposition', 'attachment; filename="'.$registration->ticket_code.'.txt"');
    }

    public function createEvent()
    {
        $chapters = Chapter::orderBy('name')->get();

        return view('events.create', compact('chapters'));
    }

    public function storeEvent(Request $request)
    {
        $data = $request->validate([
            'chapter_id' => ['required', 'exists:chapters,id'], 'name' => ['required', 'max:180'],
            'type' => ['required', 'in:Expo,Summit,Workshop'], 'venue' => ['required', 'max:180'],
            'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_cutoff' => ['required', 'date', 'before:starts_at'], 'capacity' => ['required', 'integer', 'min:1'],
            'ticket_price' => ['required', 'numeric', 'min:0'], 'description' => ['nullable', 'string'],
        ]);
        $data['created_by'] = Auth::id();
        Event::create($data);

        return redirect()->route('dashboard')->with('success', 'Event published.');
    }

    public function checkIn()
    {
        $events = Event::withCount('registrations')->orderBy('starts_at')->get();

        return view('check-in', compact('events'));
    }

    public function scan(Request $request)
    {
        $data = $request->validate(['ticket_code' => ['required', 'string'], 'event_id' => ['required', 'exists:events,id']]);
        $registration = Registration::with(['user', 'event'])->where('ticket_code', $data['ticket_code'])->where('event_id', $data['event_id'])->first();
        if (! $registration) {
            return back()->with('scan_error', 'Denied: ticket not found for this event.')->withInput();
        }
        if ($registration->payment_status !== 'paid') {
            return back()->with('scan_error', 'Denied: payment has not been verified.');
        }
        if ($registration->attendance) {
            return back()->with('scan_warning', "Already used by {$registration->user->name} at {$registration->attendance->checked_in_at->format('g:i A')}.");
        }
        $registration->attendance()->create(['checked_in_by' => Auth::id(), 'checked_in_at' => now()]);

        return back()->with('scan_success', "Authorized: {$registration->user->name} is checked in.");
    }

    public function verifyPayment(Registration $registration)
    {
        abort_unless(Auth::user()->isManager(), 403);
        $registration->load('event', 'user');
        $registration->update(['payment_status' => 'paid', 'paid_at' => now(), 'verified_by' => Auth::id()]);
        $registration->user->notify(new PaymentVerifiedNotification($registration));

        return back()->with('success', "Payment verified for {$registration->user->name}.");
    }

    public function account()
    {
        return view('account', ['user' => Auth::user()]);
    }

    public function notifications()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->latest()->get();
        $user->unreadNotifications->markAsRead();

        return view('notifications', compact('notifications'));
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $user->update($data);

        return back()->with('success', 'Account details updated.');
    }
}
