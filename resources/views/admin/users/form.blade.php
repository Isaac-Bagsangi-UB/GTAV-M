@extends('layouts.app')

@section('title', $user->exists ? 'Edit account' : 'New account')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            @include('partials.breadcrumbs', ['trail' => ['Users' => route('admin.users.index'), ($user->exists ? $user->name : 'New account') => null]])
            <h1>{{ $user->exists ? 'Edit ' . $user->name : 'New account' }}</h1>
        </div>
    </div>

    <form class="card" style="max-width:720px" method="POST"
          action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="field-row">
            <div class="field">
                <label for="name">Full name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="120">
                @error('name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    @foreach (\App\Models\User::ROLES as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('role') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="chapter_id">Chapter</label>
                <select id="chapter_id" name="chapter_id">
                    <option value="">No chapter</option>
                    @foreach ($chapters as $chapter)
                        <option value="{{ $chapter->id }}" @selected((int) old('chapter_id', $user->chapter_id) === $chapter->id)>{{ $chapter->name }}</option>
                    @endforeach
                </select>
                <div class="hint">Required for chapter managers and gate staff. It limits what they can see and scan.</div>
                @error('chapter_id') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="company">Company or organization</label>
                <input id="company" name="company" value="{{ old('company', $user->company) }}" maxlength="160">
                @error('company') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="job_title">Job title</label>
                <input id="job_title" name="job_title" value="{{ old('job_title', $user->job_title) }}" maxlength="120">
                @error('job_title') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="phone">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="40">
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">{{ $user->exists ? 'New password' : 'Password' }}</label>
                <input id="password" type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
                <div class="hint">{{ $user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters, with letters and numbers.' }}</div>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $user->exists ? 'Save changes' : 'Create account' }}</button>
            <a href="{{ route('admin.users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
