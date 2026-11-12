<?php

use App\Http\Controllers\EventManagerController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventManagerController::class, 'home'])->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/login', [EventManagerController::class, 'login'])->name('login');
    Route::post('/login', [EventManagerController::class, 'authenticate']);
    Route::get('/register', [EventManagerController::class, 'register'])->name('register');
    Route::post('/register', [EventManagerController::class, 'storeUser']);
});
Route::post('/logout', [EventManagerController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [EventManagerController::class, 'dashboard'])->name('dashboard');
    Route::get('/my-registrations', [EventManagerController::class, 'registrations'])->name('registrations.index');
    Route::get('/my-registrations/calendar', [EventManagerController::class, 'calendar'])->name('registrations.calendar');
    Route::get('/events', [EventManagerController::class, 'events'])->name('events.index');
    Route::get('/events/create', [EventManagerController::class, 'createEvent'])->middleware('can:manage-events')->name('events.create');
    Route::get('/events/{event}', [EventManagerController::class, 'showEvent'])->name('events.show');
    Route::post('/events/{event}/register', [EventManagerController::class, 'registerForEvent'])->name('events.register');
    Route::get('/registrations/{registration}/pass', [EventManagerController::class, 'pass'])->name('registrations.pass');
    Route::get('/registrations/{registration}/ticket', [EventManagerController::class, 'ticket'])->name('registrations.ticket');
    Route::post('/events', [EventManagerController::class, 'storeEvent'])->middleware('can:manage-events')->name('events.store');
    Route::post('/registrations/{registration}/verify-payment', [EventManagerController::class, 'verifyPayment'])->middleware('can:manage-events')->name('registrations.verify-payment');
    Route::post('/registrations/{registration}/reject', [EventManagerController::class, 'rejectRegistration'])->middleware('can:manage-events')->name('registrations.reject');
    Route::post('/events/{event}/registrations/reject', [EventManagerController::class, 'bulkRejectRegistrations'])->middleware('can:manage-events')->name('events.registrations.reject');
    Route::put('/events/{event}/staff', [EventManagerController::class, 'assignStaff'])->middleware('can:manage-events')->name('events.staff.update');
    Route::get('/staff', [EventManagerController::class, 'staff'])->middleware('can:manage-events')->name('staff.index');
    Route::post('/staff', [EventManagerController::class, 'storeStaff'])->middleware('can:manage-events')->name('staff.store');
    Route::get('/account', [EventManagerController::class, 'account'])->name('account');
    Route::put('/account', [EventManagerController::class, 'updateAccount'])->name('account.update');
    Route::get('/notifications', [EventManagerController::class, 'notifications'])->name('notifications');
    Route::get('/check-in', [EventManagerController::class, 'checkIn'])->middleware('can:check-in')->name('check-in');
    Route::post('/check-in', [EventManagerController::class, 'scan'])->middleware('can:check-in');
});
