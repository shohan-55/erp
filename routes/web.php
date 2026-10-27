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
| Phase 03: Real Authentication, RBAC & Firm-scoped Access Control
*/

// Default login redirect to primary practice node
Route::get('/login', function () {
    return redirect()->route('firm.login', ['firmSlug' => 'rac']);
})->name('login');

// ─── Guest / Auth Recovery Routes ──────────────────────────────────────────

// Phase 02: Platform Super Admin Authentication
Route::prefix('platform')->middleware('throttle:20,1')->group(function () {
    Route::get('/login', PlatformLogin::class)->name('platform.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('platform.forgot-password');
    Route::get('/reset-password/{token?}', ResetPassword::class)->name('platform.reset-password');
});

// Phase 02: Multi-Firm CA Practice Authentication (/c/{firm-slug}/*)
Route::prefix('c/{firmSlug}')->middleware('throttle:20,1')->group(function () {
    Route::get('/login', FirmLogin::class)->name('firm.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('firm.forgot-password');
    Route::get('/reset-password/{token?}', ResetPassword::class)->name('firm.reset-password');
    Route::get('/activate', AccountActivation::class)->name('firm.activate');
});

// Standard Laravel Password Broker Reset Route
Route::get('/reset-password/{token?}', ResetPassword::class)->name('password.reset');

// ─── Workspace & Preview Routes ────────────────────────────────────────────

// Phase 01: Practice Workspace Shell
Route::get('/', AppShell::class)->name('home');

// Phase 03: Platform Dashboard Preview / Prototype
Route::get('/platform/dashboard-preview', DashboardPreview::class)
    ->name('platform.dashboard.preview');

// Phase 04–10: Practice Module Previews (firm-scoped)
Route::get('/c/{firmSlug}/preview/{previewRole?}/{section?}', PracticePreview::class)
    ->where('previewRole', 'partner|manager|student')
    ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings')
    ->name('firm.practice.preview');

// ─── Authenticated Live Routes ─────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', AppShell::class)->name('dashboard');
});
