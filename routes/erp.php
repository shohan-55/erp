<?php

use App\Http\Controllers\Erp\AuthController;
use App\Http\Controllers\Erp\PortalController;
use App\Http\Controllers\Erp\AttendanceController;
use App\Http\Controllers\Erp\WorkflowController;
use App\Http\Controllers\Erp\PayrollController;
use App\Http\Controllers\Erp\PasswordController;
use App\Http\Controllers\Erp\ActivationController;
use App\Http\Controllers\Erp\TeamController;
use App\Http\Controllers\Erp\PolicyController;
use App\Http\Controllers\Erp\PlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('c/{firmSlug}/app')->name('erp.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'attempt'])
        ->middleware('throttle:5,1')->name('login.attempt');

    Route::get('/forgot-password', [PasswordController::class, 'forgot'])->name('password.forgot');
    Route::post('/forgot-password', [PasswordController::class, 'send'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'resetForm'])->name('password.reset.form');
    Route::post('/reset-password', [PasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.reset');

    Route::get('/activate/{token}', [ActivationController::class, 'form'])->name('activate.form');
    Route::post('/activate', [ActivationController::class, 'accept'])
        ->middleware('throttle:5,1')->name('activate.accept');

    Route::middleware(['auth', 'tenant.member'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check_in');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check_out');
        Route::post('/attendance/{sessionId}/correct', [AttendanceController::class, 'correction'])->name('attendance.correct');
        Route::get('/leave', [WorkflowController::class, 'leaves'])->name('leaves');
        Route::post('/leave', [WorkflowController::class, 'submitLeave'])->name('leaves.submit');
        Route::post('/leave/{id}/decision', [WorkflowController::class, 'decideLeave'])->name('leaves.decide');
        Route::get('/claims', [WorkflowController::class, 'claims'])->name('claims');
        Route::post('/claims', [WorkflowController::class, 'submitClaim'])->name('claims.submit');
        Route::get('/claims/{id}/receipt', [WorkflowController::class, 'receipt'])->name('claims.receipt');
        Route::post('/claims/{id}/decision', [WorkflowController::class, 'decideClaim'])->name('claims.decide');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');
        Route::post('/payroll', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{runId}/transition', [PayrollController::class, 'advance'])->name('payroll.advance');
        Route::get('/policies', [PolicyController::class, 'index'])->name('policies');
        Route::post('/policies/company/propose', [PolicyController::class, 'propose'])->name('policies.propose');
        Route::post('/policies/company/{id}/vote', [PolicyController::class, 'vote'])->name('policies.vote');
        Route::post('/policies/workspace/override', [PolicyController::class, 'override'])->name('policies.override');
        Route::get('/team', [TeamController::class, 'index'])->name('team');
        Route::post('/team/invite', [TeamController::class, 'invite'])->middleware('throttle:10,1')->name('team.invite');
        Route::post('/team/{id}/resend', [TeamController::class, 'resend'])->middleware('throttle:5,1')->name('team.resend');
        Route::post('/team/{id}/settings', [TeamController::class, 'updateStudent'])->name('team.update_student');
        Route::get('/notifications', [WorkflowController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}/read', [WorkflowController::class, 'readNotification'])->name('notifications.read');
    });
});
Route::prefix('platform/app')->name('erp.platform.')->group(function () {
    Route::get('/login', [PlatformController::class, 'login'])->name('login');
    Route::post('/login', [PlatformController::class, 'signIn'])
        ->middleware('throttle:5,1')->name('login.attempt');

    Route::middleware(['auth', 'platform.admin'])->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::post('/logout', [PlatformController::class, 'logout'])->name('logout');
        Route::post('/companies', [PlatformController::class, 'createFirm'])->name('companies.create');
        Route::post('/companies/{companyId}/partners', [PlatformController::class, 'addPartner'])->name('partners.create');
    });
});

