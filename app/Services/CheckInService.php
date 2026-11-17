<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Validates a scanned pass against the central database.
 * Shared by the browser scanner (/gate) and the mobile endpoint (POST /api/v1/checkins/scan).
 */
class CheckInService
{
    /** HTTP status the API answers with for each outcome. */
    public const HTTP_STATUS = [
        'admitted' => 200,
        'duplicate' => 409,
        'invalid' => 404,
        'wrong_event' => 422,
        'cancelled' => 422,
    ];

    /**
     * @param  string  $code  The QR token, or the reference code typed by hand (e.g. EX-7F3K9Q2M).
     */
    public function scan(string $code, Event $event, User $staff, ?string $device = null): array
    {
        $code = trim($code);

        return DB::transaction(function () use ($code, $event, $staff, $device) {
            $registration = Registration::with(['user', 'event'])
                ->where(fn ($q) => $q->where('qr_token', $code)->orWhere('reference_code', strtoupper($code)))
                ->lockForUpdate()
                ->first();

            $firstCheckIn = null;

            if (! $registration) {
                $result = 'invalid';
                $message = 'Pass not recognized. Do not admit.';
            } elseif ($registration->event_id !== $event->id) {
                $result = 'wrong_event';
                $message = 'This pass is for "'.$registration->event->title.'", not this event.';
            } elseif (! $registration->isConfirmed()) {
                $result = 'cancelled';
                $message = 'This registration was cancelled. Do not admit.';
            } elseif ($registration->checked_in_at) {
                $result = 'duplicate';
                $firstCheckIn = $registration->checked_in_at;
                $message = 'Already checked in at '.$firstCheckIn->format('g:i A, M j').'.';
            } else {
                $result = 'admitted';
                $registration->update(['checked_in_at' => now()]);
                $firstCheckIn = $registration->checked_in_at;
                $message = 'Entry authorized.';
            }

            CheckIn::create([
                'registration_id' => $registration?->id,
                'event_id' => $event->id,
                'scanned_by' => $staff->id,
                'result' => $result,
                'code_scanned' => substr($code, 0, 64),
                'device' => $device ? substr($device, 0, 255) : null,
            ]);

            return [
                'authorized' => $result === 'admitted',
                'result' => $result,
                'message' => $message,
                'attendee' => $registration ? [
                    'name' => $registration->user->name,
                    'company' => $registration->user->company,
                ] : null,
                'reference_code' => $registration?->reference_code,
                'checked_in_at' => $firstCheckIn?->toIso8601String(),
                'event' => $this->attendance($event),
            ];
        });
    }

    /**
     * Manual lookup for when a QR code cannot be scanned:
     * finds ticket holders of this event by name, company or ticket code.
     */
    public function search(Event $event, string $term, int $limit = 10): array
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $event->confirmedRegistrations()
            ->with('user')
            ->where(fn ($q) => $q
                ->where('reference_code', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('company', 'like', $like)))
            ->limit($limit)
            ->get()
            ->map(fn (Registration $r) => [
                'reference_code' => $r->reference_code,
                'name' => $r->user->name,
                'company' => $r->user->company,
                'checked_in_at' => $r->checked_in_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Live headcount for an event. */
    public function attendance(Event $event): array
    {
        $confirmed = $event->confirmedRegistrations()->count();
        $checkedIn = $event->confirmedRegistrations()->whereNotNull('checked_in_at')->count();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'capacity' => $event->capacity,
            'confirmed' => $confirmed,
            'checked_in' => $checkedIn,
            'remaining' => max(0, $confirmed - $checkedIn),
        ];
    }
}
