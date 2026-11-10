@extends('layouts.app')
@section('content')
<section class="auth-card"><div class="eyebrow">EVENT OPERATIONS PLATFORM</div><h1>Welcome back.</h1><p class="muted">One place for every chapter, attendee, and credential.</p><form method="POST" action="{{ route('login') }}" class="form-stack">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autofocus></label><label>Password<input type="password" name="password" required></label><label class="check"><input type="checkbox" name="remember"> Remember me</label><button class="button primary">Sign in</button></form><p class="form-foot">New to GTAV-M? <a href="{{ route('register') }}">Create an account</a></p></section>
@endsection
