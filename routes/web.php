<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AppShell;
use App\Livewire\Auth\FirmLogin;
use App\Livewire\Auth\PlatformLogin;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\AccountActivation;
use App\Livewire\Platform\DashboardPreview;
use App\Livewire\Practice\PracticePreview;

/*
|--------------------------------------------------------------------------
| Web Routes - RA&C ERP SaaS
|--------------------------------------------------------------------------
| Phase 01: Core Workspace Shell & Component Library
| Phase 02: Enterprise Authentication & Account Recovery Experience
*/

// Phase 01: Practice Workspace Shell & Demonstration Dashboard
Route::get('/', AppShell::class)->name('home');
Route::get('/dashboard', AppShell::class)->name('dashboard');

// Phase 03: Public, read-only platform dashboard DESIGN PREVIEW only.
// Do not promote this route to a real admin endpoint without authentication & authorization.
Route::get('/platform/dashboard-preview', DashboardPreview::class)
    ->name('platform.dashboard.preview');

// Phase 02: Platform Super Admin Authentication Experience
Route::prefix('platform')->group(function () {
    Route::get('/login', PlatformLogin::class)->name('platform.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('platform.forgot-password');
    Route::get('/reset-password', ResetPassword::class)->name('platform.reset-password');
});

// Phases 04–10: Public, fictional practice UI PREVIEWS only.
// Never attach real attendance, accounts, payroll or tenant data here before RBAC is implemented.
Route::get('/c/{firmSlug}/preview/{previewRole?}/{section?}', PracticePreview::class)
    ->where('previewRole', 'partner|manager|student')
    ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings')
    ->name('firm.practice.preview');

// Phase 02: Multi-Firm CA Practice Authentication & Account Recovery (/c/{firm-slug}/*)
Route::prefix('c/{firmSlug}')->group(function () {
    Route::get('/login', FirmLogin::class)->name('firm.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('firm.forgot-password');
    Route::get('/reset-password', ResetPassword::class)->name('firm.reset-password');
    Route::get('/activate', AccountActivation::class)->name('firm.activate');
});

// Protected operational routes: separate from public fictional design previews.
require __DIR__.'/operational.php';
