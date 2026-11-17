@extends('layouts.app')

@section('title', 'Terms & Conditions')
@section('description', 'Terms and conditions for using GTAV-M, the Global Trade Association Events Manager, and for attending its events.')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Last updated {{ now()->format('F Y') }}</div>
            <h1>Terms &amp; Conditions</h1>
        </div>
    </div>

    <div class="card prose">
        <h2>Your account</h2>
        <p>One account is for one person. Give accurate details, because your name and company are printed on your entry pass and certificate. Keep your password private and tell us if you think someone else has used your account.</p>

        <h2>Registering for events</h2>
        <ul>
            <li>Registration is open only between the opening date and the cutoff shown on each event.</li>
            <li>Each event has a maximum headcount. When it is reached, registration closes.</li>
            <li>Workshops and sessions inside a larger event need their own registration.</li>
            <li>You may cancel a registration until the event starts, unless you have already been checked in.</li>
        </ul>

        <h2>Entry passes</h2>
        <p>Each pass admits one person, once. Show the QR code at the gate. A pass that was already used, cancelled or issued for a different event will be refused.</p>

        <h2>Certificates and materials</h2>
        <p>Attendance certificates are issued after an event ends, to attendees who were checked in at the venue. Workshop materials are for registered attendees and may not be redistributed without the speaker's permission.</p>

        <h2>Acceptable use</h2>
        <p>Do not try to access other people's records, share or copy passes, upload harmful files, or interfere with the service. We may suspend accounts that do.</p>

        <h2>Changes</h2>
        <p>Event details can change. Registered attendees are told about changes to the date, time or venue. These terms may be updated, and the date at the top shows the latest version.</p>

        <h2>Contact</h2>
        <p>Questions about these terms: <a href="{{ route('contact') }}">contact the events team</a>.</p>
    </div>
@endsection
