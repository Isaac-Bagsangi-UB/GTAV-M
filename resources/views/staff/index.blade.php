@extends('layouts.app')
@section('content')
<div class="page-heading"><div><div class="eyebrow">VENUE OPERATIONS</div><h1>Gate staff.</h1><p class="muted">Create staff accounts, then assign them to the events they will work.</p></div></div>
<div class="detail-layout">
    <section class="panel">
        <div class="eyebrow">NEW ACCOUNT</div>
        <h2>Create gate staff</h2>
        <form method="POST" action="{{ route('staff.store') }}" class="form-stack">
            @csrf
            <label>Name<input name="name" value="{{ old('name') }}" required></label>
            <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
            <label>Password<input type="password" name="password" required></label>
            <label>Confirm password<input type="password" name="password_confirmation" required></label>
            <button class="button primary">Create staff account</button>
        </form>
    </section>
    <section class="panel">
        <div class="eyebrow">CURRENT STAFF</div>
        @forelse($staff as $member)
            <div class="pass-row">
                <div><strong>{{ $member->name }}</strong><small>{{ $member->email }} · {{ $member->assignedEvents->count() }} assigned event(s)</small></div>
            </div>
        @empty
            <p class="muted">No gate staff accounts yet.</p>
        @endforelse
    </section>
</div>
@endsection
