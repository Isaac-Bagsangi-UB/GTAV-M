<?php

namespace App\Http\Controllers\Gate;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Browser-based scanner for gate staff on a phone or handheld. */
class ScannerController extends Controller
{
    public function index(Request $request, CheckInService $service): View
    {
        $user = $request->user();

        // Events that are on now or coming up, limited to the staff member's chapter.
        $events = Event::published()
            ->where('ends_at', '>=', now()->subHours(12))
            ->when(! $user->isAdmin(), fn ($q) => $q->where('chapter_id', $user->chapter_id))
            ->orderBy('starts_at')
            ->take(50)
            ->get();

        $event = $events->firstWhere('id', $request->integer('event')) ?? $events->first();

        return view('gate.scanner', [
            'events' => $events,
            'event' => $event,
            'attendance' => $event ? $service->attendance($event) : null,
            'recent' => $event
                ? $event->checkIns()->with('registration.user')->latest()->take(8)->get()
                : collect(),
        ]);
    }

    /** Find a ticket holder by name or ticket code when the QR code will not scan. */
    public function search(Request $request, CheckInService $service): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'q' => ['required', 'string', 'min:2', 'max:64'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        Gate::authorize('scan-event', $event);

        return response()->json(['data' => $service->search($event, $data['q'])]);
    }

    public function scan(Request $request, CheckInService $service): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'code' => ['required', 'string', 'max:64'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        Gate::authorize('scan-event', $event);

        return response()->json($service->scan($data['code'], $event, $request->user(), 'web-scanner'));
    }
}
