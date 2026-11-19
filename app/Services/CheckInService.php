<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Validates a scanned pass against the central database.
 * Shared by the browser scanner (/gate) and the mobile endpoint (POST /api/v1/checkins/scan).
 *
 * Every scan is stored in check_ins, including repeats and refusals, so the record is complete.
 * Gate staff see that record summarized as one row per person.
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
     * What the gate screen tells staff to do for each outcome.
     *
     * @var array<string, array{tone: string, instruction: string}>
     */
    public const VERDICTS = [
        'admitted' => ['tone' => 'ok', 'instruction' => 'LET IN'],
        'duplicate' => ['tone' => 'warn', 'instruction' => 'ALREADY INSIDE'],
        'invalid' => ['tone' => 'bad', 'instruction' => 'DO NOT LET IN'],
        'wrong_event' => ['tone' => 'bad', 'instruction' => 'DO NOT LET IN'],
        'cancelled' => ['tone' => 'bad', 'instruction' => 'DO NOT LET IN'],
    ];

    /**
     * @param  string  $code  The QR token, or the ticket code typed by hand (e.g. EX-7F3K9Q2M).
     * @return array{authorized: bool, result: string, instruction: string, tone: string, message: string, attendee: array{name: string, company: ?string}|null, reference_code: ?string, checked_in_at: ?string, activity: array<string, mixed>, event: array<string, mixed>}
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
                $message = 'No ticket matches this code. Send the guest to the help desk.';
            } elseif ($registration->event_id !== $event->id) {
                $result = 'wrong_event';
                $message = 'This ticket is for "'.$registration->event->title.'" on '.$registration->event->starts_at->format('M j').', not this event.';
            } elseif (! $registration->isConfirmed()) {
                $result = 'cancelled';
                $message = 'This ticket was cancelled. Send the guest to the help desk.';
            } elseif ($registration->checked_in_at) {
                $result = 'duplicate';
                $firstCheckIn = $registration->checked_in_at;
                $message = 'Entered at '.$firstCheckIn->format('g:i A, M j').'. Check their ID before letting them back in.';
            } else {
                $result = 'admitted';
                $registration->update(['checked_in_at' => now()]);
                $firstCheckIn = $registration->checked_in_at;
                $message = 'Valid ticket. Welcome them in.';
            }

            CheckIn::create([
                'registration_id' => $registration?->id,
                'event_id' => $event->id,
                'scanned_by' => $staff->id,
                'result' => $result,
                'code_scanned' => substr($code, 0, 64),
                'device' => $device ? substr($device, 0, 255) : null,
            ]);

            $scansOfThisTicket = $event->checkIns()
                ->with('registration.user')
                ->when(
                    $registration,
                    fn ($q) => $q->where('registration_id', $registration->id),
                    fn ($q) => $q->whereNull('registration_id')->where('code_scanned', substr($code, 0, 64)),
                )
                ->latest('id')
                ->get();

            return [
                'authorized' => $result === 'admitted',
                'result' => $result,
                'instruction' => self::VERDICTS[$result]['instruction'],
                'tone' => self::VERDICTS[$result]['tone'],
                'message' => $message,
                'attendee' => $registration ? [
                    'name' => $registration->user->name,
                    'company' => $registration->user->company,
                ] : null,
                'reference_code' => $registration?->reference_code,
                'checked_in_at' => $firstCheckIn?->toIso8601String(),
                'activity' => $this->activityRow($scansOfThisTicket),
                'event' => $this->attendance($event),
            ];
        });
    }

    /**
     * Latest gate activity for an event: one row per ticket (or per unknown code), newest first.
     * Repeated scans of the same ticket are counted instead of listed again.
     *
     * @return list<array<string, mixed>>
     */
    public function recentActivity(Event $event, int $limit = 8): array
    {
        return $event->checkIns()
            ->with('registration.user')
            ->latest('id')
            ->limit(500)
            ->get()
            ->groupBy(fn (CheckIn $scan) => $this->activityKey($scan))
            ->take($limit)
            ->map(fn (Collection $scans) => $this->activityRow($scans))
            ->values()
            ->all();
    }

    /**
     * Manual lookup for when a QR code cannot be scanned:
     * finds ticket holders of this event by name, company or ticket code.
     *
     * @return list<array{reference_code: string, name: string, company: ?string, checked_in_at: ?string}>
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

    /**
     * Live headcount for an event.
     *
     * @return array{id: int, title: string, capacity: int, confirmed: int, checked_in: int, remaining: int}
     */
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

    private function activityKey(CheckIn $scan): string
    {
        return $scan->registration_id ? 'ticket-'.$scan->registration_id : 'code-'.md5((string) $scan->code_scanned);
    }

    /**
     * Summarize all scans of one ticket (newest first) as a single row for the gate screen.
     *
     * @param  Collection<int, CheckIn>  $scans
     * @return array{key: string, name: string, company: ?string, status: string, status_label: string, detail: string, last_scan_at: string, scans: int, repeat_note: ?string}
     */
    private function activityRow(Collection $scans): array
    {
        $latest = $scans->first();
        $registration = $latest->registration;
        $entered = $scans->firstWhere('result', 'admitted');

        [$status, $label, $detail] = match (true) {
            $entered !== null => ['inside', 'Inside', 'Entered '.$entered->created_at->format('g:i A')],
            $latest->result === 'duplicate' => ['inside', 'Inside', 'Entered '.$registration?->checked_in_at?->format('g:i A, M j')],
            $latest->result === 'cancelled' => ['refused', 'Not let in', 'Ticket was cancelled'],
            $latest->result === 'wrong_event' => ['refused', 'Not let in', 'Ticket is for another event'],
            default => ['refused', 'Not let in', 'Ticket not found'],
        };

        $count = $scans->count();

        return [
            'key' => $this->activityKey($latest),
            'name' => $registration?->user?->name ?? 'Unknown ticket',
            'company' => $registration?->user?->company ?? $latest->code_scanned,
            'status' => $status,
            'status_label' => $label,
            'detail' => $detail,
            'last_scan_at' => $latest->created_at->format('g:i A'),
            'scans' => $count,
            'repeat_note' => $count > 1 ? 'Scanned '.$count.' times · last '.$latest->created_at->format('g:i A') : null,
        ];
    }
}
