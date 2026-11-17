@extends('layouts.app')

@section('title', 'My sessions')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">{{ auth()->user()->company }}</div>
            <h1>My sessions</h1>
        </div>
    </div>

    @if ($upcoming->isEmpty() && $past->isEmpty())
        <div class="empty">You have not been assigned to any event yet. A chapter manager will add you to a session.</div>
    @endif

    @foreach (['Upcoming' => $upcoming, 'Past' => $past] as $heading => $sessions)
        @continue($sessions->isEmpty())

        <h2>{{ $heading }}</h2>
        @foreach ($sessions as $session)
            <div class="card">
                <div class="page-head" style="margin-bottom:12px">
                    <div>
                        <span class="badge badge-type">{{ $session->typeLabel() }}</span>
                        <span class="badge">{{ $session->chapter->name }}</span>
                        <h3 style="margin-top:8px"><a href="{{ route('events.show', $session) }}">{{ $session->title }}</a></h3>
                        <div class="muted">{{ $session->dateRange() }} · {{ $session->venue }}@if ($session->city), {{ $session->city }}@endif</div>
                        @if ($session->pivot->topic)
                            <div style="margin-top:6px">Your topic: <strong>{{ $session->pivot->topic }}</strong></div>
                        @endif
                    </div>
                    <div class="right">
                        <div class="stat-value">{{ number_format($session->confirmed_registrations_count) }}</div>
                        <div class="muted small">registered of {{ number_format($session->capacity) }} seats</div>
                        @if ($session->ends_at->isPast())
                            <a class="btn btn-sm btn-dark" style="margin-top:8px" data-download href="{{ route('speaker.certificate', $session) }}">Certificate (PDF)</a>
                        @endif
                    </div>
                </div>

                <h3>Materials for attendees</h3>
                @if ($session->materials->isEmpty())
                    <p class="muted small">Nothing uploaded yet. Slides and handouts you add here unlock for registered attendees.</p>
                @else
                    <ul class="plain">
                        @foreach ($session->materials as $material)
                            <li>
                                <div>
                                    <a data-download href="{{ route('materials.download', $material) }}"><strong>{{ $material->title }}</strong></a>
                                    <div class="muted small">{{ $material->original_name }} · {{ $material->sizeLabel() }}</div>
                                </div>
                                <form class="inline" method="POST" action="{{ route('materials.destroy', $material) }}" data-confirm="Remove this file?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('materials.store', $session) }}" enctype="multipart/form-data" style="margin-top:14px">
                    @csrf
                    <div class="field-row">
                        <div class="field">
                            <label for="title-{{ $session->id }}">Title</label>
                            <input id="title-{{ $session->id }}" name="title" required maxlength="160">
                        </div>
                        <div class="field">
                            <label for="file-{{ $session->id }}">File (up to 20 MB)</label>
                            <input id="file-{{ $session->id }}" type="file" name="file" required>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-dark" type="submit">Upload material</button>
                </form>
            </div>
        @endforeach
    @endforeach

    @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
    @endif
@endsection
