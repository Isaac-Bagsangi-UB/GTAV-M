<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $pageTitle = trim($__env->yieldContent('title', 'Events')) . ' · GTAV-M';
        $pageDescription = trim($__env->yieldContent('description', 'GTAV-M, the Global Trade Association Events Manager: register for regional expos, technical summits and training workshops, download your QR entry pass and your certificates.'));
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#0F0F0F">
    <link rel="canonical" href="{{ url()->current() }}">
    @hasSection('noindex')
        <meta name="robots" content="noindex, nofollow">
    @endif

    {{-- Social sharing preview --}}
    <meta property="og:site_name" content="GTAV-M">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="GTAV-M, Global Trade Association Events Manager">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-32.png') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    {{-- Fonts are self-hosted: no third-party request, and the two main faces start loading at once. --}}
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff') }}" as="font" type="font/woff" crossorigin>
    <link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin.woff') }}" as="font" type="font/woff" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=7">
    <script src="{{ asset('js/app.js') }}?v=4" defer></script>
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main">Skip to main content</a>
    <div class="progress" id="progress" aria-hidden="true"></div>

    @if (! empty($showIntro))
        {{-- Brand intro on the first home-page visit of a session. Pure CSS: it fades by itself and never blocks clicks. --}}
        <div class="intro" aria-hidden="true">
            <img src="{{ asset('images/logo.webp') }}" width="720" height="391" alt="">
        </div>
    @endif

    <header class="nav">
        <div class="container nav-bar">
            <a class="brand" href="{{ route('home') }}">
                <img src="{{ asset('images/logo-nav-1x.webp') }}"
                     srcset="{{ asset('images/logo-nav-1x.webp') }} 1x, {{ asset('images/logo-nav.webp') }} 2x"
                     width="150" height="68" alt="GTAV-M home">
            </a>

            <button class="nav-toggle" type="button" id="nav-toggle" aria-controls="nav-links" aria-expanded="false">
                <span class="nav-toggle-bars" aria-hidden="true"></span>
                <span class="nav-toggle-text">Menu</span>
            </button>

            @php
                $me = auth()->user();
                $links = [];
                if ($me && $me->hasRole('admin', 'chapter_manager')) {
                    $links[] = ['Dashboard', 'manage.dashboard', 'manage.dashboard'];
                    $links[] = ['Events', 'manage.events.index', 'manage.events.*'];
                    $links[] = ['Reports', 'manage.reports.index', 'manage.reports.*'];
                }
                if ($me && $me->isAdmin()) {
                    $links[] = ['Chapters', 'admin.chapters.index', 'admin.chapters.*'];
                    $links[] = ['Participation', 'admin.matrix', 'admin.matrix'];
                    $links[] = ['Users', 'admin.users.index', 'admin.users.*'];
                    $links[] = ['Audit log', 'admin.audit-log', 'admin.audit-log'];
                }
                if ($me && $me->hasRole('gate_staff', 'chapter_manager', 'admin')) {
                    $links[] = ['Scanner', 'gate.scanner', 'gate.*'];
                }
                if ($me && $me->hasRole('speaker')) {
                    $links[] = ['My sessions', 'speaker.sessions', 'speaker.*'];
                }
                if ($me && $me->hasRole('attendee')) {
                    $links[] = ['My passes', 'registrations.index', 'registrations.*'];
                }
                if (! $me || $me->hasRole('attendee', 'speaker')) {
                    $links[] = ['Browse events', 'events.index', 'events.*'];
                }
                if (! $me) {
                    $links[] = ['Contact', 'contact', 'contact'];
                    $links[] = ['Log in', 'login', 'login'];
                }
            @endphp

            <nav class="nav-links" id="nav-links" aria-label="Main">
                @foreach ($links as [$label, $routeName, $pattern])
                    @php($isCurrent = request()->routeIs($pattern))
                    <a href="{{ route($routeName) }}" @class(['active' => $isCurrent]) @if ($isCurrent) aria-current="page" @endif>{{ $label }}</a>
                @endforeach

                @auth
                    <div class="nav-user">
                        <strong>{{ $me->name }}</strong>
                        <span>{{ $me->roleLabel() }}@if ($me->chapter) · {{ $me->chapter->code }}@endif</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-no-loading>
                        @csrf
                        <button type="submit">Log out</button>
                    </form>
                @else
                    <a class="nav-cta" href="{{ route('register') }}">Create account</a>
                @endauth
            </nav>
        </div>
    </header>

    @yield('hero')

    <main class="container page" id="main" tabindex="-1">
        @if (session('success'))
            <div class="flash flash-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('status'))
            <div class="flash flash-success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash flash-error" role="alert">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <a class="footer-brand" href="{{ route('home') }}">
                <img src="{{ asset('images/logo-nav-1x.webp') }}"
                     srcset="{{ asset('images/logo-nav-1x.webp') }} 1x, {{ asset('images/logo-nav.webp') }} 2x"
                     width="150" height="68" alt="GTAV-M home" loading="lazy">
            </a>
            <nav class="footer-links" aria-label="Footer">
                <a href="{{ route('events.index') }}">Browse events</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms &amp; Conditions</a>
            </nav>
            <p class="footer-note">&copy; {{ date('Y') }} Global Trade Association Events Manager</p>
        </div>
    </footer>

    {{-- One shared confirmation dialog, opened by any form that has a data-confirm attribute. --}}
    <dialog class="dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <h2 id="confirm-title">Please confirm</h2>
        <p id="confirm-message"></p>
        <div class="actions">
            <button class="btn" type="button" id="confirm-cancel">Go back</button>
            <button class="btn btn-primary" type="button" id="confirm-ok">Yes, continue</button>
        </div>
    </dialog>

    @stack('scripts')
</body>
</html>
