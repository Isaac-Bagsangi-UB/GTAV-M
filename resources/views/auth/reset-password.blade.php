@extends('layouts.app')

@section('title', 'Choose a new password')
@section('noindex', '1')

@section('content')
    <div class="card auth-card">
        <img class="auth-logo" src="{{ asset('images/logo-nav.webp') }}" width="300" height="136" alt="GTAV-M">
        <h1>Choose a new password</h1>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
                <div class="hint">At least 8 characters, with letters and numbers.</div>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" type="submit">Save new password</button>
        </form>
    </div>
@endsection
