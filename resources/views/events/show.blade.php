@extends('layouts.app')

@section('title', $event->title)
@section('description', Str::limit($event->typeLabel() . ' in ' . ($event->city ?: $event->venue) . ', ' . $event->starts_at->format('F j, Y') . '. ' . ($event->description ?? ''), 155))

@if ($event->status === 'published')
    @push('head')
        <script type="application/ld+json">{!! $structuredData !!}</script>
    @endpush
@else
    @section('noindex', '1')
@endif

@section('content')
    @php
        $state = $event->registrationState();
        $user = auth()->user();
        $isRegistered = $registration && $registration->isConfirmed();
    @endphp

    <div class="page-head">
        <div>
            @include('partials.breadcrumbs', ['trail' => array_merge(
                ['Events' => route('events.index')],
                $event->parent ? [$event->parent->title => route('events.show', $event->parent)] : [],
                [$event->title => null]
            )])
            <h1>{{ $event->title }}</h1>
            <p class="muted small" style="margin-bottom:12px">{{ $event->chapter->name }}</p>
            <span class="badge badge-type">{{ $event->typeLabel() }}</span>
            <span class="badge badge-{{ $state }}">{{ $event->stateLabel() }}</span>
            @if ($event->status !== 'published')
                <span class="badge badge-{{ $event->status }}">{{ ucfirst($event->status) }} (not public)</span>
            @endif
        </div>
        @can('manage-event', $event)
            <a class="btn" href="{{ route('manage.events.show', $event) }}">Manage this event</a>
        @endcan
    </div>

    <figure class="event-hero">
        @include('partials.event-cover', ['event' => $event, 'variant' => 'hero', 'eager' => true])
        @if ($event->cover_path && $event->cover_credit)
            <figcaption class="muted small">{{ $event->cover_credit }}</figcaption>
        @endif
    </figure>

    <div class="split">
        <div>
            <div class="card">
                <h2>About this event</h2>
                <p>{!! nl2br(e($event->description ?: 'Details will be announced soon.')) !!}</p>

                <dl class="facts">
                    <dt>When</dt><dd>{{ $event->dateRange() }}</dd>
                    <dt>Where</dt><dd>{{ $event->venue }}@if ($event->city), {{ $event->city }}@endif</dd>
                    <dt>Registration window</dt>
                    <dd>{{ $event->registration_opens_at->format('M j, Y') }} to {{ $event->registration_closes_at->format('M j, Y g:i A') }}</dd>
                </dl>
            </div>

            @if ($event->speakers->isNotEmpty())
                <div class="card">
                    <h2>Speakers</h2>
                    <ul class="plain">
                        @foreach ($event->speakers as $speaker)
                            <li>
                                <div>
                                    <strong>{{ $speaker->name }}</strong>
                                    <div class="muted small">{{ $speaker->company }}</div>
                                </div>
                                <span class="muted small">{{ $speaker->pivot->topic }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($event->children->isNotEmpty())
                <h2>Sessions inside this event</h2>
                <p class="muted">Workshops have their own seat limits and need a separate registration.</p>
                <div class="grid grid-2" style="margin-bottom:20px">
                    @foreach ($event->children as $child)
                        @include('partials.event-card', ['event' => $child->setRelation('chapter', $event->chapter)])
                    @endforeach
                </div>
            @endif

            <div class="card">
                <h2>Materials</h2>
                @if ($canAccessMaterials)
                    @if ($materials->isEmpty())
                        <p class="muted">Nothing has been uploaded yet.</p>
                    @else
                        <ul class="plain">
                            @foreach ($materials as $material)
                                <li>
                                    <div>
                                        <strong>{{ $material->title }}</strong>
                                        <div class="muted small">{{ $material->original_name }} · {{ $material->sizeLabel() }}</div>
                                    </div>
                                    <a class="btn btn-sm" data-download href="{{ route('materials.download', $material) }}">Download</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="muted">
                        {{ $materialCount }} {{ Str::plural('file', $materialCount) }} available.
                        Materials unlock once your registration for this event is confirmed.
                    </p>
                @endif
            </div>
        </div>

        <aside>
            <div class="card">
                <div class="price" style="font-size:1.8rem">{{ $event->priceLabel() }}</div>
                <p class="muted small">per attendee</p>

                <div class="meter {{ $event->fillPercent() >= 85 ? 'hot' : '' }}"><span style="width: {{ $event->fillPercent() }}%"></span></div>
                <p class="small" style="margin-top:6px">
                    <strong>{{ number_format($event->seatsLeft()) }}</strong> of {{ number_format($event->capacity) }} seats left
                </p>

                @if ($isRegistered)
                    <div class="flash flash-success" style="margin-bottom:12px">
                        You are registered. Reference <strong class="mono">{{ $registration->reference_code }}</strong>
                    </div>
                    <a class="btn btn-primary" data-download href="{{ route('registrations.pass', $registration) }}">Download entry pass (PDF)</a>
                    <p class="small" style="margin-top:10px"><a href="{{ route('registrations.index') }}">Go to my passes</a></p>
                @elseif (! $user)
                    @if ($state === 'open')
                        <a class="btn btn-primary" href="{{ route('login') }}">Log in to register</a>
                        <p class="small" style="margin-top:10px">No account yet? <a href="{{ route('register') }}">Create one</a></p>
                    @else
                        <button class="btn" disabled>{{ $event->stateLabel() }}</button>
                    @endif
                @elseif ($user->hasRole('attendee'))
                    @if ($state === 'open')
                        <form method="POST" action="{{ route('registrations.store', $event) }}">
                            @csrf
                            <button class="btn btn-primary" type="submit">Register for {{ $event->priceLabel() }}</button>
                        </form>
                        <p class="muted small" style="margin-top:10px">Registration closes {{ $event->registration_closes_at->format('M j, g:i A') }}.</p>
                    @else
                        <button class="btn" disabled>{{ $event->stateLabel() }}</button>
                        @if ($state === 'not_open')
                            <p class="muted small" style="margin-top:10px">Opens {{ $event->registration_opens_at->format('M j, Y g:i A') }}.</p>
                        @elseif ($state === 'full')
                            <p class="muted small" style="margin-top:10px">This event reached its maximum headcount.</p>
                        @endif
                    @endif
                @else
                    <p class="muted small">Registration is for attendee accounts. You are signed in as {{ $user->roleLabel() }}.</p>
                @endif
            </div>
        </aside>
    </div>
@endsection
