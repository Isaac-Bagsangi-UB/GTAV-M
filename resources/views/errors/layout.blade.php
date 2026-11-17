{{-- Standalone error page: no database or session calls, so it renders even when the app is failing. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · GTAV-M</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=4">
</head>
<body>
    <main class="container page error-page" id="main">
        <a href="{{ url('/') }}" class="error-logo">
            <img src="{{ asset('images/logo.webp') }}" width="720" height="391" alt="GTAV-M home">
        </a>
        <div class="eyebrow">Error @yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a class="btn btn-primary" href="{{ url('/') }}">Go to the home page</a>
            @hasSection('secondary')
                @yield('secondary')
            @else
                <a class="btn" href="{{ url('/events') }}">Browse events</a>
            @endif
        </div>
    </main>
</body>
</html>
