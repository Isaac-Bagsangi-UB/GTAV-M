<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Services\ChapterReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /** GET /api/v1/reports/chapters : ticket volume and revenue per chapter. */
    public function chapters(Request $request, ChapterReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->chapter_id, 403);

        $rows = $reports->chapters($user->isAdmin() ? null : $user->chapter_id);

        return response()->json(['data' => $rows->values(), 'totals' => $reports->totals($rows)]);
    }

    /** GET /api/v1/reports/chapters/{chapter} : one chapter, broken down by event. */
    public function chapter(Request $request, Chapter $chapter, ChapterReportService $reports): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->chapter_id === $chapter->id, 403);

        $events = $reports->eventsQuery($chapter->id)->orderBy('starts_at')->get()->map(fn ($e) => [
            'id' => $e->id,
            'parent_id' => $e->parent_id,
            'title' => $e->title,
            'type' => $e->type,
            'status' => $e->status,
            'starts_at' => $e->starts_at->toIso8601String(),
            'capacity' => $e->capacity,
            'tickets' => (int) $e->confirmed_registrations_count,
            'checked_in' => (int) $e->checked_in_count,
            'revenue' => (float) $e->revenue,
        ]);

        return response()->json([
            'data' => $reports->chapters($chapter->id)->first(),
            'events' => $events,
        ]);
    }
}
