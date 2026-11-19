<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\Event;
use App\Models\User;
use App\Services\ChapterReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Event management for administrators (all chapters) and chapter managers (own chapter). */
class EventController extends Controller
{
    public function index(Request $request, ChapterReportService $reports): View
    {
        $events = $reports->eventsQuery($this->scopeChapterId($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->input('q').'%'))
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->withQueryString();

        return view('manage.events.index', ['events' => $events]);
    }

    public function create(Request $request): View
    {
        $event = new Event([
            'status' => 'draft',
            'type' => 'expo',
            'ticket_price' => 0,
            'parent_id' => $request->integer('parent') ?: null,
        ]);

        return view('manage.events.form', $this->formData($request, $event));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $cover = $this->validatedCover($request);

        $event = Event::create($data);
        $this->applyCover($event, $request, $cover);
        AuditLog::record('event.created', 'Created event "'.$event->title.'"');

        return redirect()->route('manage.events.show', $event)->with('success', 'Event created.');
    }

    public function show(Request $request, Event $event, ChapterReportService $reports): View
    {
        Gate::authorize('manage-event', $event);

        $event = $reports->eventsQuery()->with('speakers')->findOrFail($event->id);

        return view('manage.events.show', [
            'event' => $event,
            'children' => $reports->eventsQuery()->where('parent_id', $event->id)->orderBy('starts_at')->get(),
            // ?attendance=1 narrows the list to the attendance record: who checked in, and when.
            'registrations' => $event->registrations()->with('user')
                ->when($request->boolean('attendance'), fn ($q) => $q->whereNotNull('checked_in_at')->orderBy('checked_in_at'))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'materials' => $event->materials()->with('uploader')->get(),
            'speakerOptions' => User::where('role', User::ROLE_SPEAKER)
                ->whereNotIn('id', $event->speakers->pluck('id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Request $request, Event $event): View
    {
        Gate::authorize('manage-event', $event);

        return view('manage.events.form', $this->formData($request, $event));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        $data = $this->validated($request, $event);
        $cover = $this->validatedCover($request);

        $event->update($data);
        $this->applyCover($event, $request, $cover);
        AuditLog::record('event.updated', 'Updated event "'.$event->title.'" (status '.$event->status.', capacity '.$event->capacity.')');

        return redirect()->route('manage.events.show', $event)->with('success', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        if ($event->confirmedRegistrations()->exists()) {
            return back()->with('error', 'This event has confirmed registrations. Set its status to Cancelled instead of deleting it.');
        }

        $coverPath = $event->cover_path;
        $event->delete();
        $this->deleteCoverFile($coverPath);
        AuditLog::record('event.deleted', 'Deleted event "'.$event->title.'"');

        return redirect()->route('manage.events.index')->with('success', 'Event deleted.');
    }

    /** One clean attendee list per event, replacing the per-event spreadsheets. */
    public function export(Event $event): StreamedResponse
    {
        Gate::authorize('manage-event', $event);

        $filename = 'registrations-event-'.$event->id.'.csv';
        AuditLog::record('report.generated', 'Exported the registration list (CSV) for "'.$event->title.'"');

        return response()->streamDownload(function () use ($event) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Name', 'Email', 'Company', 'Status', 'Amount paid', 'Registered at', 'Checked in at']);

            $event->registrations()->with('user')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->reference_code,
                        $r->user->name,
                        $r->user->email,
                        $r->user->company,
                        $r->status,
                        $r->amount_paid,
                        $r->created_at?->format('Y-m-d H:i'),
                        $r->checked_in_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Checks the optional venue photo: a real image, at least 800 × 450 pixels, at most 4 MB.
     *
     * @return array{cover?: UploadedFile, cover_credit?: string|null, remove_cover?: bool}
     */
    private function validatedCover(Request $request): array
    {
        return $request->validate([
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=800,min_height=450'],
            'cover_credit' => ['nullable', 'string', 'max:255'],
            'remove_cover' => ['nullable', 'boolean'],
        ], [
            'cover.dimensions' => 'The photo must be at least 800 × 450 pixels so it stays sharp on the event page.',
        ]);
    }

    /** Stores a new venue photo on the private disk, or removes the current one when asked. */
    private function applyCover(Event $event, Request $request, array $cover): void
    {
        $oldPath = $event->cover_path;

        if ($request->hasFile('cover')) {
            $event->forceFill([
                'cover_path' => $request->file('cover')->store('event-covers/'.$event->id, 'local'),
                'cover_credit' => $cover['cover_credit'] ?? null,
            ])->save();
            $this->deleteCoverFile($oldPath, $event);
            AuditLog::record('event.cover_uploaded', 'Uploaded a cover photo for "'.$event->title.'"');

            return;
        }

        if (! empty($cover['remove_cover']) && $oldPath) {
            $event->forceFill(['cover_path' => null, 'cover_credit' => null])->save();
            $this->deleteCoverFile($oldPath, $event);
            AuditLog::record('event.cover_removed', 'Removed the cover photo of "'.$event->title.'"');

            return;
        }

        if ($oldPath && array_key_exists('cover_credit', $cover)) {
            $event->forceFill(['cover_credit' => $cover['cover_credit']])->save();
        }
    }

    /** Deletes a cover file unless another event still shows it (the demo data shares one photo per city). */
    private function deleteCoverFile(?string $path, ?Event $except = null): void
    {
        if (! $path) {
            return;
        }

        $stillUsed = Event::where('cover_path', $path)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->exists();

        if (! $stillUsed) {
            Storage::disk('local')->delete($path);
        }
    }

    /** Null for administrators (no restriction); the manager's own chapter otherwise. */
    private function scopeChapterId(Request $request): ?int
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $request->integer('chapter') ?: null;
        }

        abort_unless($user->chapter_id, 403, 'Your account is not assigned to a chapter yet. Ask an administrator.');

        return $user->chapter_id;
    }

    private function formData(Request $request, Event $event): array
    {
        $user = $request->user();
        $chapterId = $user->isAdmin() ? null : $this->scopeChapterId($request);

        return [
            'event' => $event,
            'chapters' => $user->isAdmin() ? Chapter::orderBy('name')->get() : collect(),
            // Only top-level events can have sub-events.
            'parents' => Event::whereNull('parent_id')
                ->when($chapterId, fn ($q) => $q->where('chapter_id', $chapterId))
                ->when($event->exists, fn ($q) => $q->where('id', '!=', $event->id))
                ->with('chapter')
                ->orderByDesc('starts_at')
                ->get(),
        ];
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $user = $request->user();

        $data = $request->validate([
            'chapter_id' => [$user->isAdmin() ? 'required' : 'nullable', 'integer', 'exists:chapters,id'],
            'parent_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(Event::TYPES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'venue' => ['required', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_opens_at' => ['required', 'date', 'before:registration_closes_at'],
            'registration_closes_at' => ['required', 'date', 'before_or_equal:ends_at'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'ticket_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
        ]);

        // Managers can only ever write to their own chapter, whatever the form says.
        if (! $user->isAdmin()) {
            $data['chapter_id'] = $this->scopeChapterId($request);
        }
        $data['chapter_id'] = (int) $data['chapter_id'];
        $data['parent_id'] = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;

        if ($data['parent_id']) {
            $parent = Event::findOrFail($data['parent_id']);

            if ($parent->chapter_id !== $data['chapter_id']) {
                throw ValidationException::withMessages(['parent_id' => 'The parent event must belong to the same chapter.']);
            }
            if ($parent->parent_id || ($event && $parent->id === $event->id)) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a top-level event as the parent.']);
            }
            if ($event && $event->children()->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'This event already has sub-events, so it cannot become a sub-event itself.']);
            }
        }

        if ($event) {
            $confirmed = $event->confirmedRegistrations()->count();
            if ($data['capacity'] < $confirmed) {
                throw ValidationException::withMessages([
                    'capacity' => "Capacity cannot go below the {$confirmed} confirmed registrations.",
                ]);
            }
        }

        return $data;
    }
}
