@extends('layouts.app')

@section('title', 'My passes')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ auth()->user()->company }}</div>
            <h1>My passes</h1>
        </div>
        <a class="btn btn-primary" href="{{ route('events.index') }}">Register for another event</a>
    </div>

    <h2>Upcoming</h2>
    @forelse ($upcoming as $registration)
        <div class="ticket">
            <div class="ticket-main">
                <span class="badge badge-type">{{ $registration->event->typeLabel() }}</span>
                @if ($registration->checked_in_at)
                    <span class="badge badge-admitted">Checked in {{ $registration->checked_in_at->format('g:i A') }}</span>
                @endif
                <h3><a href="{{ route('events.show', $registration->event) }}">{{ $registration->event->title }}</a></h3>
                <div class="muted">{{ $registration->event->dateRange() }}</div>
                <div class="muted">{{ $registration->event->venue }}@if ($registration->event->city), {{ $registration->event->city }}@endif</div>
                @if ($registration->event->materials_count > 0)
                    <div class="small target-row" style="margin-top:6px">
                        <a href="{{ route('events.show', $registration->event) }}">{{ $registration->event->materials_count }} {{ Str::plural('material', $registration->event->materials_count) }} to download</a>
                    </div>
                @endif
            </div>
            <div class="ticket-side">
                <div class="ticket-ref">Reference <strong class="mono">{{ $registration->reference_code }}</strong></div>
                <a class="btn btn-primary" data-download href="{{ route('registrations.pass', $registration) }}">Entry pass (PDF)</a>
                @if ($registration->canCancel())
                    <form method="POST" action="{{ route('registrations.destroy', $registration) }}"
                          data-confirm="Cancel your registration for this event?">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit">Cancel registration</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="empty" style="margin-bottom:24px">You have no upcoming events. <a href="{{ route('events.index') }}">Browse the catalog</a>.</div>
    @endforelse

    @if ($past->isNotEmpty())
        <h2 style="margin-top:28px">Past events</h2>
        @foreach ($past as $registration)
            <div class="ticket">
                <div class="ticket-main">
                    <span class="badge badge-type">{{ $registration->event->typeLabel() }}</span>
                    @if ($registration->checked_in_at)
                        <span class="badge badge-admitted">Attended</span>
                    @else
                        <span class="badge badge-ended">Did not attend</span>
                    @endif
                    <h3><a href="{{ route('events.show', $registration->event) }}">{{ $registration->event->title }}</a></h3>
                    <div class="muted">{{ $registration->event->dateRange() }}</div>
                    @if ($registration->event->materials_count > 0)
                        <div class="small target-row" style="margin-top:6px">
                            <a href="{{ route('events.show', $registration->event) }}">{{ $registration->event->materials_count }} {{ Str::plural('material', $registration->event->materials_count) }} to download</a>
                        </div>
                    @endif
                </div>
                <div class="ticket-side">
                    <div class="ticket-ref">Reference <strong class="mono">{{ $registration->reference_code }}</strong></div>
                    @if ($registration->canDownloadCertificate())
                        <a class="btn btn-dark" data-download href="{{ route('registrations.certificate', $registration) }}">Certificate (PDF)</a>
                    @else
                        <span class="muted small">Certificates are issued to attendees who checked in.</span>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    @if ($cancelled->isNotEmpty())
        <h2 style="margin-top:28px">Cancelled</h2>
        <div class="table-wrap">
            <table class="table">
                <tr><th>Event</th><th>Reference</th><th>Cancelled</th><th></th></tr>
                @foreach ($cancelled as $registration)
                    <tr>
                        <td>{{ $registration->event->title }}</td>
                        <td class="mono">{{ $registration->reference_code }}</td>
                        <td>{{ $registration->cancelled_at?->format('M j, Y') }}</td>
                        <td class="right"><a href="{{ route('events.show', $registration->event) }}">Register again</a></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endsection
