{{--
    Event cover: the venue photo when one was uploaded, otherwise a drawn cover in the brand colours
    (gold line art of the event type with the city name engraved behind it).
    Expects: $event. Optional: $variant ('card' or 'hero'), $eager (true for the first image on a page).
--}}
@php
    $variant ??= 'card';
    $eager ??= false;
    $photo = $event->coverUrl();
    $id = 'cv'.$event->id.$variant;
    $place = mb_strtoupper($event->placeName());
    $seed = crc32($place);
    $glowX = 58 + $seed % 24; // the gold light sits in a slightly different spot for each city
    $glowY = 28 + intdiv($seed, 24) % 20;
@endphp
<div class="cover cover-{{ $variant }}">
    @if ($photo)
        <img src="{{ $photo }}" alt="{{ $event->venue }}{{ $event->city ? ', '.$event->city : '' }}"
             width="1280" height="600" @if ($event->cover_credit) title="{{ $event->cover_credit }}" @endif loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async">
    @else
        <svg viewBox="0 0 640 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false">
            <defs>
                <linearGradient id="{{ $id }}bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1c1912"/><stop offset=".55" stop-color="#121212"/><stop offset="1" stop-color="#0c0c0c"/></linearGradient>
                <radialGradient id="{{ $id }}gl" cx="{{ $glowX }}%" cy="{{ $glowY }}%" r="55%"><stop offset="0" stop-color="#D4AF37" stop-opacity=".32"/><stop offset="1" stop-color="#D4AF37" stop-opacity="0"/></radialGradient>
                <linearGradient id="{{ $id }}wm" gradientUnits="userSpaceOnUse" x1="0" y1="0" x2="430" y2="0"><stop offset="0" stop-color="#D4AF37" stop-opacity=".34"/><stop offset=".72" stop-color="#D4AF37" stop-opacity=".13"/><stop offset="1" stop-color="#D4AF37" stop-opacity="0"/></linearGradient>
                <linearGradient id="{{ $id }}bm" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F6E3A1" stop-opacity=".35"/><stop offset="1" stop-color="#F6E3A1" stop-opacity="0"/></linearGradient>
            </defs>
            <rect width="640" height="300" fill="url(#{{ $id }}bg)"/>
            <rect width="640" height="300" fill="url(#{{ $id }}gl)"/>
            <path d="M0 60H640M0 120H640M0 180H640M0 240H640M80 0V300M160 0V300M240 0V300M320 0V300M400 0V300M480 0V300M560 0V300" stroke="#D4AF37" stroke-opacity=".07"/>
            <text x="-6" y="262" class="cover-watermark" fill="none" stroke="url(#{{ $id }}wm)" stroke-width="1.2">{{ $place }}</text>
            <g fill="none" stroke="#E5C76B" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round">
                @switch($event->type)
                    @case('summit')
                        <g fill="url(#{{ $id }}bm)" stroke="none"><path d="M392 18L376 212H432Z" opacity=".7"/><path d="M470 10L438 212H506Z"/><path d="M548 18L510 212H566Z" opacity=".7"/></g>
                        <path d="M380 14h24M458 6h24M536 14h24" stroke-opacity=".9"/>
                        <rect x="398" y="70" width="144" height="80" rx="3" stroke-opacity=".8"/>
                        <path d="M412 132l26-24 18 14 30-34 40 44" stroke-opacity=".5"/>
                        <path d="M340 212L360 186H580L600 212Z" stroke-opacity=".9"/>
                        <path d="M340 212V222H600V212" stroke-opacity=".6"/>
                        <path d="M456 186V160H484V186M452 160H488M470 160V150" stroke-opacity=".9"/>
                        <circle cx="470" cy="146" r="4" stroke-opacity=".9"/>
                        <path d="M330 248q20-10 40 0M380 248q20-10 40 0M430 248q20-10 40 0M480 248q20-10 40 0M530 248q20-10 40 0M580 248q20-10 40 0" stroke-opacity=".35"/>
                        <path d="M355 266q20-10 40 0M405 266q20-10 40 0M455 266q20-10 40 0M505 266q20-10 40 0M555 266q20-10 40 0" stroke-opacity=".25"/>
                        @break
                    @case('workshop')
                        <rect x="420" y="40" width="150" height="92" rx="3" stroke-opacity=".85"/>
                        <path d="M436 64h70M436 80h96M436 96h52" stroke-opacity=".45"/>
                        <path d="M520 112l14-18 12 10 16-24" stroke-opacity=".6"/>
                        <path d="M440 132L428 176M550 132L562 176M495 132V150" stroke-opacity=".6"/>
                        <path d="M340 236L380 176H600L630 236Z" stroke-opacity=".9"/>
                        <path d="M340 236V244H630V236" stroke-opacity=".55"/>
                        <path d="M392 214l6-22h44l-4 22Z M384 214h60M470 214l4-22h44l-2 22Z M462 214h62M548 214l2-22h44l2 22Z M540 214h62" stroke-opacity=".85"/>
                        <circle cx="372" cy="200" r="5" stroke-opacity=".5"/><circle cx="604" cy="200" r="5" stroke-opacity=".5"/>
                        @break
                    @default
                        <path d="M330 150Q470 40 610 150" stroke-opacity=".9"/>
                        <path d="M345 150Q470 62 595 150" stroke-opacity=".5"/>
                        <path d="M370 132L380 150M410 104L415 150M450 90L452 150M490 90L488 150M530 104L525 150M570 132L560 150" stroke-opacity=".45"/>
                        <path d="M320 150H620" stroke-opacity=".9"/>
                        <path d="M352 210V176H402V210M352 176L362 166H412L402 176M412 166V200L402 210M432 210V170H492V210M432 170L442 160H502L492 170M502 160V200L492 210M522 210V176H572V210M522 176L532 166H582L572 176M582 166V200L572 210M377 166V140L392 146L377 152M467 160V128L484 135L467 142M547 166V140L562 146L547 152" stroke-opacity=".85"/>
                        <path d="M330 214H620" stroke-opacity=".6"/>
                @endswitch
            </g>
        </svg>
    @endif
    <span class="cover-place">@include('partials.icon', ['name' => 'pin']){{ $event->placeName() }}</span>
</div>
