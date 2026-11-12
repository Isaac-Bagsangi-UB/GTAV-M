<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\PaymentVerifiedNotification;
use App\Notifications\RegistrationReceivedNotification;
use App\Notifications\RegistrationRejectedNotification;
use Illuminate\Database\Eloquent\Builder;
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
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

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
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
            'role' => ['required', 'in:attendee,speaker'],
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
        $this->syncPendingRegistrationNotifications($user);
        $events = $this->visibleEventsQuery($user)->with('chapter')->withCount('registrations')->orderBy('starts_at')->get();
        $eventIds = $events->modelKeys();
        $registrations = $user->isManager()
            ? Registration::with(['event', 'user'])->whereHas('event', fn ($query) => $this->applyEventVisibility($query, $user))->latest()->take(8)->get()
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
            'registrations' => Registration::whereIn('event_id', $eventIds)->count(),
            'checked_in' => DB::table('attendances')
                ->whereIn('registration_id', Registration::select('id')->whereIn('event_id', $eventIds))
                ->count(),
            'revenue' => $user->isManager()
                ? Registration::where('payment_status', 'paid')->whereIn('event_id', $eventIds)->join('events', 'events.id', '=', 'registrations.event_id')->sum('events.ticket_price')
                : null,
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
        $user = Auth::user();
        $search = request()->string('search')->trim()->toString();
        $events = $this->visibleEventsQuery($user)->with('chapter')->withCount('registrations')
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
        $user = Auth::user();
        abort_if($user->isGateStaff() && ! $event->staff()->whereKey($user->id)->exists(), 403);
        $event->load('chapter', 'creator');
        $this->syncPendingRegistrationNotifications($user);
        $registration = $user->registrations()->where('event_id', $event->id)->first();
        $canManageEvent = $this->canManageEvent($user, $event);
        $pendingRegistrations = $canManageEvent
            ? $event->registrations()->with('user')->where('payment_status', 'pending')->latest('registered_at')->get()
            : collect();
        $staff = $canManageEvent ? User::where('role', 'gate_staff')->orderBy('name')->get() : collect();
        $assignedStaffIds = $event->staff()->pluck('users.id');

        return view('events.show', compact('event', 'registration', 'pendingRegistrations', 'staff', 'assignedStaffIds', 'canManageEvent'));
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
        if ($registration->wasRecentlyCreated) {
            $event->loadMissing(['chapter.manager', 'creator']);
            $recipients = collect([$event->chapter?->manager, $event->creator])
                ->filter()
                ->unique('id');
            $registration->load(['event', 'user']);
            $recipients->each(fn (User $recipient) => $recipient->notify(new RegistrationReceivedNotification($registration)));
        }

        return redirect()->route('events.show', $event)->with('success', 'Registration received. Complete your payment so the event team can verify it.');
    }

    public function pass(Registration $registration)
    {
        $registration->load('event.chapter', 'user');
        abort_unless($registration->user_id === Auth::id() || $this->canManageEvent(Auth::user(), $registration->event), 403);
        abort_unless($registration->payment_status === 'paid', 403, 'Your pass will be available after payment is verified.');
        $content = "GTAV-M ENTRY PASS\n\n{$registration->event->name}\n{$registration->event->venue}\n{$registration->event->starts_at->format('M d, Y g:i A')}\n\nAttendee: {$registration->user->name}\nTicket code: {$registration->ticket_code}\n";

        return response($content)->header('Content-Type', 'text/plain')->header('Content-Disposition', 'attachment; filename="'.$registration->ticket_code.'.txt"');
    }

    public function ticket(Registration $registration)
    {
        abort_unless($registration->user_id === Auth::id(), 403);
        abort_unless($registration->payment_status === 'paid', 403, 'Your ticket will be available after payment is verified.');
        $registration->load('event.chapter', 'user');

        return view('registrations.ticket', compact('registration'));
    }

    public function createEvent()
    {
        $chapters = Auth::user()->isAdmin()
            ? Chapter::orderBy('name')->get()
            : Chapter::where('manager_id', Auth::id())->orderBy('name')->get();

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
        $chapter = Chapter::findOrFail($data['chapter_id']);
        abort_unless(Auth::user()->isAdmin() || $chapter->manager_id === Auth::id(), 403);
        $data['created_by'] = Auth::id();
        Event::create($data);

        return redirect()->route('dashboard')->with('success', 'Event published.');
    }

    public function checkIn()
    {
        $events = $this->visibleEventsQuery(Auth::user())->withCount('registrations')->orderBy('starts_at')->get();

        return view('check-in', compact('events'));
    }

    public function scan(Request $request)
    {
        $data = $request->validate(['ticket_code' => ['required', 'string'], 'event_id' => ['required', 'exists:events,id']]);
        if (Auth::user()->isGateStaff()) {
            abort_unless(Auth::user()->assignedEvents()->whereKey($data['event_id'])->exists(), 403);
        }
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
        $registration->load('event');
        abort_unless($this->canManageEvent(Auth::user(), $registration->event), 403);
        abort_unless($registration->payment_status === 'pending', 422, 'Only pending registrations can be approved.');
        $registration->load('user');
        $registration->update(['payment_status' => 'paid', 'paid_at' => now(), 'verified_by' => Auth::id()]);
        $registration->user->notify(new PaymentVerifiedNotification($registration));

        return back()->with('success', "Payment verified for {$registration->user->name}.");
    }

    public function rejectRegistration(Request $request, Registration $registration)
    {
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:500']]);
        $registration->load('event', 'user');
        abort_unless($this->canManageEvent(Auth::user(), $registration->event), 403);
        abort_unless($registration->payment_status === 'pending', 422, 'Only pending registrations can be rejected.');
        $registration->update([
            'payment_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);
        $registration->user->notify(new RegistrationRejectedNotification($registration));

        return back()->with('success', "Registration rejected for {$registration->user->name}.");
    }

    public function bulkRejectRegistrations(Request $request, Event $event)
    {
        abort_unless($this->canManageEvent(Auth::user(), $event), 403);
        $data = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['integer', 'exists:registrations,id'],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $registrations = Registration::with('user')
            ->where('event_id', $event->id)
            ->where('payment_status', 'pending')
            ->whereIn('id', $data['registration_ids'])
            ->get();
        foreach ($registrations as $registration) {
            $registration->update([
                'payment_status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
            $registration->load('event');
            $registration->user->notify(new RegistrationRejectedNotification($registration));
        }

        return back()->with('success', "{$registrations->count()} registration(s) rejected.");
    }

    public function assignStaff(Request $request, Event $event)
    {
        abort_unless($this->canManageEvent(Auth::user(), $event), 403);
        $data = $request->validate([
            'staff_ids' => ['array'],
            'staff_ids.*' => ['integer', 'exists:users,id'],
        ]);
        $staffIds = User::whereIn('id', $data['staff_ids'] ?? [])->where('role', 'gate_staff')->pluck('id')->all();
        $event->staff()->syncWithPivotValues($staffIds, ['assigned_by' => Auth::id()]);

        return back()->with('success', 'Event staff assignments updated.');
    }

    public function staff()
    {
        abort_unless(Auth::user()->isManager(), 403);
        $staff = User::where('role', 'gate_staff')->with('assignedEvents')->orderBy('name')->get();

        return view('staff.index', compact('staff'));
    }

    public function storeStaff(Request $request)
    {
        abort_unless(Auth::user()->isManager(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        $data['role'] = 'gate_staff';
        User::create($data);

        return back()->with('success', 'Gate staff account created. Assign events before they can check in attendees.');
    }

    public function account()
    {
        return view('account', ['user' => Auth::user()]);
    }

    public function notifications()
    {
        $user = Auth::user();
        $this->syncPendingRegistrationNotifications($user);
        $notifications = $user->notifications()->latest()->get();
        $user->unreadNotifications->markAsRead();

        return view('notifications', compact('notifications'));
    }

    private function syncPendingRegistrationNotifications(User $user): void
    {
        if (! $user->isManager()) {
            return;
        }

        $registrations = Registration::with(['event.chapter', 'event.creator', 'user'])
            ->where('payment_status', 'pending')
            ->whereHas('event', fn ($query) => $query
                ->where('created_by', $user->id)
                ->orWhereHas('chapter', fn ($query) => $query->where('manager_id', $user->id)))
            ->get();

        foreach ($registrations as $registration) {
            $hasNotification = $user->notifications()
                ->where('type', RegistrationReceivedNotification::class)
                ->whereJsonContains('data->registration_id', $registration->id)
                ->exists();

            if (! $hasNotification) {
                $user->notify(new RegistrationReceivedNotification($registration));
            }
        }
    }

    private function visibleEventsQuery(User $user): Builder
    {
        $query = Event::query();
        if ($user->isAdmin()) {
            return $query;
        }
        if ($user->isManager()) {
            return $query->whereHas('chapter', fn ($chapter) => $chapter->where('manager_id', $user->id));
        }
        if ($user->isGateStaff()) {
            return $query->whereHas('staff', fn ($staff) => $staff->where('users.id', $user->id));
        }

        return $query;
    }

    private function applyEventVisibility(Builder $query, User $user): void
    {
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isManager()) {
            $query->whereHas('chapter', fn ($chapter) => $chapter->where('manager_id', $user->id));
        } elseif ($user->isGateStaff()) {
            $query->whereHas('staff', fn ($staff) => $staff->where('users.id', $user->id));
        } else {
            $query->whereKey(-1);
        }
    }

    private function canManageEvent(User $user, Event $event): bool
    {
        return $user->isAdmin() || ($user->isManager() && $event->chapter()->where('manager_id', $user->id)->exists());
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
