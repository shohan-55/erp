<?php

use App\Http\Controllers\OperationalAuthController;
use App\Http\Controllers\OperationalAttendanceController;
use App\Http\Controllers\OperationalWorkflowController;
use Illuminate\Support\Facades\Route;

Route::prefix('app/c/{companySlug}')->group(function () {
    Route::get('/login', [OperationalAuthController::class, 'loginForm'])->name('operational.login')->middleware('guest');
    Route::post('/login', [OperationalAuthController::class, 'login'])->name('operational.login.submit')->middleware('guest');
    Route::middleware('auth')->group(function () {
        Route::get('/attendance', [OperationalAttendanceController::class, 'dashboard'])->name('operational.dashboard');
        Route::post('/attendance/check-in', [OperationalAttendanceController::class, 'checkIn'])->name('operational.checkin');
        Route::post('/attendance/check-out', [OperationalAttendanceController::class, 'checkOut'])->name('operational.checkout');
        Route::get('/requests', [OperationalWorkflowController::class, 'index'])->name('operational.requests');
        Route::post('/leave', [OperationalWorkflowController::class, 'submitLeave'])->name('operational.leave.submit');
        Route::post('/claims', [OperationalWorkflowController::class, 'submitClaim'])->name('operational.claim.submit');
        Route::post('/leave/{requestId}/review', [OperationalWorkflowController::class, 'reviewLeave'])->name('operational.leave.review');
        Route::post('/claims/{requestId}/review', [OperationalWorkflowController::class, 'reviewClaim'])->name('operational.claim.review');
        Route::post('/notifications/{notificationId}/read', [OperationalWorkflowController::class, 'markRead'])->name('operational.notification.read');
        Route::post('/logout', [OperationalAuthController::class, 'logout'])->name('operational.logout');
    });
});
