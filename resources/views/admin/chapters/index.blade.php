@extends('layouts.app')

@section('title', 'Chapters')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administration</div>
            <h1>Regional chapters</h1>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.chapters.create') }}">New chapter</a>
    </div>

    @if ($rows->isEmpty())
        <div class="empty">No chapters yet.</div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Chapter</th><th>Code</th><th class="num">Events</th><th class="num">Tickets</th><th class="num">Revenue</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                                <div class="muted small">{{ $row['region'] }}</div>
                            </td>
                            <td class="mono">{{ $row['code'] }}</td>
                            <td class="num">{{ $row['events'] }}</td>
                            <td class="num">{{ number_format($row['tickets']) }}</td>
                            <td class="num">${{ number_format($row['revenue'], 2) }}</td>
                            <td class="right nowrap">
                                <a href="{{ route('manage.dashboard', ['chapter' => $row['id']]) }}">Performance</a> ·
                                <a href="{{ route('admin.matrix', ['chapter' => $row['id']]) }}">Participation</a> ·
                                <a href="{{ route('admin.chapters.edit', $row['id']) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
