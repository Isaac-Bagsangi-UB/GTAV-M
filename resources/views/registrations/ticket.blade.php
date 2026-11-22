@extends('layouts.app')
@section('content')
<a class="back-link" href="{{ route('registrations.index') }}">← Back to registrations</a>
<section class="panel ticket-card">
    <div class="eyebrow">ENTRY TICKET</div>
    <h1>{{ $registration->event->name }}</h1>
    <p class="lead">{{ $registration->event->starts_at->format('M d, Y g:i A') }} · {{ $registration->event->venue }}</p>
    <div class="ticket-code">
        <span class="eyebrow">TICKET CODE</span>
        <strong>{{ $registration->ticket_code }}</strong>
        <p class="muted">Show this code at the venue gate. Your registration has been verified.</p>
    </div>
    <dl>
        <div><dt>ATTENDEE</dt><dd>{{ $registration->user->name }}</dd></div>
        <div><dt>CHAPTER</dt><dd>{{ $registration->event->chapter->name }}</dd></div>
    </dl>
    <a class="button primary" href="{{ route('registrations.pass', $registration) }}">Download ticket</a>
</section>
@endsection
