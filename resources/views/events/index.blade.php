@extends('layouts.app')

@section('title', 'Browse events')
@section('description', 'Upcoming regional expos, technical summits and training workshops of the Global Trade Association. See dates, venues, prices and seats left.')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Event catalog</div>
            <h1>Expos, summits and workshops</h1>
        </div>
    </div>

    <form class="filters" method="GET" action="{{ route('events.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search title, city or venue" aria-label="Search events">
        <select name="chapter" aria-label="Chapter">
            <option value="">All chapters</option>
            @foreach ($chapters as $chapter)
                <option value="{{ $chapter->id }}" @selected(request('chapter') == $chapter->id)>{{ $chapter->name }}</option>
            @endforeach
        </select>
        <select name="type" aria-label="Event type">
            <option value="">All types</option>
            @foreach (\App\Models\Event::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark" type="submit">Filter</button>
        @if (request()->hasAny(['q', 'chapter', 'type']))
            <a href="{{ route('events.index') }}">Clear</a>
        @endif
    </form>

    @if ($events->isEmpty())
        <div class="empty">No upcoming events match those filters.</div>
    @else
        <h2 class="sr-only">Upcoming events</h2>
        <div class="grid grid-3">
            @foreach ($events as $event)
                @include('partials.event-card', ['event' => $event])
            @endforeach
        </div>
        {{ $events->links() }}
    @endif
@endsection
