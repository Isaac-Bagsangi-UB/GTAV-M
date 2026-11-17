<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EventSpeakerController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_SPEAKER)],
            'topic' => ['nullable', 'string', 'max:160'],
        ]);

        $event->speakers()->syncWithoutDetaching([$data['user_id'] => ['topic' => $data['topic'] ?? null]]);
        AuditLog::record('event.speaker_assigned', 'Assigned speaker #'.$data['user_id'].' to "'.$event->title.'"');

        return back()->with('success', 'Speaker assigned.');
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        Gate::authorize('manage-event', $event);

        $event->speakers()->detach($user->id);
        AuditLog::record('event.speaker_removed', 'Removed speaker '.$user->name.' from "'.$event->title.'"');

        return back()->with('success', 'Speaker removed from this event.');
    }
}
