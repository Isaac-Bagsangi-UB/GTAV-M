@extends('pdf.reports.layout')

@section('period'){{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}@endsection

@section('body')
    <p class="lead">
        Registrations made between {{ $from->format('F j, Y') }} and {{ $to->format('F j, Y') }}, grouped by chapter and event.
        Revenue counts registrations made in the period that are still confirmed.
    </p>

    <table class="kpis">
        <tr>
            <td>
                <div class="label">Revenue in period</div>
                <div class="value">${{ number_format($totals['revenue'], 2) }}</div>
                <div class="sub">from confirmed registrations</div>
            </td>
            <td>
                <div class="label">Confirmed registrations</div>
                <div class="value">{{ number_format($totals['confirmed']) }}</div>
                <div class="sub">made in the period</div>
            </td>
            <td>
                <div class="label">Cancelled</div>
                <div class="value">{{ number_format($totals['cancelled']) }}</div>
                <div class="sub">made in the period, later cancelled</div>
            </td>
            <td>
                <div class="label">Average ticket</div>
                <div class="value">${{ number_format($totals['confirmed'] > 0 ? $totals['revenue'] / $totals['confirmed'] : 0, 2) }}</div>
                <div class="sub">across {{ $totals['events'] }} {{ \Illuminate\Support\Str::plural('event', $totals['events']) }} with sales</div>
            </td>
        </tr>
    </table>

    @if ($byChapter->isEmpty())
        <div class="empty">No registrations were made in this period.</div>
    @else
        <h2>Sales by chapter and event</h2>
        <table class="data">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Event date</th>
                    <th class="num">Ticket price</th>
                    <th class="num">Confirmed</th>
                    <th class="num">Cancelled</th>
                    <th class="num">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byChapter as $chapterName => $events)
                    <tr class="group">
                        <td colspan="3">{{ $chapterName }}</td>
                        <td class="num">{{ number_format($events->sum('period_confirmed')) }}</td>
                        <td class="num">{{ number_format($events->sum('period_cancelled')) }}</td>
                        <td class="num">${{ number_format($events->sum(fn ($e) => (float) $e->period_revenue), 2) }}</td>
                    </tr>
                    @foreach ($events as $event)
                        <tr>
                            <td>
                                {{ $event->title }}
                                @if ($event->parent)<br><span class="muted">inside {{ $event->parent->title }}</span>@endif
                            </td>
                            <td>{{ $event->starts_at->format('M j, Y') }}</td>
                            <td class="num">{{ $event->priceLabel() }}</td>
                            <td class="num">{{ number_format($event->period_confirmed) }}</td>
                            <td class="num">{{ number_format($event->period_cancelled) }}</td>
                            <td class="num">${{ number_format((float) $event->period_revenue, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="num">{{ number_format($totals['confirmed']) }}</td>
                    <td class="num">{{ number_format($totals['cancelled']) }}</td>
                    <td class="num">${{ number_format($totals['revenue'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <p class="note">Payments are recorded at the ticket price when a registration is made. A registration is counted in the period in which it was made, whatever the date of the event.</p>
@endsection
