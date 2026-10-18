<?php

use App\Http\Controllers\Erp\AuthController;
use App\Http\Controllers\Erp\WorkspaceSwitchController;
use App\Http\Controllers\Erp\PortalController;
use App\Http\Controllers\Erp\AttendanceController;
use App\Http\Controllers\Erp\AttendanceReportController;
use App\Http\Controllers\Erp\AttendanceReconciliationController;
use App\Http\Controllers\Erp\AttendanceReviewController;
use App\Http\Controllers\Erp\WorkflowController;
use App\Http\Controllers\Erp\ExpenseManagementController;
use App\Http\Controllers\Erp\LeaveController;
use App\Http\Controllers\Erp\LeavePolicyController;
use App\Http\Controllers\Erp\PayrollController;
use App\Http\Controllers\Erp\PasswordController;
use App\Http\Controllers\Erp\ActivationController;
use App\Http\Controllers\Erp\TeamController;
use App\Http\Controllers\Erp\StudentPermissionController;
use App\Http\Controllers\Erp\PolicyController;
use App\Http\Controllers\Erp\StudentShiftController;
use App\Http\Controllers\Erp\PlatformController;
use App\Http\Controllers\Erp\PlatformManagementController;
use App\Http\Controllers\Erp\PlatformCustomDomainController;
use App\Http\Controllers\Erp\CustomDomainEntryController;
use App\Http\Controllers\Erp\MfaController;
use App\Http\Controllers\Erp\SecurityController;
use Illuminate\Support\Facades\Route;

// Authenticated first-factor accounts can enroll or complete a second factor.
Route::prefix('security/mfa')->middleware(['auth', 'mfa.host'])->name('erp.mfa.')->group(function () {
    Route::get('/setup', [MfaController::class, 'setup'])->name('setup');
    Route::post('/setup', [MfaController::class, 'confirmSetup'])->middleware('throttle:5,1')->name('confirm');
    Route::get('/challenge', [MfaController::class, 'challenge'])->name('challenge');
    Route::post('/challenge', [MfaController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
    Route::get('/recovery', [MfaController::class, 'recovery'])->name('recovery');
});

// Client-owned hostnames are allowed only after DNS + HTTPS approval and tenant matching.
Route::get('/login', [CustomDomainEntryController::class, 'login'])->name('erp.custom-domain.login');

Route::prefix('c/{firmSlug}/app')->middleware('tenant.host')->name('erp.')->group(function () {
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

    // Workspace choice is available to signed-in, MFA-verified users BEFORE
    // tenant.member resolves a selected membership.
    Route::middleware(['auth'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
    Route::middleware(['auth', 'mfa.verified'])->group(function () {
        Route::get('/workspaces', [WorkspaceSwitchController::class, 'index'])->name('workspaces.index');
        Route::post('/workspaces/select', [WorkspaceSwitchController::class, 'select'])
            ->middleware('throttle:10,1')->name('workspaces.select');
    });
    Route::middleware(['auth', 'tenant.member', 'mfa.verified', 'student.features'])->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/settings/security', [SecurityController::class, 'index'])->name('security');
        Route::post('/settings/security/mfa/disable', [SecurityController::class, 'disableMfa'])
            ->middleware('throttle:5,1')->name('security.mfa.disable');
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
        Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('attendance.report');
        Route::get('/attendance/reconcile', [AttendanceReconciliationController::class, 'index'])->name('attendance.reconcile');
        Route::get('/attendance/reviews', [AttendanceReviewController::class, 'index'])->name('attendance.reviews');
        Route::post('/attendance/reviews', [AttendanceReviewController::class, 'submit'])->middleware('throttle:10,1')->name('attendance.reviews.submit');
        Route::post('/attendance/reviews/{id}/decision', [AttendanceReviewController::class, 'decide'])->middleware('throttle:20,1')->name('attendance.reviews.decide');
        Route::get('/attendance/{sessionId}/location', [AttendanceController::class, 'location'])->name('attendance.location');
        Route::post('/attendance/location-policy', [AttendanceController::class, 'updateLocationPolicy'])
            ->middleware('throttle:5,1')->name('attendance.location_policy');
        Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check_in');
        Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check_out');
        Route::post('/attendance/{sessionId}/correct', [AttendanceController::class, 'correction'])->name('attendance.correct');
        Route::get('/leave', [LeaveController::class, 'index'])->name('leaves');
        Route::post('/leave', [LeaveController::class, 'submit'])->middleware('throttle:10,1')->name('leaves.submit');
        Route::post('/leave/{id}/decision', [LeaveController::class, 'decide'])->middleware('throttle:20,1')->name('leaves.decide');
        Route::post('/leave/{id}/withdraw', [LeaveController::class, 'withdraw'])->middleware('throttle:10,1')->name('leaves.withdraw');
        Route::post('/leave/{id}/cancel', [LeaveController::class, 'cancel'])->middleware('throttle:10,1')->name('leaves.cancel');
        Route::post('/leave/policies', [LeavePolicyController::class, 'save'])->middleware('throttle:5,1')->name('leaves.policy');
        Route::post('/leave/credits', [LeavePolicyController::class, 'credit'])->middleware('throttle:10,1')->name('leaves.credit');
        Route::get('/claims', [WorkflowController::class, 'claims'])->name('claims');
        Route::post('/claims', [WorkflowController::class, 'submitClaim'])->name('claims.submit');
        Route::get('/claims/{id}/receipt', [WorkflowController::class, 'receipt'])->name('claims.receipt');
        Route::post('/claims/{id}/decision', [WorkflowController::class, 'decideClaim'])->name('claims.decide');
        Route::post('/claims/{id}/withdraw', [ExpenseManagementController::class, 'withdraw'])
            ->middleware('throttle:10,1')->name('claims.withdraw');
        Route::post('/claims/policy', [ExpenseManagementController::class, 'savePolicy'])
            ->middleware('throttle:5,1')->name('claims.policy');
        Route::get('/claims/export', [ExpenseManagementController::class, 'export'])
            ->middleware('throttle:10,1')->name('claims.export');
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll');
        Route::post('/payroll', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{runId}/transition', [PayrollController::class, 'advance'])->name('payroll.advance');
        Route::get('/payroll/{runId}/payment-evidence', [PayrollController::class, 'paymentEvidence'])->name('payroll.evidence');
        Route::get('/policies', [PolicyController::class, 'index'])->name('policies');
        Route::post('/policies/company/propose', [PolicyController::class, 'propose'])->name('policies.propose');
        Route::post('/policies/company/{id}/vote', [PolicyController::class, 'vote'])->name('policies.vote');
        Route::post('/policies/workspace/override', [PolicyController::class, 'override'])->name('policies.override');
        Route::post('/policies/calendar/day', [PolicyController::class, 'dayOverride'])
            ->middleware('throttle:5,1')->name('policies.day_override');
        Route::post('/policies/calendar/day/{id}/decision', [PolicyController::class, 'reviewDayOverride'])
            ->middleware('throttle:5,1')->name('policies.day_review');
        Route::post('/policies/calendar/students', [StudentShiftController::class, 'store'])
            ->middleware('throttle:10,1')->name('policies.student_shifts');
        Route::post('/policies/calendar/students/{shiftId}/decision', [StudentShiftController::class, 'decide'])
            ->middleware('throttle:10,1')->name('policies.student_shift_decide');
        Route::get('/permissions/students', [StudentPermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions/students/{memberId}', [StudentPermissionController::class, 'updateStudent'])->middleware('throttle:30,1')->name('permissions.student');
        Route::post('/permissions/managers/{memberId}/delegation', [StudentPermissionController::class, 'delegateManager'])->middleware('throttle:15,1')->name('permissions.delegate');
        Route::get('/team', [TeamController::class, 'index'])->name('team');
        Route::post('/team/invite', [TeamController::class, 'invite'])->middleware('throttle:10,1')->name('team.invite');
        Route::post('/team/{id}/resend', [TeamController::class, 'resend'])->middleware('throttle:5,1')->name('team.resend');
        Route::post('/team/{id}/settings', [TeamController::class, 'updateStudent'])->name('team.update_student');
        Route::get('/notifications', [WorkflowController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/{id}/read', [WorkflowController::class, 'readNotification'])->name('notifications.read');
    });
});
Route::prefix('platform/app')->middleware('platform.host')->name('erp.platform.')->group(function () {
    Route::get('/login', [PlatformController::class, 'login'])->name('login');
    Route::post('/login', [PlatformController::class, 'signIn'])
        ->middleware('throttle:5,1')->name('login.attempt');

    Route::middleware(['auth', 'platform.admin'])->group(function () {
        Route::post('/logout', [PlatformController::class, 'logout'])->name('logout');
    });
    Route::middleware(['auth', 'platform.admin', 'mfa.verified'])->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::post('/companies', [PlatformController::class, 'createFirm'])->name('companies.create');
        Route::post('/companies/{companyId}/partners', [PlatformController::class, 'addPartner'])->name('partners.create');
        Route::post('/companies/{companyId}/custom-domain', [PlatformCustomDomainController::class, 'store'])->name('domains.store');
        Route::post('/companies/{companyId}/custom-domain/verify', [PlatformCustomDomainController::class, 'verify'])->middleware('throttle:5,1')->name('domains.verify');
        Route::post('/companies/{companyId}/custom-domain/tls', [PlatformCustomDomainController::class, 'approveTls'])->name('domains.tls');
        Route::post('/companies/{companyId}/custom-domain/activate', [PlatformCustomDomainController::class, 'activate'])->middleware('throttle:5,1')->name('domains.activate');
        Route::post('/companies/{companyId}/custom-domain/disable', [PlatformCustomDomainController::class, 'disable'])->name('domains.disable');
        Route::get('/companies/{companyId}', [PlatformManagementController::class, 'showCompany'])->name('companies.show');
        Route::patch('/companies/{companyId}', [PlatformManagementController::class, 'updateCompany'])->name('companies.update');
        Route::post('/companies/{companyId}/lifecycle', [PlatformManagementController::class, 'transitionCompany'])->name('companies.lifecycle');
        Route::post('/companies/{companyId}/members', [PlatformManagementController::class, 'inviteMember'])->middleware('throttle:10,1')->name('members.invite');
        Route::patch('/companies/{companyId}/members/{memberId}', [PlatformManagementController::class, 'updateMember'])->name('members.update');
        Route::post('/companies/{companyId}/members/{memberId}/lifecycle', [PlatformManagementController::class, 'transitionMember'])->name('members.lifecycle');
    });
});

