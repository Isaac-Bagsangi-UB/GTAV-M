@extends('layouts.app')
@section('content')
<a class="back-link" href="{{ route('events.index') }}">← Back to events</a>
<div class="detail-layout">
    <article class="panel event-detail">
        <div class="eyebrow">{{ $event->type }} · {{ $event->chapter->name }}</div>
        <h1>{{ $event->name }}</h1>
        <p class="lead">{{ $event->description ?: 'A focused gathering for global trade professionals.' }}</p>
        <dl>
            <div><dt>WHEN</dt><dd>{{ $event->starts_at->format('M d, Y') }}<br>{{ $event->starts_at->format('g:i A') }} - {{ $event->ends_at->format('g:i A') }}</dd></div>
            <div><dt>WHERE</dt><dd>{{ $event->venue }}<br>{{ $event->chapter->location }}</dd></div>
            <div><dt>CAPACITY</dt><dd>{{ $event->seatsRemaining() }} seats remaining<br>of {{ $event->capacity }}</dd></div>
            <div><dt>PRICE</dt><dd>₱{{ number_format($event->ticket_price, 2) }}</dd></div>
        </dl>
    </article>
    @if(auth()->user()->role === 'attendee')
        <aside class="panel payment-panel">
            @if($registration)
                <div class="eyebrow">REGISTRATION STATUS</div>
                <h2>{{ $registration->payment_status === 'paid' ? 'Registered' : ($registration->payment_status === 'rejected' ? 'Rejected' : 'Awaiting verification') }}</h2>
                <p class="muted">
                    @if($registration->payment_status === 'paid')
                        Your payment is verified and your ticket is ready.
                    @elseif($registration->payment_status === 'rejected')
                        Your registration was not approved. {{ $registration->rejection_reason ?: '' }}
                    @else
                        Your payment is waiting for the event team to verify.
                    @endif
                </p>
            @else
                <div class="eyebrow">SECURE YOUR SEAT</div>
                <h2>Register for this event</h2>
                <p class="muted">Choose how you will pay. The event team will verify your payment before your entry pass is released.</p>
                <form method="POST" action="{{ route('events.register', $event) }}" class="form-stack">
                    @csrf
                    <label>Payment method
                        <select name="payment_method" required>
                            <option value="">Choose one</option>
                            <option value="cash">Cash at the event office</option>
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </label>
                    <button class="button primary full">Register and submit payment method</button>
                </form>
            @endif
        </aside>
    @elseif($canManageEvent)
        <aside class="panel payment-panel operations-panel">
            <div class="eyebrow">EVENT OPERATIONS</div>
            <h2>Manage this event</h2>
            <div class="operation-buttons">
                <button class="operation-button button" type="button" data-modal-open="registrations-modal">
                    <span><strong>Registrations</strong><small>Review pending attendees</small></span>
                    <b>{{ $pendingRegistrations->count() }}</b>
                </button>
                <button class="operation-button button" type="button" data-modal-open="staff-modal">
                    <span><strong>Assigned staff</strong><small>Choose gate staff</small></span>
                    <b>{{ $assignedStaffIds->count() }}</b>
                </button>
            </div>
        </aside>
        <dialog id="registrations-modal" class="modal">
            <div class="modal-header"><div><div class="eyebrow">REGISTRATIONS</div><h2>{{ $pendingRegistrations->count() }} pending</h2></div><button class="modal-close" type="button" data-modal-close>×</button></div>
            @if($pendingRegistrations->isNotEmpty())
                <form id="bulk-reject-form" method="POST" action="{{ route('events.registrations.reject', $event) }}" class="form-stack">
                    @csrf
                    <div class="pending-toolbar"><label class="check"><input type="checkbox" data-select-all="pending-registration"> Select all</label><span class="muted tiny">Choose attendees to reject</span></div>
                    <div class="pending-list">
                        @foreach($pendingRegistrations as $pendingRegistration)
                            <div class="pending-item">
                                <label class="check"><input type="checkbox" form="bulk-reject-form" name="registration_ids[]" value="{{ $pendingRegistration->id }}" data-select-item="pending-registration"><span><strong>{{ $pendingRegistration->user->name }}</strong><small>{{ ucfirst($pendingRegistration->payment_method ?: 'payment method not provided') }}</small></span></label>
                                <button class="button small" type="submit" formaction="{{ route('registrations.verify-payment', $pendingRegistration) }}" formmethod="POST">Mark paid</button>
                            </div>
                        @endforeach
                    </div>
                    <label>Rejection reason <span class="optional">optional</span><input name="rejection_reason" placeholder="Explain why the registration was rejected"></label>
                    <button class="button small" type="submit">Reject selected</button>
                </form>
            @else
                <p class="muted">No registrations are waiting for verification.</p>
            @endif
        </dialog>
        <dialog id="staff-modal" class="modal">
            <div class="modal-header"><div><div class="eyebrow">ASSIGNED STAFF</div><h2>Gate staff</h2></div><button class="modal-close" type="button" data-modal-close>×</button></div>
            @if($staff->isNotEmpty())
                <form method="POST" action="{{ route('events.staff.update', $event) }}" class="form-stack">
                    @csrf
                    @method('PUT')
                    <div class="pending-list">
                        @foreach($staff as $member)
                            <label class="check pending-item"><span><strong>{{ $member->name }}</strong><small>{{ $member->email }}</small></span><input type="checkbox" name="staff_ids[]" value="{{ $member->id }}" @checked($assignedStaffIds->contains($member->id))></label>
                        @endforeach
                    </div>
                    <button class="button primary" type="submit">Save staff assignments</button>
                </form>
            @else
                <p class="muted">No gate staff accounts exist yet. Create one from the Staff page.</p>
            @endif
        </dialog>
    @endif
</div>
@endsection
