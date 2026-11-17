{{--
    Shared frame for the three management reports (dompdf).
    Every page: black header band with the GTAV-M logo, report title and scope; footer with the report
    number, who generated it and the page number.
--}}
@php
    $generatedAt = now();
    $reportNo = 'RPT-' . $generatedAt->format('Ymd-His') . '-' . str_pad((string) $generatedBy->id, 3, '0', STR_PAD_LEFT);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} · {{ $scope }}</title>
    <style>
        @@font-face { font-family: "Cormorant"; font-weight: normal; src: url("{{ resource_path('fonts/CormorantGaramond-SemiBold.ttf') }}") format("truetype"); }
        @@page { margin: 112px 34px 58px 34px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1C1C1C; line-height: 1.45; }

        .band { position: fixed; top: -112px; left: -34px; right: -34px; height: 92px; background: #0F0F0F; }
        .band table { width: 100%; border-collapse: collapse; }
        .band td { vertical-align: middle; padding: 0; }
        .band .logo { width: 170px; padding: 10px 0 0 30px; }
        .band .logo img { height: 72px; }
        .band .title { text-align: right; padding: 16px 34px 0 0; color: #CFCFCF; }
        .band .title .name { font-family: "Cormorant", "DejaVu Serif", serif; font-size: 24px; color: #D4AF37; line-height: 1.1; }
        .band .title .scope { font-size: 8.5px; letter-spacing: 1.6px; text-transform: uppercase; margin-top: 4px; color: #CFCFCF; }
        .rule { position: fixed; top: -20px; left: -34px; right: -34px; height: 2px; background: #D4AF37; }

        .foot { position: fixed; bottom: -40px; left: 0; right: 0; height: 22px; border-top: 0.5px solid #D4AF37; padding-top: 6px; font-size: 7.5px; color: #6E6E6E; }
        .foot table { width: 100%; border-collapse: collapse; }
        .foot .page:before { content: "Page " counter(page); }

        h2 { font-family: "Cormorant", "DejaVu Serif", serif; font-size: 17px; font-weight: normal; color: #0F0F0F; margin: 18px 0 6px; }
        h2 small { font-family: "DejaVu Sans", sans-serif; font-size: 8px; color: #6E6E6E; letter-spacing: 1px; text-transform: uppercase; margin-left: 6px; }
        p.lead { font-size: 9.5px; color: #3A3A3A; margin: 0 0 12px; }
        p.note { font-size: 8px; color: #6E6E6E; margin: 6px 0 0; }

        table.kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 4px -6px 10px; }
        table.kpis td { width: 25%; background: #F7F4EC; border-top: 2px solid #D4AF37; padding: 9px 11px; vertical-align: top; }
        table.kpis .label { font-size: 7px; letter-spacing: 1.4px; text-transform: uppercase; color: #6E6E6E; }
        table.kpis .value { font-family: "Cormorant", "DejaVu Serif", serif; font-size: 21px; color: #0F0F0F; line-height: 1.15; margin-top: 2px; }
        table.kpis .sub { font-size: 7.5px; color: #6E6E6E; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; page-break-inside: auto; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #0F0F0F; color: #E5C76B; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; text-align: left; padding: 6px 7px; }
        table.data td { padding: 5px 7px; border-bottom: 0.5px solid #E2DCCB; vertical-align: top; }
        table.data tbody tr:nth-child(even) td { background: #FBFAF6; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data tr.sub td { color: #4A4A4A; }
        table.data tr.sub td.first { padding-left: 20px; }
        table.data tr.group td { background: #F1ECDD; font-weight: bold; color: #0F0F0F; }
        table.data tfoot td { font-weight: bold; border-top: 1.2px solid #0F0F0F; border-bottom: 0; background: #F7F4EC; }
        .muted { color: #6E6E6E; }
        .tag { font-size: 7px; letter-spacing: .8px; text-transform: uppercase; padding: 1px 5px; border: 0.5px solid #B9A25A; color: #7A6418; }
        .yes { color: #1E7A4E; font-weight: bold; }
        .no { color: #8A8A8A; }
        .empty { padding: 14px; text-align: center; color: #6E6E6E; border: 0.5px dashed #B9A25A; }
    </style>
</head>
<body>
    <div class="band">
        <table>
            <tr>
                <td class="logo"><img src="{{ \App\Support\Brand::markDataUri() }}" alt="GTAV-M"></td>
                <td class="title">
                    <div class="name">{{ $title }}</div>
                    <div class="scope">{{ $scope }}@hasSection('period') · @yield('period')@endif</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="rule"></div>

    <div class="foot">
        <table>
            <tr>
                <td>{{ $reportNo }} · Generated {{ $generatedAt->format('M j, Y g:i A') }} by {{ $generatedBy->name }} ({{ $generatedBy->roleLabel() }})</td>
                <td style="text-align:right">GTAV-M · Confidential · <span class="page"></span></td>
            </tr>
        </table>
    </div>

    @yield('body')
</body>
</html>
