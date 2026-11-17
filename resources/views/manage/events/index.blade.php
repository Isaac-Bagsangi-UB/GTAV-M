@extends('layouts.app')

@section('title', 'Manage events')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ auth()->user()->isAdmin() ? 'All chapters' : auth()->user()->chapter?->name }}</div>
            <h1>Manage events</h1>
        </div>
        <a class="btn btn-primary" href="{{ route('manage.events.create') }}">New event</a>
    </div>

    <form class="filters" method="GET" action="{{ route('manage.events.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by title" aria-label="Search events">
        <select name="status" aria-label="Status">
            <option value="">Any status</option>
            @foreach (\App\Models\Event::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark" type="submit">Filter</button>
    </form>

    @if ($events->isEmpty())
        <div class="empty">No events found.</div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Starts</th>
                        <th>Status</th>
                        <th>Registration</th>
                        <th class="num">Tickets</th>
                        <th class="num">Revenue</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>
                                <a href="{{ route('manage.events.show', $event) }}"><strong>{{ $event->title }}</strong></a>
                                <div class="muted small">
                                    {{ $event->chapter->code }} · {{ $event->typeLabel() }}
                                    @if ($event->parent) · inside {{ $event->parent->title }} @endif
                                </div>
                            </td>
                            <td class="nowrap">{{ $event->starts_at->format('M j, Y') }}</td>
                            <td><span class="badge badge-{{ $event->status }}">{{ ucfirst($event->status) }}</span></td>
                            <td><span class="badge badge-{{ $event->registrationState() }}">{{ $event->stateLabel() }}</span></td>
                            <td class="num">{{ number_format($event->confirmed_registrations_count) }} / {{ number_format($event->capacity) }}</td>
                            <td class="num">${{ number_format((float) $event->revenue, 2) }}</td>
                            <td class="right nowrap"><a href="{{ route('manage.events.edit', $event) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $events->links() }}
    @endif
@endsection
