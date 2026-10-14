<?php

use App\Http\Controllers\OperationalAuthController;
use App\Http\Controllers\OperationalAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('app/c/{companySlug}')->group(function () {
    Route::get('/login', [OperationalAuthController::class, 'loginForm'])->name('operational.login')->middleware('guest');
    Route::post('/login', [OperationalAuthController::class, 'login'])->name('operational.login.submit')->middleware('guest');
    Route::middleware('auth')->group(function () {
        Route::get('/attendance', [OperationalAttendanceController::class, 'dashboard'])->name('operational.dashboard');
        Route::post('/attendance/check-in', [OperationalAttendanceController::class, 'checkIn'])->name('operational.checkin');
        Route::post('/attendance/check-out', [OperationalAttendanceController::class, 'checkOut'])->name('operational.checkout');
        Route::post('/logout', [OperationalAuthController::class, 'logout'])->name('operational.logout');
    });
});
