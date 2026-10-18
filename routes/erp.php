<?php

use App\Http\Controllers\Erp\AuthController;
use App\Http\Controllers\Erp\WorkspaceSwitchController;
use App\Http\Controllers\Erp\PortalController;
use App\Http\Controllers\Erp\CorporateCalendarController;
use App\Http\Controllers\Erp\AttendanceController;
use App\Http\Controllers\Erp\AttendanceReportController;
use App\Http\Controllers\Erp\AttendanceReconciliationController;
use App\Http\Controllers\Erp\AttendanceReviewController;
use App\Http\Controllers\Erp\WorkflowController;
use App\Http\Controllers\Erp\ExpenseManagementController;
use App\Http\Controllers\Erp\LeaveController;
use App\Http\Controllers\Erp\LeavePolicyController;
use App\Http\Controllers\Erp\PayrollController;
use App\Http\Controllers\Erp\PayrollAdjustmentController;
use App\Http\Controllers\Erp\PayrollStatementController;
use App\Http\Controllers\Erp\PasswordController;
use App\Http\Controllers\Erp\ActivationController;
use App\Http\Controllers\Erp\TeamController;
use App\Http\Controllers\Erp\StaffController;
use App\Http\Controllers\Erp\StaffTransferController;
use App\Http\Controllers\Erp\EngagementController;
use App\Http\Controllers\Erp\AuditTimesheetController;
use App\Http\Controllers\Erp\PracticeReportController;
use App\Http\Controllers\Erp\PracticeNoticeController;
use App\Http\Controllers\Erp\PracticeDocumentController;
use App\Http\Controllers\Erp\BrandStudioController;
use App\Http\Controllers\Erp\OfficialDocumentController;
use App\Http\Controllers\Erp\OfficialTemplateController;
use App\Http\Controllers\Erp\StaffCertificateController;
use App\Http\Controllers\Erp\StudentPermissionController;
use App\Http\Controllers\Erp\PolicyController;
use App\Http\Controllers\Erp\AnnualHolidayController;
use App\Http\Controllers\Erp\HrPolicyController;
use App\Http\Controllers\Erp\StudentShiftController;
use App\Http\Controllers\Erp\PlatformController;
use App\Http\Controllers\Erp\PlatformBillingController;
use App\Http\Controllers\Erp\TenantSubscriptionController;
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
        Route::get('/calendar', [CorporateCalendarController::class, 'index'])->name('calendar.index');
        Route::get('/calendar/export', [CorporateCalendarController::class, 'export'])->middleware('throttle:20,1')->name('calendar.export');
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
        Route::get('/attendance/clock', [AttendanceController::class, 'clock'])->middleware('throttle:30,1')->name('attendance.clock');
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
        Route::post('/payroll/{runId}/adjustments', [PayrollAdjustmentController::class, 'create'])
            ->middleware('throttle:10,1')->name('payroll.adjustments.create');
        Route::post('/payroll/{runId}/adjustments/{adjustmentId}/void', [PayrollAdjustmentController::class, 'voidAdjustment'])
            ->middleware('throttle:10,1')->name('payroll.adjustments.void');
        Route::get('/payroll/{runId}/payslips/{memberId}', [PayrollStatementController::class, 'payslip'])
            ->name('payroll.payslip');
        Route::get('/payroll/{runId}/register', [PayrollStatementController::class, 'register'])
            ->middleware('throttle:10,1')->name('payroll.register');
        Route::get('/engagements', [EngagementController::class, 'index'])->name('engagements');
        Route::post('/engagements/clients', [EngagementController::class, 'client'])
            ->middleware('throttle:10,1')->name('engagements.clients.create');
        Route::post('/engagements/clients/{id}/update', [EngagementController::class, 'client'])
            ->middleware('throttle:10,1')->name('engagements.clients.update');
        Route::post('/engagements/clients/{id}/archive', [EngagementController::class, 'archiveClient'])
            ->middleware('throttle:5,1')->name('engagements.clients.archive');
        Route::post('/engagements', [EngagementController::class, 'engagement'])
            ->middleware('throttle:10,1')->name('engagements.create');
        Route::post('/engagements/{id}/update', [EngagementController::class, 'engagement'])
            ->middleware('throttle:10,1')->name('engagements.update');
        Route::post('/engagements/{id}/close', [EngagementController::class, 'close'])
            ->middleware('throttle:5,1')->name('engagements.close');
        Route::post('/engagements/{id}/assign', [EngagementController::class, 'assign'])
            ->middleware('throttle:15,1')->name('engagements.assign');
        Route::get('/timesheets', [AuditTimesheetController::class, 'index'])->name('timesheets');
        Route::post('/timesheets', [AuditTimesheetController::class, 'submit'])
            ->middleware('throttle:20,1')->name('timesheets.submit');
        Route::post('/timesheets/{id}/decision', [AuditTimesheetController::class, 'decide'])
            ->middleware('throttle:20,1')->name('timesheets.decide');
        Route::post('/timesheets/{id}/withdraw', [AuditTimesheetController::class, 'withdraw'])
            ->middleware('throttle:10,1')->name('timesheets.withdraw');
        Route::get('/timesheets/export', [AuditTimesheetController::class, 'export'])
            ->middleware('throttle:10,1')->name('timesheets.export');
        Route::get('/subscription', [TenantSubscriptionController::class, 'index'])->name('subscription');
        Route::get('/reports/practice', [PracticeReportController::class, 'index'])->name('reports.practice');
        Route::get('/reports/practice/export', [PracticeReportController::class, 'export'])
            ->middleware('throttle:10,1')->name('reports.practice.export');
        Route::get('/notices', [PracticeNoticeController::class, 'index'])->name('notices');
        Route::post('/notices', [PracticeNoticeController::class, 'publish'])
            ->middleware('throttle:5,1')->name('notices.publish');
        Route::post('/notifications/read-all', [PracticeNoticeController::class, 'markAllRead'])
            ->middleware('throttle:10,1')->name('notifications.read_all');
        Route::get('/settings/brand-studio', [BrandStudioController::class,'index'])->name('brand.index');
        Route::post('/settings/brand-studio', [BrandStudioController::class,'save'])->middleware('throttle:6,1')->name('brand.save');
        Route::post('/settings/brand-studio/assets', [BrandStudioController::class,'upload'])->middleware('throttle:6,1')->name('brand.upload');
        Route::get('/settings/brand-studio/assets/{id}', [BrandStudioController::class,'asset'])->middleware('throttle:30,1')->name('brand.asset');
        Route::get('/settings/document-templates', [OfficialTemplateController::class,'index'])->name('official_templates.index');
        Route::post('/settings/document-templates', [OfficialTemplateController::class,'save'])->middleware('throttle:5,1')->name('official_templates.save');
        Route::get('/documents/certificates', [StaffCertificateController::class,'index'])->name('staff_certificates.index');
        Route::post('/documents/certificates/{memberId}', [StaffCertificateController::class,'issue'])->middleware('throttle:6,1')->name('staff_certificates.issue');
        Route::get('/documents/certificates/{id}/print', [StaffCertificateController::class,'print'])->middleware('throttle:30,1')->name('staff_certificates.print');
        Route::get('/documents/certificates/{id}/word', [StaffCertificateController::class,'word'])->middleware('throttle:30,1')->name('staff_certificates.word');
        Route::get('/documents/approved-leave/{id}/print', [OfficialDocumentController::class,'print'])->middleware('throttle:30,1')->name('official.leave.print');
        Route::get('/documents/approved-leave/{id}/word', [OfficialDocumentController::class,'word'])->middleware('throttle:30,1')->name('official.leave.word');
        Route::get('/documents/official-register/export', [OfficialDocumentController::class,'register'])->middleware('throttle:10,1')->name('official.register.export');
        Route::get('/documents', [PracticeDocumentController::class, 'index'])->name('documents');
        Route::post('/documents', [PracticeDocumentController::class, 'upload'])
            ->middleware('throttle:10,1')->name('documents.upload');
        Route::get('/documents/{id}/download', [PracticeDocumentController::class, 'download'])
            ->middleware('throttle:30,1')->name('documents.download');
        Route::post('/documents/{id}/archive', [PracticeDocumentController::class, 'archive'])
            ->middleware('throttle:10,1')->name('documents.archive');
        Route::get('/settings/hr-policies', [HrPolicyController::class, 'index'])->name('hr_policies.index');
        Route::post('/settings/hr-policies/permissions', [HrPolicyController::class, 'delegate'])->middleware('throttle:5,1')->name('hr_policies.delegate');
        Route::post('/settings/hr-policies/revisions', [HrPolicyController::class, 'propose'])->middleware('throttle:8,1')->name('hr_policies.propose');
        Route::post('/settings/hr-policies/revisions/{revisionId}/decision', [HrPolicyController::class, 'decide'])->middleware('throttle:12,1')->name('hr_policies.decide');
        Route::get('/settings/annual-holidays', [AnnualHolidayController::class, 'index'])->name('annual_holidays.index');
        Route::post('/settings/annual-holidays', [AnnualHolidayController::class, 'submit'])->middleware('throttle:5,1')->name('annual_holidays.submit');
        Route::post('/settings/annual-holidays/{planId}/decision', [AnnualHolidayController::class, 'decide'])->middleware('throttle:10,1')->name('annual_holidays.decide');
        Route::post('/settings/annual-holidays/branding', [AnnualHolidayController::class, 'branding'])->middleware('throttle:5,1')->name('annual_holidays.branding');
        Route::get('/policies', [PolicyController::class, 'index'])->name('policies');
        Route::post('/policies/company/propose', [PolicyController::class, 'propose'])->name('policies.propose');
        Route::post('/policies/company/{id}/vote', [PolicyController::class, 'vote'])->name('policies.vote');
        Route::post('/policies/workspace/override', [PolicyController::class, 'override'])->name('policies.override');
        Route::post('/policies/attendance-clock', [PolicyController::class, 'attendanceClock'])->middleware('throttle:5,1')->name('policies.attendance_clock');
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
        Route::get('/staff', [StaffController::class, 'index'])->name('staff');
        Route::get('/staff/my-records', [StaffController::class, 'myRecords'])->name('staff.my_records');
        Route::post('/staff/{id}/profile', [StaffController::class, 'profile'])
            ->middleware('throttle:10,1')->name('staff.profile');
        Route::post('/staff/{id}/compensation', [StaffController::class, 'compensation'])
            ->middleware('throttle:5,1')->name('staff.compensation');
        Route::post('/staff/{id}/lifecycle', [StaffController::class, 'lifecycle'])
            ->middleware('throttle:5,1')->name('staff.lifecycle');
        Route::post('/staff/{id}/documents', [StaffController::class, 'upload'])
            ->middleware('throttle:10,1')->name('staff.documents.upload');
        Route::get('/staff/documents/{documentId}', [StaffController::class, 'download'])
            ->name('staff.documents.download');
        Route::post('/staff/{memberId}/transfer', [StaffTransferController::class, 'request'])
            ->middleware('throttle:5,1')->name('staff.transfer.request');
        Route::post('/staff/transfers/{id}/decision', [StaffTransferController::class, 'decide'])
            ->middleware('throttle:5,1')->name('staff.transfer.decide');
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
        Route::get('/billing', [PlatformBillingController::class, 'index'])->name('billing');
        Route::post('/billing/plans', [PlatformBillingController::class, 'storePlan'])
            ->middleware('throttle:10,1')->name('billing.plans.create');
        Route::post('/billing/plans/{planId}/availability', [PlatformBillingController::class, 'availability'])
            ->middleware('throttle:10,1')->name('billing.plans.availability');
        Route::get('/companies/{companyId}/billing', [PlatformBillingController::class, 'company'])
            ->name('billing.company');
        Route::post('/companies/{companyId}/subscription', [PlatformBillingController::class, 'subscribe'])
            ->middleware('throttle:10,1')->name('billing.subscribe');
        Route::post('/companies/{companyId}/invoices', [PlatformBillingController::class, 'invoice'])
            ->middleware('throttle:10,1')->name('billing.invoice');
        Route::post('/companies/{companyId}/invoices/{invoiceId}/payments', [PlatformBillingController::class, 'payment'])
            ->middleware('throttle:10,1')->name('billing.payment');
        Route::post('/companies/{companyId}/payments/{paymentId}/decide', [PlatformBillingController::class, 'decide'])
            ->middleware('throttle:10,1')->name('billing.payment.decide');
        Route::get('/companies/{companyId}', [PlatformManagementController::class, 'showCompany'])->name('companies.show');
        Route::patch('/companies/{companyId}', [PlatformManagementController::class, 'updateCompany'])->name('companies.update');
        Route::post('/companies/{companyId}/lifecycle', [PlatformManagementController::class, 'transitionCompany'])->name('companies.lifecycle');
        Route::post('/companies/{companyId}/members', [PlatformManagementController::class, 'inviteMember'])->middleware('throttle:10,1')->name('members.invite');
        Route::patch('/companies/{companyId}/members/{memberId}', [PlatformManagementController::class, 'updateMember'])->name('members.update');
        Route::post('/companies/{companyId}/members/{memberId}/lifecycle', [PlatformManagementController::class, 'transitionMember'])->name('members.lifecycle');
    });
});

