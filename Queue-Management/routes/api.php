<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingCallController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\DriverTripController;
use App\Http\Controllers\Api\PriorityClaimController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TimeController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public: no token needed.
Route::get('/time', TimeController::class);
Route::post('/register', [AuthController::class, 'register']);
// throttle:5,1 = at most 5 login attempts per minute per IP, then 429. Slows down password guessing.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Everything below needs a valid token AND an account that is still active.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Only students check in, so only students accept the location notice.
    Route::post('/consent', [AuthController::class, 'consent'])->middleware('role:student');

    // Student check-in. throttle:10,1 = 10 check-in attempts per minute per user (then 429).
    Route::post('/bookings', [BookingController::class, 'store'])->middleware(['role:student', 'throttle:10,1']);
    Route::get('/bookings/current', [BookingController::class, 'current'])->middleware('role:student');
    Route::get('/bookings/mine', [BookingController::class, 'mine'])->middleware('role:student');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->middleware('role:student');

    // Operators: check in a student by hand, and list bookings. Admins may read the list.
    Route::post('/bookings/manual', [BookingController::class, 'manual'])->middleware('role:operator');
    Route::get('/bookings', [BookingController::class, 'index'])->middleware('role:operator,admin');

    // Priority claims. Students submit; operators/admins review; admins alone decide. Drivers have no access.
    Route::post('/priority-claims', [PriorityClaimController::class, 'store'])->middleware(['role:student', 'throttle:10,1']);
    Route::get('/priority-claims/mine', [PriorityClaimController::class, 'mine'])->middleware('role:student');
    Route::get('/priority-claims', [PriorityClaimController::class, 'index'])->middleware('role:operator,admin');
    Route::get('/priority-claims/{claim}/document', [PriorityClaimController::class, 'document'])->middleware('role:operator,admin');
    Route::patch('/priority-claims/{claim}/approve', [PriorityClaimController::class, 'approve'])->middleware('role:admin');
    Route::patch('/priority-claims/{claim}/reject', [PriorityClaimController::class, 'reject'])->middleware('role:admin');

    // Drivers. A driver can only ever act on their own trips: the role middleware lets drivers in,
    // and TripPolicy / BookingCallPolicy then answer 403 for anybody else's trip.
    Route::middleware('role:driver')->group(function () {
        Route::get('/driver/trips', [DriverTripController::class, 'index']);
        Route::get('/driver/trips/{trip}/manifest', [DriverTripController::class, 'manifest']);
        Route::post('/driver/trips/{trip}/call-next', [DriverTripController::class, 'callNext']);
        Route::post('/booking-calls/{call}/board', [BookingCallController::class, 'board']);
        Route::post('/booking-calls/{call}/skip', [BookingCallController::class, 'skip']);
    });

    // Admin only: accounts, system settings, reports and the audit log.
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/hourly', [ReportController::class, 'hourly']);
        Route::get('/reports/routes', [ReportController::class, 'routes']);

        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });

    // Any logged-in user may read stations and routes (students need them for the route picker).
    Route::get('/stations', [StationController::class, 'index']);
    Route::get('/routes', [RouteController::class, 'index']);

    // Writes and the schedule belong to operators. There is no DELETE anywhere:
    // history is kept, and something is retired by setting is_active = false.
    Route::middleware('role:operator')->group(function () {
        Route::post('/stations', [StationController::class, 'store']);
        Route::put('/stations/{station}', [StationController::class, 'update']);

        Route::post('/routes', [RouteController::class, 'store']);
        Route::put('/routes/{route}', [RouteController::class, 'update']);

        Route::get('/buses', [BusController::class, 'index']);
        Route::post('/buses', [BusController::class, 'store']);
        Route::put('/buses/{bus}', [BusController::class, 'update']);

        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::put('/trips/{trip}', [TripController::class, 'update']);
        Route::patch('/trips/{trip}/cancel', [TripController::class, 'cancel']);
    });
});
