<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\MatrixController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\EventBrowseController;
use App\Http\Controllers\Gate\ScannerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manage\DashboardController;
use App\Http\Controllers\Manage\EventController as ManageEventController;
use App\Http\Controllers\Manage\EventSpeakerController;
use App\Http\Controllers\Manage\ReportController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Speaker\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/events', [EventBrowseController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [EventBrowseController::class, 'show'])->name('events.show');
Route::view('/contact', 'pages.contact')->name('contact');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    // Forgotten password: emailed single-use link
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Signed-in users. The "role" middleware decides who may enter each area.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');

    // Workshop materials. Access is checked per event (gates in AppServiceProvider).
    Route::get('/materials/{material}', [MaterialController::class, 'download'])->name('materials.download');
    Route::post('/events/{event}/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

    // Corporate Attendees
    Route::middleware('role:attendee')->group(function () {
        Route::post('/events/{event}/register', [RegistrationController::class, 'store'])->name('registrations.store');
        Route::get('/my/registrations', [RegistrationController::class, 'index'])->name('registrations.index');
        Route::delete('/my/registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');
        Route::get('/my/registrations/{registration}/pass', [RegistrationController::class, 'pass'])->name('registrations.pass');
        Route::get('/my/registrations/{registration}/certificate', [RegistrationController::class, 'certificate'])->name('registrations.certificate');
    });

    // Guest Speakers
    Route::middleware('role:speaker')->group(function () {
        Route::get('/speaker/sessions', [SessionController::class, 'index'])->name('speaker.sessions');
        Route::get('/speaker/sessions/{event}/certificate', [SessionController::class, 'certificate'])->name('speaker.certificate');
    });

    // Venue Gate Staff (managers and administrators can also work a gate)
    Route::middleware('role:gate_staff,chapter_manager,admin')->group(function () {
        Route::get('/gate', [ScannerController::class, 'index'])->name('gate.scanner');
        Route::get('/gate/search', [ScannerController::class, 'search'])->middleware('throttle:gate')->name('gate.search');
        Route::post('/gate/scan', [ScannerController::class, 'scan'])->middleware('throttle:gate')->name('gate.scan');
    });

    // Regional Chapter Managers and Global Event Administrators
    Route::middleware('role:admin,chapter_manager')->prefix('manage')->name('manage.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/events/{event}/export', [ManageEventController::class, 'export'])->name('events.export');
        Route::post('/events/{event}/speakers', [EventSpeakerController::class, 'store'])->name('events.speakers.store');
        Route::delete('/events/{event}/speakers/{user}', [EventSpeakerController::class, 'destroy'])->name('events.speakers.destroy');
        Route::resource('events', ManageEventController::class);

        // Reports (PDF)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/chapter-performance', [ReportController::class, 'chapterPerformance'])->name('reports.chapter-performance');
        Route::get('/reports/event-attendance', [ReportController::class, 'eventAttendance'])->name('reports.event-attendance');
        Route::get('/reports/registration-revenue', [ReportController::class, 'registrationRevenue'])->name('reports.registration-revenue');
    });

    // Global Event Administrators only
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('chapters', ChapterController::class)->except('show');
        Route::resource('users', UserController::class)->except('show');
        Route::get('/matrix', [MatrixController::class, 'index'])->name('matrix');
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log');
    });
});
