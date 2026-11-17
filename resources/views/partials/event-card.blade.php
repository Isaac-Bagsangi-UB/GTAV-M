{{-- Expects: $event (with chapter and confirmed_registrations_count loaded) --}}
<a class="event-card" href="{{ route('events.show', $event) }}">
    <div class="event-card-top">
        <span class="badge badge-type">{{ $event->typeLabel() }}</span>
        <span class="badge badge-{{ $event->registrationState() }}">{{ $event->stateLabel() }}</span>
        <h3>{{ $event->title }}</h3>
        <div class="meta muted small">@include('partials.icon', ['name' => 'calendar'])<span>{{ $event->dateRange() }}</span></div>
        <div class="meta muted small">@include('partials.icon', ['name' => 'pin'])<span>{{ $event->venue }}@if ($event->city), {{ $event->city }}@endif</span></div>
        <div class="meta muted small">@include('partials.icon', ['name' => 'globe'])<span>{{ $event->chapter->name }}</span></div>
    </div>
    <div class="event-card-stub">
        <span class="price">{{ $event->priceLabel() }}</span>
        <span class="muted">{{ number_format($event->seatsLeft()) }} of {{ number_format($event->capacity) }} seats left</span>
    </div>
</a>
