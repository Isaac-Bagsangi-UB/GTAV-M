# GTAV-M: Global Trade Association Events Manager

Event registration, credential issuance, gate check-in and management reporting for a global trade association.
Built with **Laravel + MySQL** for WEBSYS2 (University of Baguio, School of Information Technology).

Repository: https://github.com/Isaac-Bagsangi-UB/GTAV-M

## What it solves

| Problem (from the proposal) | How GTAV-M handles it |
| --- | --- |
| Duplicate records and slow check-in | One account per person and one `registrations` table for every event. A unique key on (person, event) makes duplicates impossible. Each registration gets its own ticket code and QR code. |
| Insecure document access | Entry passes, certificates and workshop materials are generated or stored privately and released only after the signed-in user's registration (or speaker assignment) is verified. |
| No overall visibility | A live dashboard and three PDF reports roll tickets, revenue and turnout up from sub-events to events to chapters. Managers see their chapter; administrators see all. |
| No real-time gate validation | A token-protected API (`POST /api/v1/checkins/scan`) and a phone-camera scanner at `/gate`. Every gate shares one database, so a pass works once. |

## Roles

| Role | Lands on | Can do |
| --- | --- | --- |
| Global Event Administrator | `/manage` | Everything: all chapters, users and roles, participation matrix, audit log, all reports |
| Regional Chapter Manager | `/manage` | Events, capacity and cutoff rules, registrations, speakers, materials and reports for **their own chapter only** |
| Corporate Attendee | `/my/registrations` | Sign up, register, cancel, download entry pass, certificate and materials |
| Guest Speaker | `/speaker/sessions` | Sign up, see assigned sessions and headcount, upload and download materials, download a speaker certificate after the event |
| Venue Gate Staff | `/gate` | Pick an event, scan passes, find an attendee by name or ticket code, see the live entry count against capacity |

Access control has two layers:

1. **Route middleware** `role:admin,chapter_manager` (`app/Http/Middleware/EnsureRole.php`) decides which area a role may enter.
2. **Gates** in `app/Providers/AppServiceProvider.php` decide which records inside that area a user may touch, for example only the manager's own chapter.

## The three reports

Under **Reports** (administrators and chapter managers). Each is a PDF made from live data, carries the GTAV-M logo, a report number, the time and the person who generated it, and is written to the audit log.

| Report | Answers | Filters |
| --- | --- | --- |
| **Chapter Performance** | How is each chapter doing? Tickets against capacity, revenue and turnout, with every event and its sub-events | Chapter (administrators) |
| **Event Attendance** | Who came to this event? Every ticket holder, checked in or not, and when | Event |
| **Registration and Revenue** | What did we sell in this period? Registrations, cancellations and income by chapter and event | Date range, chapter (administrators) |

## Setup

You need **PHP 8.4 or newer** (the locked Symfony 8 packages require it; the team uses PHP 8.5), Composer, and MySQL.
Check with `php -v`. Use a folder **outside OneDrive**: OneDrive locks files while Composer writes them.

```powershell
git clone https://github.com/Isaac-Bagsangi-UB/GTAV-M.git gtav-m
cd gtav-m
composer install
copy .env.example .env
php artisan key:generate
```

**Database.** Start MySQL and create an empty database named `gtavm` (phpMyAdmin works). `.env.example` uses
`DB_PORT=3307`; change it to `3306` if your MySQL runs on the default port (XAMPP does). `APP_TIMEZONE=Asia/Manila`
sets the clock used on every screen, pass and report; change it if the events run in another timezone. Add `MAIL_MAILER=log`
so password-reset emails are written to `storage/logs/laravel.log` instead of being sent.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3307
DB_DATABASE=gtavm
DB_USERNAME=root
DB_PASSWORD=
MAIL_MAILER=log
```

**Build the tables, load the demo data and start the site:**

```powershell
php artisan migrate:fresh --seed
php artisan serve
```

Open http://127.0.0.1:8000. No `npm` build is needed: the interface uses the plain stylesheet and scripts in `public/`.

> Run `php artisan migrate:fresh --seed` again right before a demo. The demo data is dated relative to today
> and includes one event happening today, so the scanner always has something to scan. Seeding about 3,000 records
> takes 20 to 40 seconds.
>
> **Venue photos:** the first time you seed with internet, the seeder downloads one free-licensed photo per city
> from Wikimedia Commons (Singapore, Rotterdam, Houston, Manila, Cebu, Davao, Baguio) into
> `storage/app/private/event-covers/demo`. Later reseeds reuse them, so they work offline. Without internet the
> events show drawn gold covers instead. The photographer is credited under each photo on the event page.

**Run the automated tests:**

```powershell
php artisan test
```

## Demo accounts

Password for all of them: `password`

| Role | Email |
| --- | --- |
| Administrator | admin@gtavm.test |
| Chapter manager (Asia-Pacific) | manager.apac@gtavm.test |
| Chapter manager (Europe / Americas) | manager.eur@gtavm.test / manager.amer@gtavm.test |
| Chapter manager (Philippines) | manager.ph@gtavm.test |
| Attendee | attendee@gtavm.test |
| Speaker | speaker@gtavm.test |
| Gate staff (Asia-Pacific) | gate.apac@gtavm.test |
| Gate staff (Philippines) | gate.ph@gtavm.test (scans "Manila Members Briefing", today) |

## A demo that touches every requirement

1. **Attendee:** log in, open *My passes*, download the entry pass for "Singapore Members Briefing" (today) and the certificate for the past expo. Browse events: the Cold Chain workshop is **sold out** (capacity rule) and the Technical Summit **opens soon** (registration window rule).
2. **Gate staff** (phone or second browser): pick "Singapore Members Briefing", scan the QR on the pass or type its ticket code. The first scan says **LET IN**; scanning it again says **ALREADY INSIDE** with the time they entered; an unknown or cancelled ticket says **DO NOT LET IN** with the reason. *Gate activity* keeps one line per ticket and counts repeat scans. Type part of a name to find someone whose QR will not scan.
3. **Chapter manager:** sees only Asia-Pacific figures; opens an event, exports the CSV, uploads a material, assigns a speaker, generates the three reports.
4. **Administrator:** all chapters, the participation matrix, users and roles, the audit log of everything above.
5. **Speaker:** sees assigned sessions, uploads slides, downloads the speaker certificate for the past expo.
6. **Forgotten password:** on the log in page, request a reset link. With `MAIL_MAILER=log` the email is written to `storage/logs/laravel.log`; open the link from there.
7. **API:** run the examples in `API.md`.

## The 8 technical requirements

| # | Requirement | Where it is |
| --- | --- | --- |
| 1 | Multi-role authorization | 5 roles. `EnsureRole` middleware guards each area in `routes/web.php` and `routes/api.php`; Gates `manage-event`, `scan-event`, `upload-materials`, `access-materials` in `AppServiceProvider` guard actions per chapter and per event; `@can` hides buttons in the views. Tests: `RoleAccessTest`, `GateCheckInTest`. |
| 2 | Authentication | Register (attendee or speaker), log in, log out, forgotten password, session regenerated on log in and invalidated on log out (`Auth/` controllers). Tests: `AuthenticationTest`, `PasswordResetTest`. |
| 3 | Advanced relationships | Many-to-many: `User::registeredEvents()` / `Event::attendees()` through the `registrations` pivot, and `User::sessions()` / `Event::speakers()` through `event_speaker`. Has-many-through: `Chapter::registrations()` (chapters → events → registrations). Test: `DatabaseRelationshipsTest`. |
| 4 | 1,000+ seeded records | `DatabaseSeeder` creates about 3,000 records across 4 chapters, including the Philippines (Manila, Cebu, Davao, Baguio); the year of chapter history is built with factories and relationship states (`for()`, `sequence()`, `registeredDuring()`, `CheckInFactory::admits()`). Test: `DatabaseSeederTest`. |
| 5 | File upload and download | Workshop materials: validated by type and size (20 MB), stored with the `Storage` facade on the private disk, downloaded only by registered attendees, speakers and staff (`MaterialController`). Event cover photos: validated as images (JPG, PNG, WebP, 4 MB, at least 800 × 450), stored privately and streamed by `EventBrowseController@cover`. Tests: `MaterialFilesTest`, `EventCoverTest`. |
| 6 | Token API | `routes/api.php`, `/api/v1`, Sanctum bearer tokens, `EventResource` and `RegistrationResource`. Tests: `ApiEndpointsTest`, `GateCheckInTest`. Reference: `API.md`. |
| 7 | Unit and feature tests | `tests/Unit` (capacity and window rules, cancellation and certificate rules, report totals, file size labels, ticket tokens) and `tests/Feature` (routes, authorization, uploads, API structure, reports, seeding). |
| 8 | MVC structure | Web controllers by role in `app/Http/Controllers/{Auth,Admin,Manage,Speaker,Gate}`, API controllers in `app/Http/Controllers/Api`, business rules in `app/Services`, Blade views. |

## Where things are

```
app/Models/                     User, Chapter, Event, Registration, CheckIn, Material, AuditLog
app/Services/
  RegistrationService.php       capacity limit, registration window, no duplicates (row lock)
  CheckInService.php            validates a scanned pass, logs every scan, name search
  ChapterReportService.php      roll-up of tickets, revenue and turnout
app/Http/Middleware/
  EnsureRole.php                role-based access
  SecurityHeaders.php           CSP, HSTS, X-Frame-Options and related headers
app/Http/Controllers/
  Auth/ Admin/ Manage/ Speaker/ Gate/   web screens per role (Manage/ReportController: the three reports)
  Api/                                  REST API (JSON)
app/Support/                    QrCode.php (QR as SVG), Brand.php (logo for PDFs)
database/migrations/            Laravel's users, cache and jobs tables, plus 9 for this app (incl. API tokens)
database/factories/  database/seeders/   test factories and demo data
tests/Unit/  tests/Feature/      PHPUnit unit and feature tests (php artisan test)
API.md  DEPLOYMENT.md            REST API reference; going live on a server
resources/views/                Blade templates; pdf/ = pass, certificate, reports; errors/ = branded error pages
resources/fonts/                heading font for PDFs
public/css/app.css              the whole stylesheet, no npm build needed
public/js/                      app.js (menu, dialogs, loading states), scanner.js, vendor/html5-qrcode
public/images/, public/fonts/   logo set, favicon, self-hosted web fonts
routes/web.php  routes/api.php
```

## Database

- `chapters` 1 to many `events`; `events` 1 to many `events` (sub-events through `parent_id`)
- `users` many to many `events` through `registrations` (the participation matrix): `User::registeredEvents()`, `Event::attendees()`
- `users` many to many `events` through `event_speaker` (speakers)
- `chapters` has many `registrations` through `events`: `Chapter::registrations()`, used by the chapter reports
- `events` 1 to many `materials`; `registrations` 1 to many `check_ins` (every scan, including rejected ones)
- `audit_logs`: who did what and when
- `users.chapter_id` limits managers and gate staff to one chapter

## How it was tested

81 PHPUnit tests. `tests/Unit` checks the business rules on their own: capacity and registration window, when a ticket
can be cancelled or earn a certificate, report totals, file size labels, ticket tokens. `tests/Feature` covers sign-up,
log in and log out, role access, registration rules, gate scanning, file upload and download, the API's JSON structure
and tokens, cover photos, the three reports, password reset, the administration safeguards and the seeder.
Run them with `php artisan test`.

Before delivery the code also ran on a real Laravel 13 install with the demo data:

- every page requested as every role (141 checks, including forbidden pages returning 403);
- 31 browser interaction checks: log in, log out, wrong password, phone menu, confirmation dialog, registration, button loading state, gate scanning (admit, already in, unknown pass, name search), the full password reset including a reused link, the password rule;
- an automated accessibility pass on 19 pages at desktop and phone width: contrast, labels, alt text, names of buttons and links, heading order, tap-target size (0 issues);
- every PDF rendered and inspected: entry pass, attendee and speaker certificates, the three reports;
- page speed on an emulated slow 4G phone: largest content painted in 1.0 to 1.8 seconds, no layout shift.

## Things to know

- **Payment is simulated.** Registering records the ticket price as paid. There is no payment gateway.
- **Camera scanning needs HTTPS or localhost.** Browsers block the camera on plain `http://192.168...` addresses. Over Wi-Fi on a phone, type the ticket code or name instead, or serve the site over HTTPS.
- **Email** is written to the log file in this setup. For real email, set the `MAIL_` lines in `.env` (see `DEPLOYMENT.md`).
- Contact addresses on the Contact, Privacy and Terms pages use the placeholder domain `gtavm.test`. Replace them before going live.
- Going live on a real server: follow `DEPLOYMENT.md`.
- `resources/css`, `resources/js`, `vite.config.js` and `package.json` belong to the earlier Tailwind version of the interface and are not used by this one.
