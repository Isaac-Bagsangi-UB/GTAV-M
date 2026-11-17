<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Workshop materials: private files released only to verified registrants. */
class MaterialController extends Controller
{
    public function download(Material $material): StreamedResponse
    {
        Gate::authorize('access-materials', $material->event);

        abort_unless(Storage::disk('local')->exists($material->path), 404, 'This file is no longer available.');

        return Storage::disk('local')->download($material->path, $material->original_name);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('upload-materials', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,ppt,pptx,doc,docx,xls,xlsx,zip,txt,png,jpg,jpeg'],
        ]);

        $file = $request->file('file');

        $event->materials()->create([
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'path' => $file->store('materials/'.$event->id, 'local'),
            'original_name' => mb_substr($file->getClientOriginalName(), -255), // column holds 255 characters
            'size' => $file->getSize(),
        ]);
        AuditLog::record('material.uploaded', 'Uploaded "'.$data['title'].'" to "'.$event->title.'"');

        return back()->with('success', 'Material uploaded. Registered attendees can download it now.');
    }

    public function destroy(Material $material): RedirectResponse
    {
        Gate::authorize('upload-materials', $material->event);

        Storage::disk('local')->delete($material->path);
        $material->delete();
        AuditLog::record('material.removed', 'Removed material "'.$material->title.'"');

        return back()->with('success', 'Material removed.');
    }
}
