<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\CheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The fast endpoint for handheld scanners at the gate. */
class CheckInController extends Controller
{
    /**
     * POST /api/v1/checkins/scan
     * Body: { "event_id": 4, "code": "<QR token or reference code>", "device": "Gate-A-01" }
     *
     * 200 admitted · 409 already checked in · 404 unknown pass · 422 wrong event or cancelled
     */
    public function scan(Request $request, CheckInService $service): JsonResponse
    {
        $data = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'code' => ['required', 'string', 'max:64'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        Gate::authorize('scan-event', $event);

        $result = $service->scan($data['code'], $event, $request->user(), $data['device'] ?? 'api');

        return response()->json($result, CheckInService::HTTP_STATUS[$result['result']]);
    }

    /** GET /api/v1/events/{event}/attendees?q= : manual search by name or ticket code. */
    public function attendees(Request $request, Event $event, CheckInService $service): JsonResponse
    {
        Gate::authorize('scan-event', $event);

        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:64']]);

        return response()->json(['data' => $service->search($event, $data['q'])]);
    }

    /** GET /api/v1/events/{event}/attendance : live headcount. */
    public function attendance(Event $event, CheckInService $service): JsonResponse
    {
        Gate::authorize('scan-event', $event);

        return response()->json(['data' => $service->attendance($event)]);
    }
}
