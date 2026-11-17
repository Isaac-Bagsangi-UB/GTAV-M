@extends('pdf.reports.layout')

@section('body')
    <h2 style="margin-top:0">{{ $event->title }}</h2>
    <p class="lead">
        {{ $event->typeLabel() }}@if ($event->parent), part of {{ $event->parent->title }}@endif ·
        {{ $event->dateRange() }} · {{ $event->venue }}@if ($event->city), {{ $event->city }}@endif
        <br>
        @if ($summary['ended'])
            This event has ended. The figures below are its final attendance record.
        @elseif ($event->starts_at->isPast())
            This event is in progress. The figures below are live as of the time this report was generated.
        @else
            This event has not started yet. Nobody can be checked in before it opens.
        @endif
    </p>

    <table class="kpis">
        <tr>
            <td>
                <div class="label">Ticket holders</div>
                <div class="value">{{ number_format($summary['confirmed']) }}</div>
                <div class="sub">of {{ number_format($summary['capacity']) }} seats</div>
            </td>
            <td>
                <div class="label">Checked in</div>
                <div class="value">{{ number_format($summary['checked_in']) }}</div>
                <div class="sub">{{ $summary['attendance_rate'] }}% of ticket holders</div>
            </td>
            <td>
                <div class="label">{{ $summary['ended'] ? 'Did not attend' : 'Not yet arrived' }}</div>
                <div class="value">{{ number_format($summary['not_checked_in']) }}</div>
                <div class="sub">{{ number_format($summary['cancelled']) }} {{ \Illuminate\Support\Str::plural('registration', $summary['cancelled']) }} cancelled</div>
            </td>
            <td>
                <div class="label">Ticket revenue</div>
                <div class="value">${{ number_format($summary['revenue'], 2) }}</div>
                <div class="sub">at {{ $event->priceLabel() }} per ticket</div>
            </td>
        </tr>
    </table>

    <h2>Attendance record <small>{{ number_format($registrations->count()) }} ticket {{ \Illuminate\Support\Str::plural('holder', $registrations->count()) }}, A to Z</small></h2>
    @if ($registrations->isEmpty())
        <div class="empty">Nobody holds a ticket for this event.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%">#</th>
                    <th style="width:26%">Attendee</th>
                    <th style="width:24%">Company</th>
                    <th style="width:15%">Reference</th>
                    <th style="width:13%">Registered</th>
                    <th style="width:18%">Checked in</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($registrations as $i => $registration)
                    <tr>
                        <td class="muted">{{ $i + 1 }}</td>
                        <td><strong>{{ $registration->user->name }}</strong><br><span class="muted">{{ $registration->user->email }}</span></td>
                        <td>{{ $registration->user->company ?: '-' }}</td>
                        <td>{{ $registration->reference_code }}</td>
                        <td>{{ $registration->created_at->format('M j, Y') }}</td>
                        <td>
                            @if ($registration->checked_in_at)
                                <span class="yes">Yes</span> · {{ $registration->checked_in_at->format('M j, g:i A') }}
                            @else
                                <span class="no">{{ $summary['ended'] ? 'No' : 'Not yet' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="note">Check-in times come from gate scans. Each pass admits one person once; repeat scans are recorded in the scan log but do not change these figures.</p>
@endsection
