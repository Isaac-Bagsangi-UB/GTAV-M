@extends('layouts.app')

@section('title', 'Contact')
@section('description', 'Contact the Global Trade Association events team about registrations, entry passes, certificates, speaking and chapter events.')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">We are glad to help</div>
            <h1>Contact the events team</h1>
        </div>
    </div>

    <div class="grid grid-3">
        <div class="card">
            <h2>Registration and passes</h2>
            <p>Questions about a registration, an entry pass that will not download, or a certificate.</p>
            <p><a href="mailto:events@gtavm.test">events@gtavm.test</a></p>
        </div>
        <div class="card">
            <h2>Speakers and chapters</h2>
            <p>Speaking at an event, or asking a regional chapter about an expo, summit or workshop.</p>
            <p><a href="mailto:chapters@gtavm.test">chapters@gtavm.test</a></p>
        </div>
        <div class="card">
            <h2>Privacy requests</h2>
            <p>Ask for a copy of your personal data, a correction, or deletion of your account.</p>
            <p><a href="mailto:privacy@gtavm.test">privacy@gtavm.test</a></p>
        </div>
    </div>

    <div class="card prose">
        <h2>Before you write</h2>
        <ul>
            <li><strong>Lost your pass?</strong> Log in and open <em>My passes</em>. Every pass can be downloaded again.</li>
            <li><strong>Cannot log in?</strong> Use <a href="{{ route('password.request') }}">Forgot your password</a> on the log in page.</li>
            <li><strong>At the venue?</strong> Gate staff can find you by name if your QR code will not scan.</li>
        </ul>
        <p class="muted small">Office hours: Monday to Friday, 9:00 AM to 5:00 PM. We reply within two working days.</p>
    </div>
@endsection
