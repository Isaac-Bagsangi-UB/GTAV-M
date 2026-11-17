@extends('layouts.app')

@section('title', 'Audit log')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administration</div>
            <h1>Audit log</h1>
        </div>
    </div>
    <p class="muted" style="max-width:68ch;margin-top:-16px;margin-bottom:32px">
        A permanent record of who did what and when: sign-ins and failed attempts, account and role changes, event and chapter edits, registrations, uploaded files and generated reports. Entries cannot be edited or deleted from the website.
    </p>

    <form class="filters" method="GET" action="{{ route('admin.audit-log') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search descriptions" aria-label="Search the audit log">
        <select name="group" aria-label="Type of activity">
            <option value="">All activity</option>
            @foreach ($groups as $value => $label)
                <option value="{{ $value }}" @selected($group === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark" type="submit">Filter</button>
        @if (request()->hasAny(['q', 'group']))
            <a href="{{ route('admin.audit-log') }}">Clear</a>
        @endif
    </form>

    @if ($logs->isEmpty())
        <div class="empty">
            @include('partials.icon', ['name' => 'shield', 'class' => 'icon-lg'])
            No activity matches these filters.
        </div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Type</th>
                        <th>What happened</th>
                        <th>By</th>
                        <th>IP address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr>
                            <td class="log-time">
                                {{ $log->created_at?->format('M j, Y') }}
                                <div class="muted small">{{ $log->created_at?->format('g:i:s A') }}</div>
                            </td>
                            <td><span class="badge log-group">{{ $groups[$log->group()] ?? ucfirst($log->group()) }}</span></td>
                            <td>{{ $log->description }}</td>
                            <td>
                                @if ($log->user)
                                    {{ $log->user->name }}
                                    <div class="muted small">{{ $log->user->roleLabel() }}</div>
                                @else
                                    <span class="muted">Not signed in</span>
                                @endif
                            </td>
                            <td class="mono small">{{ $log->ip_address ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    @endif
@endsection
