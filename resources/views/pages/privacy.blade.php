@extends('layouts.app')

@section('title', 'Privacy Policy')
@section('description', 'How GTAV-M, the Global Trade Association Events Manager, collects, uses, stores and protects personal data.')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Last updated {{ now()->format('F Y') }}</div>
            <h1>Privacy Policy</h1>
        </div>
    </div>

    <div class="card prose">
        <p>This policy explains what personal data the Global Trade Association Events Manager (GTAV-M) collects, why, and what you can do about it.</p>

        <h2>What we collect</h2>
        <ul>
            <li><strong>Account details:</strong> your name, work email, company, job title and phone number, and a securely hashed password.</li>
            <li><strong>Event records:</strong> the events you register for, the ticket price recorded, and the time you were checked in at the venue.</li>
            <li><strong>Files:</strong> workshop materials uploaded by speakers and managers.</li>
            <li><strong>Security records:</strong> sign-in attempts, important account actions and the internet address they came from.</li>
        </ul>

        <h2>Why we use it</h2>
        <ul>
            <li>To register you for events and issue your entry pass and attendance certificate.</li>
            <li>To confirm your entry at the gate.</li>
            <li>To give event managers attendance and ticket figures for their chapter.</li>
            <li>To keep accounts secure and investigate misuse.</li>
        </ul>

        <h2>Who can see it</h2>
        <p>Administrators and the manager of the chapter that hosts an event can see who registered for it. Gate staff see your name, company and ticket code when they check you in. We do not sell personal data or share it for advertising.</p>

        <h2>Cookies</h2>
        <p>The site uses only the cookies it needs to work: one that keeps you signed in and one that protects forms against forgery. There are no advertising or tracking cookies, so no cookie banner is shown.</p>

        <h2>How long we keep it</h2>
        <p>Account and event records are kept while your account is open. Security records are kept for twelve months. When an account is deleted, its registrations are deleted with it.</p>

        <h2>How we protect it</h2>
        <p>Passwords are stored as one-way hashes. Passes, certificates and materials are released only after your registration is verified. Access is limited by role, and security-relevant actions are logged.</p>

        <h2>Your choices</h2>
        <p>You can ask for a copy of your data, a correction, or deletion of your account by writing to <a href="mailto:privacy@gtavm.test">privacy@gtavm.test</a>. We answer within 30 days.</p>
    </div>
@endsection
