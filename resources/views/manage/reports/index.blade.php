@extends('layouts.app')

@section('title', 'Reports')
@section('noindex', '1')

@section('content')
    @php
        $isAdmin = auth()->user()->isAdmin();
    @endphp

    <div class="page-head">
        <div>
            <div class="eyebrow">{{ $isAdmin ? 'All chapters' : auth()->user()->chapter?->name }}</div>
            <h1>Reports</h1>
        </div>
    </div>
    <p class="muted" style="max-width:68ch;margin-top:-16px;margin-bottom:36px">
        Each report is generated as a PDF from live data, stamped with the date, time and the person who generated it, and recorded in the audit log.
        @unless ($isAdmin) Reports cover your chapter only. @endunless
    </p>

    <div class="grid grid-3">
        {{-- Report 1 --}}
        <section class="card report-card card-lift" aria-labelledby="r1">
            <span class="icon-badge">@include('partials.icon', ['name' => 'chart', 'class' => 'icon-lg'])</span>
            <h2 id="r1">Chapter Performance</h2>
            <p>How each chapter is doing: tickets sold against capacity, revenue and turnout, with every event and its sub-events.</p>
            <ul class="includes">
                <li>Totals and fill rate per chapter</li>
                <li>Event and sub-event breakdown</li>
                <li>Attendance rate from gate scans</li>
            </ul>
            <form method="GET" action="{{ route('manage.reports.chapter-performance') }}" data-download>
                @if ($isAdmin)
                    <div class="field">
                        <label for="r1-chapter">Chapter</label>
                        <select id="r1-chapter" name="chapter">
                            <option value="">All chapters</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}">{{ $chapter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="btn btn-primary" type="submit">Generate PDF</button>
            </form>
        </section>

        {{-- Report 2 --}}
        <section class="card report-card card-lift" aria-labelledby="r2">
            <span class="icon-badge">@include('partials.icon', ['name' => 'users', 'class' => 'icon-lg'])</span>
            <h2 id="r2">Event Attendance</h2>
            <p>The attendance record for one event: every ticket holder, whether they were scanned in at the gate, and when.</p>
            <ul class="includes">
                <li>Checked in, not yet arrived, cancelled</li>
                <li>Attendee list with check-in times</li>
                <li>Attendance rate and ticket revenue</li>
            </ul>
            <form method="GET" action="{{ route('manage.reports.event-attendance') }}" data-download>
                <div class="field">
                    <label for="r2-event">Event</label>
                    <select id="r2-event" name="event_id" required>
                        @forelse ($eventGroups as $groupLabel => $groupEvents)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach ($groupEvents as $event)
                                    <option value="{{ $event->id }}">{{ $event->starts_at->format('M j, Y') }} · {{ \Illuminate\Support\Str::limit($event->title, 44) }}@if ($isAdmin) ({{ $event->chapter->code }})@endif</option>
                                @endforeach
                            </optgroup>
                        @empty
                            <option value="" disabled selected>No events yet</option>
                        @endforelse
                    </select>
                    @error('event_id') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-primary" type="submit" @disabled(empty($eventGroups))>Generate PDF</button>
            </form>
        </section>

        {{-- Report 3 --}}
        <section class="card report-card card-lift" aria-labelledby="r3">
            <span class="icon-badge">@include('partials.icon', ['name' => 'coins', 'class' => 'icon-lg'])</span>
            <h2 id="r3">Registration and Revenue</h2>
            <p>Ticket sales over a period you choose: new registrations, cancellations and income, grouped by chapter and event.</p>
            <ul class="includes">
                <li>Registrations made in the period</li>
                <li>Cancellations in the period</li>
                <li>Revenue per event and in total</li>
            </ul>
            <form method="GET" action="{{ route('manage.reports.registration-revenue') }}" data-download>
                <div class="field-row">
                    <div class="field">
                        <label for="r3-from">From</label>
                        <input id="r3-from" type="date" name="from" value="{{ old('from', $defaultFrom) }}" required>
                        @error('from') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="r3-to">To</label>
                        <input id="r3-to" type="date" name="to" value="{{ old('to', $defaultTo) }}" required>
                        @error('to') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>
                @if ($isAdmin)
                    <div class="field">
                        <label for="r3-chapter">Chapter</label>
                        <select id="r3-chapter" name="chapter">
                            <option value="">All chapters</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}">{{ $chapter->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button class="btn btn-primary" type="submit">Generate PDF</button>
            </form>
        </section>
    </div>
@endsection
