@extends('layouts.app')

@section('title', 'Dashboard')
@section('noindex', '1')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">{{ $isAdmin ? 'All chapters' : auth()->user()->chapter->name }}</div>
            <h1>Ticket volume and revenue</h1>
        </div>
        <div class="actions">
            @if ($isAdmin)
                <form method="GET" action="{{ route('manage.dashboard') }}" class="filters" style="margin:0">
                    <select name="chapter" data-autosubmit aria-label="Chapter">
                        <option value="">All chapters</option>
                        @foreach ($chapters as $chapter)
                            <option value="{{ $chapter->id }}" @selected($chapterId === $chapter->id)>{{ $chapter->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a class="btn btn-primary" href="{{ route('manage.events.create') }}">New event</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat accent">
            <div class="stat-label">Ticket revenue</div>
            <div class="stat-value">${{ number_format($totals['revenue'], 0) }}</div>
            <div class="stat-note">confirmed registrations</div>
        </div>
        <div class="stat">
            <div class="stat-label">Tickets issued</div>
            <div class="stat-value">{{ number_format($totals['tickets']) }}</div>
            <div class="stat-note">of {{ number_format($totals['capacity']) }} seats ({{ $totals['fill_rate'] }}%)</div>
        </div>
        <div class="stat teal">
            <div class="stat-label">Checked in</div>
            <div class="stat-value">{{ number_format($totals['checked_in']) }}</div>
            <div class="stat-note">{{ $totals['attendance_rate'] }}% turnout at events held so far</div>
        </div>
        <div class="stat">
            <div class="stat-label">Events</div>
            <div class="stat-value">{{ number_format($totals['events']) }}</div>
            <div class="stat-note">including sub-events</div>
        </div>
    </div>

    @if ($isAdmin && ! $chapterId)
        <h2>Chapter performance</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Chapter</th>
                        <th class="num">Events</th>
                        <th class="num">Tickets</th>
                        <th>Seats filled</th>
                        <th class="num">Checked in</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('manage.dashboard', ['chapter' => $row['id']]) }}"><strong>{{ $row['name'] }}</strong></a>
                                <div class="muted small">{{ $row['region'] }}</div>
                            </td>
                            <td class="num">{{ $row['events'] }}</td>
                            <td class="num">{{ number_format($row['tickets']) }} / {{ number_format($row['capacity']) }}</td>
                            <td>
                                <div class="meter"><span style="width: {{ min(100, $row['fill_rate']) }}%"></span></div>
                                <span class="small muted">{{ $row['fill_rate'] }}%</span>
                            </td>
                            <td class="num">{{ number_format($row['checked_in']) }} <span class="muted small">({{ $row['attendance_rate'] }}%)</span></td>
                            <td class="num"><strong>${{ number_format($row['revenue'], 2) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>All chapters</td>
                        <td class="num">{{ $totals['events'] }}</td>
                        <td class="num">{{ number_format($totals['tickets']) }} / {{ number_format($totals['capacity']) }}</td>
                        <td>{{ $totals['fill_rate'] }}%</td>
                        <td class="num">{{ number_format($totals['checked_in']) }}</td>
                        <td class="num">${{ number_format($totals['revenue'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <h2>Events and sub-events</h2>
    <p class="muted">Each event's row shows its own tickets. The "with sub-events" figure adds the workshops and sessions inside it.</p>

    @if ($topLevel->isEmpty())
        <div class="empty">No events yet. <a href="{{ route('manage.events.create') }}">Create the first one</a>.</div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="num">Tickets</th>
                        <th class="num">Checked in</th>
                        <th class="num">Revenue</th>
                        <th class="num">With sub-events</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topLevel as $event)
                        @php
                            $subs = $children->get($event->id, collect());
                            $rollup = (float) $event->revenue + $subs->sum(fn ($s) => (float) $s->revenue);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('manage.events.show', $event) }}"><strong>{{ $event->title }}</strong></a>
                                <div class="muted small">{{ $event->chapter->code }} · {{ $event->typeLabel() }}</div>
                            </td>
                            <td class="nowrap">{{ $event->starts_at->format('M j, Y') }}</td>
                            <td><span class="badge badge-{{ $event->status }}">{{ ucfirst($event->status) }}</span></td>
                            <td class="num">{{ number_format($event->confirmed_registrations_count) }} / {{ number_format($event->capacity) }}</td>
                            <td class="num">{{ number_format($event->checked_in_count) }}</td>
                            <td class="num">${{ number_format((float) $event->revenue, 2) }}</td>
                            <td class="num"><strong>${{ number_format($rollup, 2) }}</strong></td>
                        </tr>
                        @foreach ($subs as $sub)
                            <tr class="child">
                                <td>
                                    &#8627; <a href="{{ route('manage.events.show', $sub) }}">{{ $sub->title }}</a>
                                </td>
                                <td class="nowrap">{{ $sub->starts_at->format('M j, Y') }}</td>
                                <td><span class="badge badge-{{ $sub->status }}">{{ ucfirst($sub->status) }}</span></td>
                                <td class="num">{{ number_format($sub->confirmed_registrations_count) }} / {{ number_format($sub->capacity) }}</td>
                                <td class="num">{{ number_format($sub->checked_in_count) }}</td>
                                <td class="num">${{ number_format((float) $sub->revenue, 2) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
