<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Entry pass {{ $registration->reference_code }}</title>
    <style>
        @@page { margin: 0; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0F0F0F; font-size: 11px; }
        .head { background: #0F0F0F; color: #ffffff; padding: 14px 28px 14px; border-bottom: 3px solid #D4AF37; }
        .head table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: middle; padding: 0; }
        .head img { height: 62px; }
        .kind { font-size: 8px; letter-spacing: 2.5px; text-transform: uppercase; color: #E5C76B; text-align: right; line-height: 1.6; }
        .kind span { color: #CFCFCF; }
        .body { padding: 22px 28px 0; }
        .type { font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #9A7B1C; font-weight: bold; }
        h1 { font-size: 19px; margin: 4px 0 14px; line-height: 1.25; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 0 0 10px; }
        .label { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #6E6E6E; }
        .value { font-size: 12px; font-weight: bold; }
        .tear { border-top: 2px dashed #b9b3a4; margin: 10px 28px 0; }
        .qr { text-align: center; padding: 18px 28px 0; }
        .qr img { width: 210px; height: 210px; }
        .ref { font-family: DejaVu Sans Mono, monospace; font-size: 17px; font-weight: bold; letter-spacing: 2px; margin-top: 6px; }
        .note { font-size: 9px; color: #6E6E6E; margin-top: 8px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="head">
        <table>
            <tr>
                <td><img src="{{ \App\Support\Brand::markDataUri() }}" alt="GTAV-M"></td>
                <td class="kind">Entry pass<br><span>{{ $registration->event->chapter->name }}</span></td>
            </tr>
        </table>
    </div>

    <div class="body">
        <div class="type">{{ $registration->event->typeLabel() }}</div>
        <h1>{{ $registration->event->title }}</h1>

        <table>
            <tr>
                <td style="width:55%">
                    <div class="label">Attendee</div>
                    <div class="value">{{ $registration->user->name }}</div>
                </td>
                <td>
                    <div class="label">Company</div>
                    <div class="value">{{ $registration->user->company ?: '-' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Date</div>
                    <div class="value">{{ $registration->event->starts_at->format('D, M j, Y') }}</div>
                    <div>{{ $registration->event->starts_at->format('g:i A') }} to {{ $registration->event->ends_at->format($registration->event->starts_at->isSameDay($registration->event->ends_at) ? 'g:i A' : 'M j, g:i A') }}</div>
                </td>
                <td>
                    <div class="label">Venue</div>
                    <div class="value">{{ $registration->event->venue }}</div>
                    <div>{{ $registration->event->city }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="tear"></div>

    <div class="qr">
        <img src="{{ $qr }}" alt="QR code">
        <div class="ref">{{ $registration->reference_code }}</div>
        <div class="note">
            Show this code at the gate. It admits one person, once.<br>
            If the code will not scan, gate staff can type the reference above.
        </div>
    </div>
</body>
</html>
