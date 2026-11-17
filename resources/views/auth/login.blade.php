@extends('layouts.app')

@section('title', 'Log in')
@section('noindex', '1')

@section('content')
    <div class="card auth-card">
        <img class="auth-logo" src="{{ asset('images/logo-nav.webp') }}" width="300" height="136" alt="GTAV-M">
        <h1>Log in</h1>
        <p class="muted">Attendees, speakers, gate staff and managers all sign in here.</p>

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="form-split">
                <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                <a class="small" href="{{ route('password.request') }}">Forgot your password?</a>
            </div>
            <button class="btn btn-primary" type="submit">Log in</button>
        </form>

        <p class="small" style="margin-top:16px">New attendee or speaker? <a href="{{ route('register') }}">Create an account</a></p>
    </div>

    @if (app()->environment('local'))
        <div class="card auth-card">
            <h2>Demo accounts</h2>
            <p class="muted small">Shown only in the local environment. Password for all: <span class="mono">password</span></p>
            <table class="demo-logins">
                <tr><td>Administrator</td><td class="mono">admin@gtavm.test</td></tr>
                <tr><td>Chapter manager</td><td class="mono">manager.apac@gtavm.test</td></tr>
                <tr><td>Attendee</td><td class="mono">attendee@gtavm.test</td></tr>
                <tr><td>Speaker</td><td class="mono">speaker@gtavm.test</td></tr>
                <tr><td>Gate staff</td><td class="mono">gate.apac@gtavm.test</td></tr>
            </table>
        </div>
    @endif
@endsection
