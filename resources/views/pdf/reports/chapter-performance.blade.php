@extends('pdf.reports.layout')

@section('body')
    <p class="lead">
        Ticket sales, revenue and turnout for {{ $scope === 'All chapters' ? 'every regional chapter' : $scope }}, rolled up from sub-events to events to chapters.
        Draft events are excluded. Turnout counts only events that have already started.
    </p>

    <table class="kpis">
        <tr>
            <td>
                <div class="label">Ticket revenue</div>
                <div class="value">${{ number_format($totals['revenue'], 2) }}</div>
                <div class="sub">confirmed registrations</div>
            </td>
            <td>
                <div class="label">Tickets issued</div>
                <div class="value">{{ number_format($totals['tickets']) }}</div>
                <div class="sub">of {{ number_format($totals['capacity']) }} seats · {{ $totals['fill_rate'] }}% filled</div>
            </td>
            <td>
                <div class="label">Checked in at the gate</div>
                <div class="value">{{ number_format($totals['checked_in']) }}</div>
                <div class="sub">{{ $totals['attendance_rate'] }}% turnout at events held so far</div>
            </td>
            <td>
                <div class="label">Events</div>
                <div class="value">{{ number_format($totals['events']) }}</div>
                <div class="sub">across {{ $totals['chapters'] }} {{ \Illuminate\Support\Str::plural('chapter', $totals['chapters']) }}, incl. sub-events</div>
            </td>
        </tr>
    </table>

    <h2>Chapter summary</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Chapter</th>
                <th>Region</th>
                <th class="num">Events</th>
                <th class="num">Capacity</th>
                <th class="num">Tickets</th>
                <th class="num">Filled</th>
                <th class="num">Checked in</th>
                <th class="num">Turnout</th>
                <th class="num">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td><strong>{{ $row['name'] }}</strong> <span class="muted">({{ $row['code'] }})</span></td>
                    <td>{{ $row['region'] }}</td>
                    <td class="num">{{ $row['events'] }}</td>
                    <td class="num">{{ number_format($row['capacity']) }}</td>
                    <td class="num">{{ number_format($row['tickets']) }}</td>
                    <td class="num">{{ $row['fill_rate'] }}%</td>
                    <td class="num">{{ number_format($row['checked_in']) }}</td>
                    <td class="num">{{ $row['attendance_rate'] }}%</td>
                    <td class="num">${{ number_format($row['revenue'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($rows->count() > 1)
            <tfoot>
                <tr>
                    <td colspan="2">All chapters</td>
                    <td class="num">{{ $totals['events'] }}</td>
                    <td class="num">{{ number_format($totals['capacity']) }}</td>
                    <td class="num">{{ number_format($totals['tickets']) }}</td>
                    <td class="num">{{ $totals['fill_rate'] }}%</td>
                    <td class="num">{{ number_format($totals['checked_in']) }}</td>
                    <td class="num">{{ $totals['attendance_rate'] }}%</td>
                    <td class="num">${{ number_format($totals['revenue'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @foreach ($rows as $row)
        @php
            $chapterEvents = $topLevel->get($row['id'], collect());
        @endphp
        <h2>{{ $row['name'] }} <small>events and sub-events</small></h2>
        @if ($chapterEvents->isEmpty())
            <div class="empty">No published events in this chapter yet.</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="num">Tickets / capacity</th>
                        <th class="num">Checked in</th>
                        <th class="num">Revenue</th>
                        <th class="num">With sub-events</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($chapterEvents as $event)
                        @php
                            $subs = $children->get($event->id, collect());
                            $rollup = (float) $event->revenue + $subs->sum(fn ($s) => (float) $s->revenue);
                        @endphp
                        <tr>
                            <td><strong>{{ $event->title }}</strong><br><span class="muted">{{ $event->typeLabel() }}</span></td>
                            <td>{{ $event->starts_at->format('M j, Y') }}</td>
                            <td><span class="tag">{{ ucfirst($event->status) }}</span></td>
                            <td class="num">{{ number_format($event->confirmed_registrations_count) }} / {{ number_format($event->capacity) }}</td>
                            <td class="num">{{ number_format($event->checked_in_count) }}</td>
                            <td class="num">${{ number_format((float) $event->revenue, 2) }}</td>
                            <td class="num"><strong>${{ number_format($rollup, 2) }}</strong></td>
                        </tr>
                        @foreach ($subs as $sub)
                            <tr class="sub">
                                <td class="first">&rarr; {{ $sub->title }}</td>
                                <td>{{ $sub->starts_at->format('M j, Y') }}</td>
                                <td><span class="tag">{{ ucfirst($sub->status) }}</span></td>
                                <td class="num">{{ number_format($sub->confirmed_registrations_count) }} / {{ number_format($sub->capacity) }}</td>
                                <td class="num">{{ number_format($sub->checked_in_count) }}</td>
                                <td class="num">${{ number_format((float) $sub->revenue, 2) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <p class="note">Revenue is the ticket price recorded on each confirmed registration. Cancelled registrations are excluded from every figure.</p>
@endsection
