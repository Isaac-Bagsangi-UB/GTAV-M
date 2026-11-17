<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Services\ChapterReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function index(ChapterReportService $reports): View
    {
        return view('admin.chapters.index', ['rows' => $reports->chapters()]);
    }

    public function create(): View
    {
        return view('admin.chapters.form', ['chapter' => new Chapter]);
    }

    public function store(Request $request): RedirectResponse
    {
        $chapter = Chapter::create($this->validated($request));
        AuditLog::record('chapter.created', 'Created chapter '.$chapter->name.' ('.$chapter->code.')');

        return redirect()->route('admin.chapters.index')->with('success', 'Chapter created.');
    }

    public function edit(Chapter $chapter): View
    {
        return view('admin.chapters.form', ['chapter' => $chapter]);
    }

    public function update(Request $request, Chapter $chapter): RedirectResponse
    {
        $chapter->update($this->validated($request, $chapter));
        AuditLog::record('chapter.updated', 'Updated chapter '.$chapter->name.' ('.$chapter->code.')');

        return redirect()->route('admin.chapters.index')->with('success', 'Chapter updated.');
    }

    public function destroy(Chapter $chapter): RedirectResponse
    {
        if ($chapter->events()->exists()) {
            return back()->with('error', 'This chapter still has events. Move or delete them first.');
        }

        // Managers and gate staff would silently lose access to everything, so they are moved first.
        $staff = $chapter->users()->count();
        if ($staff > 0) {
            return back()->with('error', "This chapter still has {$staff} staff ".($staff === 1 ? 'account' : 'accounts').'. Assign them to another chapter first.');
        }

        $chapter->delete();
        AuditLog::record('chapter.deleted', 'Deleted chapter '.$chapter->name);

        return redirect()->route('admin.chapters.index')->with('success', 'Chapter deleted.');
    }

    private function validated(Request $request, ?Chapter $chapter = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:8', 'alpha_num', Rule::unique('chapters', 'code')->ignore($chapter?->id)],
            'region' => ['required', 'string', 'max:120'],
        ]);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
