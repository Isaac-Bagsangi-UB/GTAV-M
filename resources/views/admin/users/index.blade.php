@extends('layouts.app')

@section('title', 'Users')
@section('noindex', '1')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Administration</div>
            <h1>Users and roles</h1>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}">New account</a>
    </div>

    <form class="filters" method="GET" action="{{ route('admin.users.index') }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email or company" aria-label="Search users">
        <select name="role" aria-label="Role">
            <option value="">All roles</option>
            @foreach (\App\Models\User::ROLES as $value => $label)
                <option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark" type="submit">Filter</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Role</th><th>Chapter</th><th>Company</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            <div class="muted small">{{ $user->email }}</div>
                        </td>
                        <td><span class="badge">{{ $user->roleLabel() }}</span></td>
                        <td>{{ $user->chapter?->code ?? '-' }}</td>
                        <td>{{ $user->company ?? '-' }}</td>
                        <td class="right nowrap">
                            <a href="{{ route('admin.users.edit', $user) }}">Edit</a>
                            @unless ($user->is(auth()->user()))
                                ·
                                <form class="inline" method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                      data-confirm="Delete this account and its registrations?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No accounts match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
@endsection
