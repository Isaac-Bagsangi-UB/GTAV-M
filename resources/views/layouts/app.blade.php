<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GTAV-M' }} | Global Trade Association</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/tailwind.css', 'resources/js/app.js'])
    @endif
</head>
<body>
<header class="topbar"><a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">G</span><span>GTAV<span class="accent">-M</span><small>GLOBAL TRADE ASSOCIATION</small></span></a>
<nav>@auth <a href="{{ route('events.index') }}">Browse events</a>@if(auth()->user()->isManager()) <a href="{{ route('events.create') }}">Create event</a><a href="{{ route('staff.index') }}">Staff</a>@endif @if(auth()->user()->isGateStaff() || auth()->user()->isManager()) <a href="{{ route('check-in') }}">Gate check-in</a>@endif <a class="notification-link" href="{{ route('notifications') }}" aria-label="Notifications">Notifications @if(auth()->user()->unreadNotifications->count())<span class="notification-dot">{{ auth()->user()->unreadNotifications->count() }}</span>@endif</a><a class="user-chip" href="{{ route('account') }}">{{ auth()->user()->name }} <small>{{ str_replace('_', ' ', auth()->user()->role) }}</small></a><form method="POST" action="{{ route('logout') }}">@csrf<button class="link-button">Log out</button></form>@endauth</nav>
</header>
<main class="shell">@if(session('success'))<div class="notice success" role="status"><span class="notice-icon">✓</span><span>{{ session('success') }}</span></div>@endif @if($errors->any())<div class="notice error" role="alert"><span class="notice-icon">!</span><span>{{ $errors->first() }}</span></div>@endif @yield('content')</main>
</body>
</html>
