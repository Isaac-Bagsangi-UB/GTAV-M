@extends('layouts.app')

@section('title', 'Regional expos, summits and workshops')
@section('description', 'Register once for every Global Trade Association expo, summit and workshop. Download your QR entry pass, certificates and workshop materials from one account.')

@push('head')
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Global Trade Association Events Manager (GTAV-M)',
        'url' => url('/'),
        'logo' => asset('images/og-image.jpg'),
        'contactPoint' => ['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => 'events@gtavm.test'],
    ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@section('hero')
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <div class="eyebrow">Global Trade Association</div>
                <h1>One registration for <em>every</em> expo, summit and workshop.</h1>
                <p>Register once, download your QR entry pass, and walk straight through the gate. Certificates and workshop materials are waiting in your account afterwards.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="{{ route('events.index') }}">Browse events</a>
                    @guest
                        <a class="btn" href="{{ route('register') }}">Create an account</a>
                    @endguest
                </div>
                <div class="hero-stats">
                    <div><strong>{{ $upcomingCount }}</strong><span>upcoming events and sessions</span></div>
                    <div><strong>{{ $chapterCount }}</strong><span>regional chapters</span></div>
                </div>
            </div>
            <div class="hero-logo">
                <img src="{{ asset('images/logo.webp') }}" width="720" height="391" alt="GTAV-M emblem: a golden globe above the GTAV-M wordmark and a QR ticket" fetchpriority="high">
            </div>
        </div>
    </section>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Coming up</div>
            <h2>Next events</h2>
        </div>
        <a href="{{ route('events.index') }}">See all events &rarr;</a>
    </div>

    @if ($events->isEmpty())
        <div class="empty">No events are published yet. Check back soon.</div>
    @else
        <div class="grid grid-3">
            @foreach ($events as $event)
                @include('partials.event-card', ['event' => $event])
            @endforeach
        </div>
    @endif
@endsection
