<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckInController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1  (all URLs are prefixed with /api)
|--------------------------------------------------------------------------
| Authentication: Laravel Sanctum bearer tokens.
|   1. POST /api/v1/auth/token  { email, password, device_name }
|   2. Send "Authorization: Bearer <token>" with every other request.
| See API.md for the full reference.
*/
Route::prefix('v1')->group(function () {
    // Public
    Route::post('auth/token', [AuthController::class, 'token'])->middleware('throttle:api-token');
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // Corporate Attendees
        Route::middleware('role:attendee')->group(function () {
            Route::get('registrations', [RegistrationController::class, 'index']);
            Route::post('events/{event}/registrations', [RegistrationController::class, 'store']);
            Route::delete('registrations/{registration}', [RegistrationController::class, 'destroy']);
        });

        // Gate scanners
        Route::middleware('role:gate_staff,chapter_manager,admin')->group(function () {
            Route::post('checkins/scan', [CheckInController::class, 'scan'])->middleware('throttle:gate');
            Route::get('events/{event}/attendance', [CheckInController::class, 'attendance']);
            Route::get('events/{event}/attendees', [CheckInController::class, 'attendees'])->middleware('throttle:gate');
        });

        // Managers and administrators
        Route::middleware('role:admin,chapter_manager')->group(function () {
            Route::get('reports/chapters', [ReportController::class, 'chapters']);
            Route::get('reports/chapters/{chapter}', [ReportController::class, 'chapter']);
        });
    });
});
