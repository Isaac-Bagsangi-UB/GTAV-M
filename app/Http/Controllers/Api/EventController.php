<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /** GET /api/v1/events?chapter=&type=&q= : published, upcoming events. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $events = Event::published()
            ->where('ends_at', '>=', now())
            ->when($request->filled('chapter'), fn ($q) => $q->where('chapter_id', $request->integer('chapter')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            ->with('chapter')
            ->withCount('confirmedRegistrations')
            ->orderBy('starts_at')
            ->paginate(min(50, max(1, $request->integer('per_page', 15))));

        return EventResource::collection($events);
    }

    /** GET /api/v1/events/{event} */
    public function show(Event $event): EventResource
    {
        abort_unless($event->status === 'published', 404);

        $event->load([
            'chapter',
            'children' => fn ($q) => $q->where('status', 'published')->withCount('confirmedRegistrations'),
        ])->loadCount('confirmedRegistrations');

        return new EventResource($event);
    }
}
