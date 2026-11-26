@extends('layouts.app')

@section('title', $event->exists ? 'Edit event' : 'New event')
@section('noindex', '1')

@section('content')
    @php
        $dt = fn ($field) => old($field, $event->{$field}?->format('Y-m-d\TH:i'));
    @endphp

    <div class="page-head">
        <div>
            @include('partials.breadcrumbs', ['trail' => $event->exists
                ? ['Events' => route('manage.events.index'), $event->title => route('manage.events.show', $event), 'Edit' => null]
                : ['Events' => route('manage.events.index'), 'New event' => null]])
            <h1>{{ $event->exists ? 'Edit ' . $event->title : 'New event' }}</h1>
        </div>
    </div>

    <form class="card" method="POST" action="{{ $event->exists ? route('manage.events.update', $event) : route('manage.events.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($event->exists)
            @method('PUT')
        @endif

        <fieldset>
            <legend>Basics</legend>
            <div class="field">
                <label for="title">Title</label>
                <input id="title" name="title" value="{{ old('title', $event->title) }}" required maxlength="160">
                @error('title') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="type">Type</label>
                    <select id="type" name="type" required>
                        @foreach (\App\Models\Event::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $event->type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type') <div class="error">{{ $message }}</div> @enderror
                </div>
                @if ($chapters->isNotEmpty())
                    <div class="field">
                        <label for="chapter_id">Chapter</label>
                        <select id="chapter_id" name="chapter_id" required>
                            <option value="">Choose a chapter</option>
                            @foreach ($chapters as $chapter)
                                <option value="{{ $chapter->id }}" @selected((int) old('chapter_id', $event->chapter_id) === $chapter->id)>{{ $chapter->name }}</option>
                            @endforeach
                        </select>
                        @error('chapter_id') <div class="error">{{ $message }}</div> @enderror
                    </div>
                @endif
                <div class="field">
                    <label for="parent_id">Part of (optional)</label>
                    <select id="parent_id" name="parent_id">
                        <option value="">Stand-alone event</option>
                        @foreach ($parents as $parent)
                            <option value="{{ $parent->id }}" @selected((int) old('parent_id', $event->parent_id) === $parent->id)>
                                {{ $parent->chapter->code }} · {{ $parent->title }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Pick a parent to make this a sub-event, such as a workshop inside an expo.</div>
                    @error('parent_id') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" maxlength="5000">{{ old('description', $event->description) }}</textarea>
                @error('description') <div class="error">{{ $message }}</div> @enderror
            </div>
        </fieldset>

        <fieldset>
            <legend>Where and when</legend>
            <div class="field-row">
                <div class="field">
                    <label for="venue">Venue</label>
                    <input id="venue" name="venue" value="{{ old('venue', $event->venue) }}" required maxlength="160">
                    @error('venue') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="city">City</label>
                    <input id="city" name="city" value="{{ old('city', $event->city) }}" maxlength="100">
                    @error('city') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="starts_at">Starts</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ $dt('starts_at') }}" required>
                    @error('starts_at') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="ends_at">Ends</label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ $dt('ends_at') }}" required>
                    @error('ends_at') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Cover photo</legend>
            <div class="cover-field">
                <div class="cover-field-preview">
                    @if ($event->exists)
                        @include('partials.event-cover', ['event' => $event, 'variant' => 'card'])
                    @endif
                </div>
                <div>
                    <div class="field">
                        <label for="cover">Venue photo (optional)</label>
                        <input id="cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        <div class="hint">JPG, PNG or WebP, landscape, at least 800 × 450 pixels, up to 4 MB. Shown on the event card and as the banner of the event page. Without a photo, a gold drawing of the venue type is used.</div>
                        @error('cover') <div class="error">{{ $message }}</div> @enderror
                    </div>
                    @if ($event->cover_path)
                        <label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove the current photo</label>
                    @endif
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Capacity and registration rules</legend>
            <div class="field-row">
                <div class="field">
                    <label for="capacity">Maximum headcount</label>
                    <input id="capacity" type="number" name="capacity" min="1" value="{{ old('capacity', $event->capacity) }}" required>
                    <div class="hint">Registration stops automatically when this is reached.</div>
                    @error('capacity') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="ticket_price">Ticket price (USD)</label>
                    <input id="ticket_price" type="number" name="ticket_price" min="0" step="0.01" value="{{ old('ticket_price', $event->ticket_price) }}" required>
                    <div class="hint">Use 0 for a free event.</div>
                    @error('ticket_price') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="registration_opens_at">Registration opens</label>
                    <input id="registration_opens_at" type="datetime-local" name="registration_opens_at" value="{{ $dt('registration_opens_at') }}" required>
                    @error('registration_opens_at') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label for="registration_closes_at">Registration cutoff</label>
                    <input id="registration_closes_at" type="datetime-local" name="registration_closes_at" value="{{ $dt('registration_closes_at') }}" required>
                    @error('registration_closes_at') <div class="error">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="field" style="max-width:260px">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    @foreach (\App\Models\Event::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="hint">Only published events are visible to attendees.</div>
                @error('status') <div class="error">{{ $message }}</div> @enderror
            </div>
        </fieldset>

        <div class="actions">
            <button class="btn btn-primary" type="submit">{{ $event->exists ? 'Save changes' : 'Create event' }}</button>
            <a href="{{ $event->exists ? route('manage.events.show', $event) : route('manage.events.index') }}">Cancel</a>
        </div>
    </form>

    @if ($event->exists)
        <form method="POST" action="{{ route('manage.events.destroy', $event) }}" data-confirm="Delete this event permanently?">
            @csrf
            @method('DELETE')
            <button class="btn btn-sm btn-danger" type="submit">Delete event</button>
        </form>
    @endif
@endsection
