<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Event participation matrix: which individuals attend which
 * combination of events and summits within a chapter.
 */
class MatrixController extends Controller
{
    public function index(Request $request): View
    {
        $chapters = Chapter::orderBy('name')->get();
        $chapter = $chapters->firstWhere('id', $request->integer('chapter')) ?? $chapters->first();

        $events = $chapter
            ? Event::where('chapter_id', $chapter->id)->where('status', '!=', 'draft')->orderBy('starts_at')->get()
            : collect();
        $eventIds = $events->pluck('id');

        $inScope = fn ($q) => $q->whereIn('event_id', $eventIds)->where('status', Registration::CONFIRMED);

        $people = User::whereHas('registrations', $inScope)
            ->with(['registrations' => $inScope])
            ->withCount(['registrations as events_in_chapter' => $inScope])
            ->orderByDesc('events_in_chapter')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Key each person's registrations by event for quick lookup in the view.
        $people->getCollection()->each(
            fn (User $u) => $u->setRelation('registrations', $u->registrations->keyBy('event_id'))
        );

        return view('admin.matrix', [
            'chapters' => $chapters,
            'chapter' => $chapter,
            'events' => $events,
            'people' => $people,
            'multiEventCount' => User::whereHas('registrations', $inScope, '>=', 2)->count(),
        ]);
    }
}
