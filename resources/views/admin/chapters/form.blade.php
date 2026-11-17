@extends('layouts.app')

@section('title', $chapter->exists ? 'Edit chapter' : 'New chapter')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            @include('partials.breadcrumbs', ['trail' => ['Chapters' => route('admin.chapters.index'), ($chapter->exists ? $chapter->name : 'New chapter') => null]])
            <h1>{{ $chapter->exists ? 'Edit ' . $chapter->name : 'New chapter' }}</h1>
        </div>
    </div>

    <form class="card" style="max-width:620px" method="POST"
          action="{{ $chapter->exists ? route('admin.chapters.update', $chapter) : route('admin.chapters.store') }}">
        @csrf
        @if ($chapter->exists)
            @method('PUT')
        @endif

        <div class="field">
            <label for="name">Chapter name</label>
            <input id="name" name="name" value="{{ old('name', $chapter->name) }}" required maxlength="120">
            @error('name') <div class="error">{{ $message }}</div> @enderror
        </div>
        <div class="field-row">
            <div class="field">
                <label for="code">Short code</label>
                <input id="code" name="code" value="{{ old('code', $chapter->code) }}" required maxlength="8" style="text-transform:uppercase">
                <div class="hint">Up to 8 letters or digits, for example APAC.</div>
                @error('code') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="region">Region</label>
                <input id="region" name="region" value="{{ old('region', $chapter->region) }}" required maxlength="120">
                @error('region') <div class="error">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $chapter->exists ? 'Save changes' : 'Create chapter' }}</button>
            <a href="{{ route('admin.chapters.index') }}">Cancel</a>
        </div>
    </form>

    @if ($chapter->exists)
        <form method="POST" action="{{ route('admin.chapters.destroy', $chapter) }}" data-confirm="Delete this chapter?">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-danger" type="submit">Delete chapter</button>
        </form>
    @endif
@endsection
