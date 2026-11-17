<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Public catalog of published events. */
class EventBrowseController extends Controller
{
    public function index(Request $request): View
    {
        $events = Event::published()
            ->where('ends_at', '>=', now())
            ->when($request->filled('chapter'), fn ($q) => $q->where('chapter_id', $request->integer('chapter')))
            ->when(
                $request->filled('type') && array_key_exists($request->input('type'), Event::TYPES),
                fn ($q) => $q->where('type', $request->input('type'))
            )
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('city', 'like', $term)->orWhere('venue', 'like', $term));
            })
            ->with(['chapter', 'parent'])
            ->withCount('confirmedRegistrations')
            ->orderBy('starts_at')
            ->paginate(9)
            ->withQueryString();

        return view('events.index', [
            'events' => $events,
            'chapters' => Chapter::orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();

        // Drafts and cancelled events are only visible to the people who manage them.
        abort_unless(
            $event->status === 'published' || ($user && Gate::forUser($user)->allows('manage-event', $event)),
            404
        );

        $event->load([
            'chapter',
            'parent',
            'speakers',
            'children' => fn ($q) => $q->where('status', 'published')->withCount('confirmedRegistrations'),
        ])->loadCount('confirmedRegistrations');

        $canAccessMaterials = $user && Gate::forUser($user)->allows('access-materials', $event);

        return view('events.show', [
            'event' => $event,
            'registration' => $user?->registrations()->where('event_id', $event->id)->first(),
            'canAccessMaterials' => $canAccessMaterials,
            'materials' => $canAccessMaterials ? $event->materials()->get() : collect(),
            'materialCount' => $event->materials()->count(),
            'structuredData' => $this->structuredData($event),
        ]);
    }

    /** schema.org description of the event, so search engines can show its date, place and price. */
    private function structuredData(Event $event): string
    {
        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BusinessEvent',
            'name' => $event->title,
            'description' => $event->description,
            'startDate' => $event->starts_at->toIso8601String(),
            'endDate' => $event->ends_at->toIso8601String(),
            'eventStatus' => $event->status === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => ['@type' => 'Place', 'name' => $event->venue, 'address' => $event->city ?: $event->venue],
            'organizer' => ['@type' => 'Organization', 'name' => 'Global Trade Association, '.$event->chapter->name, 'url' => url('/')],
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format((float) $event->ticket_price, 2, '.', ''),
                'priceCurrency' => 'USD',
                'url' => route('events.show', $event),
                'availability' => match ($event->registrationState()) {
                    'open' => 'https://schema.org/InStock',
                    'not_open' => 'https://schema.org/PreSale',
                    'full' => 'https://schema.org/SoldOut',
                    default => 'https://schema.org/Discontinued', // registration closed or event over
                },
                'validFrom' => $event->registration_opens_at->toIso8601String(),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
