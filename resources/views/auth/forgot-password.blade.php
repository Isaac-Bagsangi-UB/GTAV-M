@extends('layouts.app')

@section('title', 'Forgot password')
@section('noindex', '1')

@section('content')
    <div class="card auth-card">
        <img class="auth-logo" src="{{ asset('images/logo-nav.webp') }}" width="300" height="136" alt="GTAV-M">
        <h1>Forgot your password?</h1>
        <p class="muted">Enter the email address of your account. We will send a link to choose a new password. The link works once and expires after 60 minutes.</p>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-primary" type="submit">Send reset link</button>
        </form>

        <p class="small" style="margin-top:20px"><a href="{{ route('login') }}">&larr; Back to log in</a></p>
    </div>
@endsection
