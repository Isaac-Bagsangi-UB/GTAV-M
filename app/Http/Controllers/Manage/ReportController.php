<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\Registration;
use App\Services\ChapterReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The three management reports, each generated as a PDF:
 *   1. Chapter Performance      tickets, revenue and attendance rolled up by chapter and event
 *   2. Event Attendance         who registered for one event, who was checked in and when
 *   3. Registration and Revenue registrations and ticket income received in a date range
 *
 * Administrators can report on every chapter; chapter managers only on their own.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $chapterId = $this->scopeChapterId($request);

        return view('manage.reports.index', [
            'chapters' => $request->user()->isAdmin() ? Chapter::orderBy('name')->get() : collect(),
            'eventGroups' => $this->eventGroups($chapterId),
            'defaultFrom' => now()->startOfMonth()->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    /** Report 1: Chapter Performance. */
    public function chapterPerformance(Request $request, ChapterReportService $reports): Response
    {
        $chapterId = $this->scopeChapterId($request);
        $rows = $reports->chapters($chapterId);
        abort_if($rows->isEmpty(), 404, 'That chapter does not exist.');

        $events = $reports->eventsQuery($chapterId)
            ->where('status', '!=', 'draft')
            ->orderBy('starts_at')
            ->get();

        $scope = $chapterId ? $rows->first()['name'] : 'All chapters';
        AuditLog::record('report.generated', 'Generated the Chapter Performance report ('.$scope.')');

        return Pdf::loadView('pdf.reports.chapter-performance', [
            'title' => 'Chapter Performance Report',
            'scope' => $scope,
            'generatedBy' => $request->user(),
            'rows' => $rows,
            'totals' => $reports->totals($rows),
            'topLevel' => $events->whereNull('parent_id')->groupBy('chapter_id'),
            'children' => $events->whereNotNull('parent_id')->groupBy('parent_id'),
        ])->setPaper('a4', 'landscape')->download('chapter-performance-'.now()->format('Ymd-His').'.pdf');
    }

    /** Report 2: Event Attendance. */
    public function eventAttendance(Request $request): Response
    {
        $data = $request->validate(['event_id' => ['required', 'integer', 'exists:events,id']]);

        $event = Event::with(['chapter', 'parent'])->findOrFail($data['event_id']);
        Gate::authorize('manage-event', $event);

        $registrations = $event->registrations()
            ->with('user')
            ->where('status', Registration::CONFIRMED)
            ->get()
            ->sortBy(fn (Registration $r) => mb_strtolower($r->user->name))
            ->values();

        $checkedIn = $registrations->whereNotNull('checked_in_at')->count();

        AuditLog::record('report.generated', 'Generated the Event Attendance report for "'.$event->title.'"');

        return Pdf::loadView('pdf.reports.event-attendance', [
            'title' => 'Event Attendance Report',
            'scope' => $event->chapter->name,
            'generatedBy' => $request->user(),
            'event' => $event,
            'registrations' => $registrations,
            'summary' => [
                'capacity' => $event->capacity,
                'confirmed' => $registrations->count(),
                'checked_in' => $checkedIn,
                'not_checked_in' => $registrations->count() - $checkedIn,
                'cancelled' => $event->registrations()->where('status', Registration::CANCELLED)->count(),
                'attendance_rate' => $registrations->count() > 0 ? round($checkedIn / $registrations->count() * 100, 1) : 0.0,
                'revenue' => (float) $registrations->sum('amount_paid'),
                'ended' => $event->ends_at->isPast(),
            ],
        ])->setPaper('a4')->download('event-attendance-'.$event->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /** Report 3: Registration and Revenue for a date range. */
    public function registrationRevenue(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay();
        $chapterId = $this->scopeChapterId($request);

        $inPeriod = fn (string $status) => fn ($q) => $q
            ->where('registrations.status', $status)
            ->whereBetween('registrations.created_at', [$from, $to]);

        $events = Event::query()
            ->when($chapterId, fn ($q) => $q->where('chapter_id', $chapterId))
            ->with(['chapter', 'parent'])
            ->withCount([
                'registrations as period_confirmed' => $inPeriod(Registration::CONFIRMED),
                'registrations as period_cancelled' => $inPeriod(Registration::CANCELLED),
            ])
            ->withSum(['registrations as period_revenue' => $inPeriod(Registration::CONFIRMED)], 'amount_paid')
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $e) => $e->period_confirmed + $e->period_cancelled > 0)
            ->values();

        $scope = $chapterId ? (Chapter::find($chapterId)?->name ?? 'Chapter') : 'All chapters';
        AuditLog::record('report.generated', 'Generated the Registration and Revenue report ('.$scope.', '.$data['from'].' to '.$data['to'].')');

        return Pdf::loadView('pdf.reports.registration-revenue', [
            'title' => 'Registration and Revenue Report',
            'scope' => $scope,
            'generatedBy' => $request->user(),
            'from' => $from,
            'to' => $to,
            'byChapter' => $events->groupBy(fn (Event $e) => $e->chapter->name)->sortKeys(),
            'totals' => [
                'events' => $events->count(),
                'confirmed' => (int) $events->sum('period_confirmed'),
                'cancelled' => (int) $events->sum('period_cancelled'),
                'revenue' => (float) $events->sum(fn (Event $e) => (float) $e->period_revenue),
            ],
        ])->setPaper('a4')->download('registration-revenue-'.$data['from'].'-to-'.$data['to'].'.pdf');
    }

    /**
     * Events for the Event Attendance picker: those already held or in progress first (newest first),
     * because they have attendance to report, then upcoming ones (soonest first).
     */
    private function eventGroups(?int $chapterId): array
    {
        $events = Event::query()
            ->when($chapterId, fn ($q) => $q->where('chapter_id', $chapterId))
            ->where('status', '!=', 'draft')
            ->with('chapter')
            ->get();

        return array_filter([
            'Held or in progress' => $events->filter(fn (Event $e) => $e->starts_at->lte(now()))->sortByDesc('starts_at')->values(),
            'Upcoming' => $events->filter(fn (Event $e) => $e->starts_at->gt(now()))->sortBy('starts_at')->values(),
        ], fn ($group) => $group->isNotEmpty());
    }

    /** Null for administrators who chose "all chapters"; otherwise the chapter the report is limited to. */
    private function scopeChapterId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->integer('chapter') ?: null;
        }

        abort_unless($user->chapter_id, 403, 'Your account is not assigned to a chapter yet. Ask an administrator.');

        return $user->chapter_id;
    }
}
