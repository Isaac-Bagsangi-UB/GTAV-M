@extends('layouts.app')

@section('title', 'Gate scanner')
@section('noindex', '1')

@section('content')
    <div class="scanner" id="scanner"
        @if ($event) data-event-id="{{ $event->id }}"
             data-scan-url="{{ route('gate.scan') }}"
             data-search-url="{{ route('gate.search') }}" @endif>
        <div class="eyebrow">Venue gate</div>
        <h1>Gate check-in</h1>

        @if (!$event)
            <p class="muted">No events to scan.</p>
            <div class="empty">There are no published events on now or coming up for your chapter.</div>
        @else
            <form method="GET" action="{{ route('gate.scanner') }}" class="field">
                <label for="event">Scanning for</label>
                <select id="event" name="event" data-autosubmit>
                    @foreach ($events as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $event->id)>
                            {{ $option->starts_at->format('M j') }} · {{ $option->title }}
                        </option>
                    @endforeach
                </select>
            </form>

            <div class="headcount" aria-live="polite">
                <div><strong id="count-in">{{ $attendance['checked_in'] }}</strong><span>Entered</span></div>
                <div><strong id="count-left">{{ $attendance['remaining'] }}</strong><span>Still to arrive</span></div>
                <div><strong>{{ $attendance['capacity'] }}</strong><span>Capacity</span></div>
            </div>

            <div id="reader"></div>
            <p id="camera-note" class="muted small" style="margin-top:6px">Starting camera…</p>

            <div id="result" class="scan-result" role="status" aria-live="assertive">
                <div class="verdict">READY TO SCAN</div>
                <div class="muted">Hold the QR code on the guest's pass inside the square.</div>
            </div>

            <form id="manual-form" class="manual" autocomplete="off" data-no-loading>
                <input id="manual-code" name="code" placeholder="Name or ticket code"
                    aria-label="Attendee name or ticket code" maxlength="64">
                <button class="btn btn-dark" type="submit">Find</button>
            </form>
            <div id="matches" aria-live="polite"></div>

            <div class="activity-head">
                <h2 class="h3">Gate activity</h2>
            </div>
            <ul class="activity" id="recent">
                @forelse ($activity as $row)
                    @include('gate.partials.activity-row', ['row' => $row])
                @empty
                    <li class="activity-empty" id="recent-empty">No scans yet for this event.</li>
                @endforelse
            </ul>
        @endif
    </div>
@endsection

@if ($event)
    @push('scripts')
        <script src="{{ asset('js/vendor/html5-qrcode.min.js') }}" defer></script>
        <script src="{{ asset('js/scanner.js') }}?v=5" defer></script>
    @endpush
@endif
