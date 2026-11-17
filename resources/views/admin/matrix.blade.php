@extends('layouts.app')

@section('title', 'Participation matrix')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administration</div>
            <h1>Event participation matrix</h1>
        </div>
        <form method="GET" action="{{ route('admin.matrix') }}" class="filters" style="margin:0">
            <select name="chapter" data-autosubmit aria-label="Chapter">
                @foreach ($chapters as $option)
                    <option value="{{ $option->id }}" @selected($chapter && $chapter->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (! $chapter || $events->isEmpty())
        <div class="empty">This chapter has no published events yet.</div>
    @else
        <div class="stat-grid">
            <div class="stat">
                <div class="stat-label">People</div>
                <div class="stat-value">{{ number_format($people->total()) }}</div>
                <div class="stat-note">with at least one ticket in {{ $chapter->code }}</div>
            </div>
            <div class="stat accent">
                <div class="stat-label">Multi-event attendees</div>
                <div class="stat-value">{{ number_format($multiEventCount) }}</div>
                <div class="stat-note">registered for two or more events, one record each</div>
            </div>
            <div class="stat">
                <div class="stat-label">Events</div>
                <div class="stat-value">{{ $events->count() }}</div>
                <div class="stat-note">including sub-events</div>
            </div>
        </div>

        <div class="legend">
            <span><span class="dot dot-registered">R</span> Registered</span>
            <span><span class="dot dot-attended">&#10003;</span> Checked in at the gate</span>
            <span>Blank means not registered</span>
        </div>

        <div class="table-wrap">
            <table class="table matrix">
                <thead>
                    <tr>
                        <th>Attendee</th>
                        <th class="num">Events</th>
                        @foreach ($events as $event)
                            <th class="event-col">
                                <a href="{{ route('manage.events.show', $event) }}">{{ Str::limit($event->title, 42) }}</a>
                                <div class="muted">{{ $event->starts_at->format('M j') }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($people as $person)
                        <tr>
                            <td>
                                <strong>{{ $person->name }}</strong>
                                <div class="muted small">{{ $person->company }}</div>
                            </td>
                            <td class="num">{{ $person->events_in_chapter }}</td>
                            @foreach ($events as $event)
                                @php
                                    $cell = $person->registrations->get($event->id);
                                @endphp
                                <td class="cell">
                                    @if ($cell && $cell->checked_in_at)
                                        <span class="dot dot-attended" title="Checked in {{ $cell->checked_in_at->format('M j, g:i A') }}">&#10003;</span>
                                    @elseif ($cell)
                                        <span class="dot dot-registered" title="Registered, reference {{ $cell->reference_code }}">R</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $events->count() + 2 }}" class="muted">No registrations in this chapter yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $people->links() }}
    @endif
@endsection
