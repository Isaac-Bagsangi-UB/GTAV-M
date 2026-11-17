<?php

namespace App\Http\Controllers\Speaker;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = $request->user()->sessions()
            ->with(['chapter', 'materials'])
            ->withCount('confirmedRegistrations')
            ->orderBy('starts_at')
            ->get();

        return view('speaker.sessions', [
            'upcoming' => $sessions->filter(fn ($e) => $e->ends_at->isFuture()),
            'past' => $sessions->filter(fn ($e) => $e->ends_at->isPast())->reverse(),
        ]);
    }

    /** PDF certificate for a speaker, available once their session is over. */
    public function certificate(Request $request, Event $event): Response
    {
        $session = $request->user()->sessions()->with('chapter')->whereKey($event->id)->firstOrFail();

        abort_unless($session->ends_at->isPast(), 403, 'Speaker certificates are available after the event ends.');

        return Pdf::loadView('pdf.certificate', [
            'heading' => 'Certificate of Appreciation',
            'person' => $request->user(),
            'event' => $session,
            'lead' => 'served as guest speaker at the '.strtolower($session->typeLabel()),
            'topic' => $session->pivot->topic,
            'number' => 'SPK-'.$session->id.'-'.$request->user()->id,
            'note' => 'Issued to the assigned speaker of this event',
        ])->setPaper('a4', 'landscape')->download('speaker-certificate-'.$session->id.'.pdf');
    }
}
