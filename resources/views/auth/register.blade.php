@extends('layouts.app')

@section('title', 'Create account')
@section('noindex', '1')

@section('content')
    <div class="card auth-card wide">
        <img class="auth-logo" src="{{ asset('images/logo-nav.webp') }}" width="300" height="136" alt="GTAV-M">
        <h1>Create an account</h1>
        <p class="muted">One account covers every event you attend, across all chapters.</p>

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="field">
                <label for="role">I am joining as</label>
                <select id="role" name="role" required>
                    <option value="attendee" @selected(old('role', 'attendee') === 'attendee')>Corporate Attendee</option>
                    <option value="speaker" @selected(old('role') === 'speaker')>Guest Speaker</option>
                </select>
                <div class="hint">Speakers are assigned to their sessions by a chapter manager after signing up.</div>
                @error('role') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="name">Full name</label>
                    <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
                    <div class="hint">Printed on your pass and certificate.</div>
                    @error('name') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="email">Work email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="company">Company</label>
                    <input id="company" name="company" value="{{ old('company') }}" required autocomplete="organization">
                    @error('company') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="job_title">Job title (optional)</label>
                    <input id="job_title" name="job_title" value="{{ old('job_title') }}" autocomplete="organization-title">
                    @error('job_title') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field">
                <label for="phone">Phone (optional)</label>
                <input id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password">
                    <div class="hint">At least 8 characters, with letters and numbers.</div>
                    @error('password') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                </div>
            </div>
            <p class="muted small">By creating an account you agree to the <a href="{{ route('terms') }}">Terms &amp; Conditions</a> and the <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>
            <button class="btn btn-primary" type="submit">Create account</button>
        </form>

        <p class="small" style="margin-top:16px">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </div>
@endsection
