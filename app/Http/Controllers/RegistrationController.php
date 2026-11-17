<?php

namespace App\Http\Controllers;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Registration;
use App\Services\RegistrationService;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** The attendee's own registrations, entry passes and certificates. */
class RegistrationController extends Controller
{
    public function index(Request $request): View
    {
        $registrations = $request->user()->registrations()
            ->with(['event' => fn ($q) => $q->with('chapter')->withCount('materials')])
            ->get()
            ->sortBy(fn (Registration $r) => $r->event->starts_at)
            ->values();

        return view('registrations.index', [
            'upcoming' => $registrations->filter(fn ($r) => $r->isConfirmed() && $r->event->ends_at->isFuture()),
            'past' => $registrations->filter(fn ($r) => $r->isConfirmed() && $r->event->ends_at->isPast())->reverse(),
            'cancelled' => $registrations->reject(fn ($r) => $r->isConfirmed()),
        ]);
    }

    public function store(Request $request, Event $event, RegistrationService $service): RedirectResponse
    {
        try {
            $registration = $service->register($request->user(), $event);
        } catch (RegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('registrations.index')
            ->with('success', 'You are registered for '.$event->title.'. Reference '.$registration->reference_code.'. Your entry pass is ready to download.');
    }

    public function destroy(Request $request, Registration $registration, RegistrationService $service): RedirectResponse
    {
        $this->ensureOwner($request, $registration);

        try {
            $service->cancel($registration);
        } catch (RegistrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Your registration for '.$registration->event->title.' was cancelled.');
    }

    /** PDF entry pass with the QR code that gate staff scan. */
    public function pass(Request $request, Registration $registration): Response
    {
        $this->ensureOwner($request, $registration);
        abort_unless($registration->isConfirmed(), 403, 'This registration is not active, so no pass is available.');

        $registration->load(['user', 'event.chapter']);

        return Pdf::loadView('pdf.pass', [
            'registration' => $registration,
            'qr' => QrCode::dataUri($registration->qr_token, 260),
        ])->setPaper('a5')->download('entry-pass-'.$registration->reference_code.'.pdf');
    }

    /** PDF attendance certificate, only for attendees who were checked in. */
    public function certificate(Request $request, Registration $registration): Response
    {
        $this->ensureOwner($request, $registration);
        abort_unless(
            $registration->canDownloadCertificate(),
            403,
            'Certificates are issued after the event ends, to attendees who were checked in at the venue.'
        );

        $registration->load(['user', 'event.chapter']);

        return Pdf::loadView('pdf.certificate', [
            'heading' => 'Certificate of Attendance',
            'person' => $registration->user,
            'event' => $registration->event,
            'lead' => 'attended the '.strtolower($registration->event->typeLabel()),
            'topic' => null,
            'number' => $registration->reference_code,
            'note' => 'Attendance verified at the gate on '.$registration->checked_in_at->format('M j, Y'),
        ])
            ->setPaper('a4', 'landscape')
            ->download('certificate-'.$registration->reference_code.'.pdf');
    }

    private function ensureOwner(Request $request, Registration $registration): void
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
    }
}
