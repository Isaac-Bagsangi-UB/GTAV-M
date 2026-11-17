<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $heading }} {{ $number }}</title>
    <style>
        @@page { margin: 28px; }
        body { margin: 0; font-family: DejaVu Serif, serif; color: #0F0F0F; text-align: center; }
        /* Sized so the frame fills one A4 landscape page (dompdf measures 712 px at most). */
        .frame { border: 3px solid #0F0F0F; padding: 6px; height: 708px; }
        .inner { border: 1px solid #D4AF37; height: 706px; }
        .band { background: #0F0F0F; padding: 18px 0 12px; border-bottom: 2px solid #D4AF37; }
        .band img { height: 132px; }
        .content { padding: 26px 70px 0; }
        .brand { font-family: DejaVu Sans, sans-serif; font-size: 12px; letter-spacing: 5px; text-transform: uppercase; color: #9A7B1C; }
        @@font-face { font-family: "Cormorant"; font-weight: normal; src: url("{{ resource_path('fonts/CormorantGaramond-SemiBold.ttf') }}") format("truetype"); }
        h1 { font-family: "Cormorant", "DejaVu Serif", serif; font-size: 40px; margin: 10px 0 2px; font-weight: normal; color: #0F0F0F; }
        .line { font-size: 14px; color: #4A4A4A; margin: 20px 0 8px; }
        .name { font-size: 32px; font-weight: bold; margin: 6px 0; border-bottom: 1px solid #b9b3a4; display: inline-block; padding: 0 30px 8px; }
        .event { font-size: 20px; font-weight: bold; margin: 6px 0 4px; }
        .meta { font-size: 13px; color: #4A4A4A; }
        table { width: 100%; margin-top: 64px; font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #4A4A4A; }
        td { width: 33%; vertical-align: bottom; }
        .sign { border-top: 1px solid #0F0F0F; padding-top: 6px; margin: 0 24px; }
    </style>
</head>
<body>
    <div class="frame">
        <div class="inner">
            <div class="band"><img src="{{ \App\Support\Brand::fullDataUri() }}" alt="GTAV-M, Global Trade Association Events Manager"></div>
            <div class="content">
            <div class="brand">Global Trade Association · {{ $event->chapter->name }}</div>
            <h1>{{ $heading }}</h1>

            <div class="line">This certifies that</div>
            <div class="name">{{ $person->name }}</div>
            @if ($person->company)
                <div class="meta">{{ $person->company }}</div>
            @endif

            <div class="line">{{ $lead }}</div>
            <div class="event">{{ $event->title }}</div>
            @if ($topic)
                <div class="meta">Topic: {{ $topic }}</div>
            @endif
            <div class="meta">
                {{ $event->starts_at->format('F j, Y') }}@if (! $event->starts_at->isSameDay($event->ends_at)) to {{ $event->ends_at->format('F j, Y') }}@endif
                · {{ $event->venue }}@if ($event->city), {{ $event->city }}@endif
            </div>

            <table>
                <tr>
                    <td><div class="sign">Chapter Manager</div></td>
                    <td>
                        Certificate no. {{ $number }}<br>
                        {{ $note }}
                    </td>
                    <td><div class="sign">Global Event Administrator</div></td>
                </tr>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
