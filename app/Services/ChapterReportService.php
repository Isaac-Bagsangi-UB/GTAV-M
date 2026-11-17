<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Chapter performance tracking: rolls registrations up from
 * sub-events to events to regional chapters.
 */
class ChapterReportService
{
    /** One row per chapter with ticket volume, revenue and attendance. */
    public function chapters(?int $chapterId = null): Collection
    {
        $confirmed = fn ($q) => $q->where('registrations.status', Registration::CONFIRMED);
        // Drafts are not on sale yet, so they count toward neither the event total nor capacity.
        $live = fn ($q) => $q->where('events.status', '!=', 'draft');

        return Chapter::query()
            ->when($chapterId, fn (Builder $q) => $q->whereKey($chapterId))
            ->withCount(['events' => $live])
            ->withSum(['events as capacity_total' => $live], 'capacity')
            ->withCount(['registrations as tickets' => $confirmed])
            // Attendance is measured only at events that have started: nobody can be scanned in before that.
            ->withCount(['registrations as tickets_started' => fn ($q) => $confirmed($q)->where('events.starts_at', '<=', now())])
            ->withCount(['registrations as checked_in' => fn ($q) => $confirmed($q)->whereNotNull('registrations.checked_in_at')])
            ->withSum(['registrations as revenue' => $confirmed], 'registrations.amount_paid')
            ->orderBy('name')
            ->get()
            ->map(fn (Chapter $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'region' => $c->region,
                'events' => (int) $c->events_count,
                'capacity' => (int) $c->capacity_total,
                'tickets' => (int) $c->tickets,
                'tickets_started' => (int) $c->tickets_started,
                'checked_in' => (int) $c->checked_in,
                'revenue' => (float) $c->revenue,
                'fill_rate' => $c->capacity_total > 0 ? round($c->tickets / $c->capacity_total * 100, 1) : 0.0,
                'attendance_rate' => $c->tickets_started > 0 ? round($c->checked_in / $c->tickets_started * 100, 1) : 0.0,
            ]);
    }

    /** Events query with the same numbers attached to each event. */
    public function eventsQuery(?int $chapterId = null): Builder
    {
        return Event::query()
            ->when($chapterId, fn (Builder $q) => $q->where('chapter_id', $chapterId))
            ->with(['chapter', 'parent'])
            ->withCount([
                'confirmedRegistrations',
                'confirmedRegistrations as checked_in_count' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->withSum('confirmedRegistrations as revenue', 'amount_paid');
    }

    public function totals(Collection $chapterRows): array
    {
        $tickets = $chapterRows->sum('tickets');
        $capacity = $chapterRows->sum('capacity');
        $checkedIn = $chapterRows->sum('checked_in');
        $ticketsStarted = $chapterRows->sum('tickets_started');

        return [
            'chapters' => $chapterRows->count(),
            'events' => $chapterRows->sum('events'),
            'tickets' => $tickets,
            'capacity' => $capacity,
            'tickets_started' => $ticketsStarted,
            'checked_in' => $checkedIn,
            'revenue' => $chapterRows->sum('revenue'),
            'fill_rate' => $capacity > 0 ? round($tickets / $capacity * 100, 1) : 0.0,
            'attendance_rate' => $ticketsStarted > 0 ? round($checkedIn / $ticketsStarted * 100, 1) : 0.0,
        ];
    }
}
