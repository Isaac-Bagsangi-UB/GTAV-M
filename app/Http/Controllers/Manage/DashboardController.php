<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Services\ChapterReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ticket volume and revenue across chapters, events and sub-events.
 * Administrators see every chapter; chapter managers see their own.
 */
class DashboardController extends Controller
{
    public function index(Request $request, ChapterReportService $reports): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $chapterId = $request->integer('chapter') ?: null;
        } else {
            abort_unless($user->chapter_id, 403, 'Your account is not assigned to a chapter yet. Ask an administrator.');
            $chapterId = $user->chapter_id;
        }

        $rows = $reports->chapters($chapterId);

        // Top-level events with their sub-events nested underneath.
        $events = $reports->eventsQuery($chapterId)->orderByDesc('starts_at')->get();
        $children = $events->whereNotNull('parent_id')->groupBy('parent_id');
        $topLevel = $events->whereNull('parent_id')->values();

        return view('manage.dashboard', [
            'rows' => $rows,
            'totals' => $reports->totals($rows),
            'topLevel' => $topLevel,
            'children' => $children,
            'chapters' => $user->isAdmin() ? Chapter::orderBy('name')->get() : collect(),
            'chapterId' => $chapterId,
        ]);
    }
}
