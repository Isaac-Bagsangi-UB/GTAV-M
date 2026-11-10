@extends('layouts.app')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INBOX</div><h1>Notifications.</h1><p class="muted">Payment and registration updates for your events.</p></div></div>
<div class="panel notification-panel">@forelse($notifications as $notification)<div class="notification-row"><strong>{{ $notification->data['title'] ?? 'Update' }}</strong><p>{{ $notification->data['message'] ?? '' }}</p><small>{{ $notification->created_at->format('M d, Y g:i A') }}</small></div>@empty<p class="muted">You have no notifications yet.</p>@endforelse</div>
@endsection
