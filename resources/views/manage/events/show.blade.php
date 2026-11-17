@extends('layouts.app')

@section('title', $event->title)
@section('noindex', '1')

@section('content')
    @php
        $subRevenue = $children->sum(fn ($c) => (float) $c->revenue);
        $subTickets = $children->sum('confirmed_registrations_count');
    @endphp

    <div class="page-head">
        <div>
            @include('partials.breadcrumbs', ['trail' => array_merge(
                ['Events' => route('manage.events.index')],
                $event->parent ? [$event->parent->title => route('manage.events.show', $event->parent)] : [],
                [$event->title => null]
            )])
            <h1>{{ $event->title }}</h1>
            <span class="badge badge-type">{{ $event->typeLabel() }}</span>
            <span class="badge badge-{{ $event->status }}">{{ ucfirst($event->status) }}</span>
            <span class="badge badge-{{ $event->registrationState() }}">{{ $event->stateLabel() }}</span>
            <div class="muted small" style="margin-top:8px">{{ $event->chapter->name }} · {{ $event->dateRange() }} · {{ $event->venue }}@if ($event->city), {{ $event->city }}@endif</div>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('events.show', $event) }}">Public page</a>
            <a class="btn" href="{{ route('gate.scanner', ['event' => $event->id]) }}">Open scanner</a>
            <a class="btn btn-dark" href="{{ route('manage.events.edit', $event) }}">Edit</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat accent">
            <div class="stat-label">Revenue</div>
            <div class="stat-value">${{ number_format((float) $event->revenue, 0) }}</div>
            @if ($children->isNotEmpty())
                <div class="stat-note">${{ number_format((float) $event->revenue + $subRevenue, 0) }} with sub-events</div>
            @endif
        </div>
        <div class="stat">
            <div class="stat-label">Tickets</div>
            <div class="stat-value">{{ number_format($event->confirmed_registrations_count) }}</div>
            <div class="stat-note">of {{ number_format($event->capacity) }} · {{ number_format($event->seatsLeft()) }} left</div>
        </div>
        <div class="stat teal">
            <div class="stat-label">Checked in</div>
            <div class="stat-value">{{ number_format($event->checked_in_count) }}</div>
            <div class="stat-note">
                {{ $event->confirmed_registrations_count > 0 ? round($event->checked_in_count / $event->confirmed_registrations_count * 100) : 0 }}% of ticket holders
            </div>
        </div>
        <div class="stat">
            <div class="stat-label">Registration cutoff</div>
            <div class="stat-value" style="font-size:1.25rem">{{ $event->registration_closes_at->format('M j, Y') }}</div>
            <div class="stat-note">{{ $event->registration_closes_at->format('g:i A') }}</div>
        </div>
    </div>

    @if (! $event->parent_id)
        <div class="page-head" style="margin-bottom:10px">
            <h2>Sub-events</h2>
            <a class="btn btn-sm" href="{{ route('manage.events.create', ['parent' => $event->id]) }}">Add sub-event</a>
        </div>
        @if ($children->isEmpty())
            <div class="empty" style="margin-bottom:20px">No workshops or sessions inside this event yet.</div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Session</th><th>Starts</th><th>Status</th><th class="num">Tickets</th><th class="num">Checked in</th><th class="num">Revenue</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($children as $child)
                            <tr>
                                <td><a href="{{ route('manage.events.show', $child) }}"><strong>{{ $child->title }}</strong></a></td>
                                <td class="nowrap">{{ $child->starts_at->format('M j, g:i A') }}</td>
                                <td><span class="badge badge-{{ $child->status }}">{{ ucfirst($child->status) }}</span></td>
                                <td class="num">{{ $child->confirmed_registrations_count }} / {{ $child->capacity }}</td>
                                <td class="num">{{ $child->checked_in_count }}</td>
                                <td class="num">${{ number_format((float) $child->revenue, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="3">Sub-event total</td><td class="num">{{ $subTickets }}</td><td></td><td class="num">${{ number_format($subRevenue, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        @endif
    @endif

    <div class="grid grid-2">
        <div class="card">
            <h2>Speakers</h2>
            @if ($event->speakers->isEmpty())
                <p class="muted">No speakers assigned.</p>
            @else
                <ul class="plain">
                    @foreach ($event->speakers as $speaker)
                        <li>
                            <div>
                                <strong>{{ $speaker->name }}</strong>
                                <div class="muted small">{{ $speaker->pivot->topic ?: $speaker->company }}</div>
                            </div>
                            <form class="inline" method="POST" action="{{ route('manage.events.speakers.destroy', [$event, $speaker]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($speakerOptions->isNotEmpty())
                <form method="POST" action="{{ route('manage.events.speakers.store', $event) }}" style="margin-top:16px">
                    @csrf
                    <div class="field">
                        <label for="user_id">Assign a speaker</label>
                        <select id="user_id" name="user_id" required>
                            @foreach ($speakerOptions as $option)
                                <option value="{{ $option->id }}">{{ $option->name }} ({{ $option->company }})</option>
                            @endforeach
                        </select>
                        @error('user_id') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    <div class="field">
                        <label for="topic">Topic (optional)</label>
                        <input id="topic" name="topic" maxlength="160">
                    </div>
                    <button class="btn btn-sm btn-dark" type="submit">Assign</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h2>Materials</h2>
            <p class="muted small">Only attendees with a confirmed registration for this event can download these.</p>
            @if ($materials->isNotEmpty())
                <ul class="plain">
                    @foreach ($materials as $material)
                        <li>
                            <div>
                                <a data-download href="{{ route('materials.download', $material) }}"><strong>{{ $material->title }}</strong></a>
                                <div class="muted small">{{ $material->original_name }} · {{ $material->sizeLabel() }} · {{ $material->uploader->name }}</div>
                            </div>
                            <form class="inline" method="POST" action="{{ route('materials.destroy', $material) }}" data-confirm="Remove this file?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('materials.store', $event) }}" enctype="multipart/form-data" style="margin-top:16px">
                @csrf
                <div class="field">
                    <label for="material_title">Title</label>
                    <input id="material_title" name="title" value="{{ old('title') }}" required maxlength="160">
                    @error('title') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="file">File</label>
                    <input id="file" type="file" name="file" required>
                    <div class="hint">PDF, Office files, images or ZIP, up to 20 MB.</div>
                    @error('file') <div class="error">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-sm btn-dark" type="submit">Upload</button>
            </form>
        </div>
    </div>

    <div class="page-head" style="margin-bottom:10px">
        <h2>{{ request()->boolean('attendance') ? 'Attendance record' : 'Registrations' }}</h2>
        <div class="actions">
            @if (request()->boolean('attendance'))
                <a class="btn btn-sm" href="{{ route('manage.events.show', $event) }}">All registrations</a>
            @else
                <a class="btn btn-sm" href="{{ route('manage.events.show', [$event, 'attendance' => 1]) }}">Checked in only</a>
            @endif
            <a class="btn btn-sm" data-download href="{{ route('manage.events.export', $event) }}">Export CSV</a>
        </div>
    </div>
    @if ($registrations->isEmpty())
        <div class="empty">{{ request()->boolean('attendance') ? 'Nobody has checked in yet.' : 'Nobody has registered yet.' }}</div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Reference</th><th>Attendee</th><th>Company</th><th>Status</th><th>Registered</th><th>Checked in</th><th class="num">Paid</th></tr>
                </thead>
                <tbody>
                    @foreach ($registrations as $registration)
                        <tr>
                            <td class="mono">{{ $registration->reference_code }}</td>
                            <td>
                                {{ $registration->user->name }}
                                <div class="muted small">{{ $registration->user->email }}</div>
                            </td>
                            <td>{{ $registration->user->company }}</td>
                            <td><span class="badge badge-{{ $registration->status }}">{{ ucfirst($registration->status) }}</span></td>
                            <td class="nowrap">{{ $registration->created_at->format('M j, Y') }}</td>
                            <td class="nowrap">{{ $registration->checked_in_at?->format('M j, g:i A') ?? '-' }}</td>
                            <td class="num">${{ number_format((float) $registration->amount_paid, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $registrations->links() }}
    @endif
@endsection
