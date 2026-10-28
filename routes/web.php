<?php

use Illuminate\Support\Facades\Auth;
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
| Web Routes - DhakaWala ERP SaaS
|--------------------------------------------------------------------------
| Official Multi-Tenant Enterprise Practice Management Platform
| Owned and Operated by DhakaWala (erp.dhakawala.com)
*/

// Default login redirect (resolves custom domain or points to DhakaWala ERP portal)
Route::get('/login', function (\Illuminate\Http\Request $request) {
    $customFirm = \App\Services\FirmRegistry::getByHost($request->getHost());
    if ($customFirm) {
        return redirect()->route('firm.login', ['firmSlug' => $customFirm['slug']]);
    }
    return redirect()->route('home');
})->name('login');

// Unified sign out route
Route::match(['get', 'post'], '/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('home');
})->name('logout');

// ─── Guest / Auth Recovery Routes ──────────────────────────────────────────

// DhakaWala Platform Super Admin Authentication
Route::prefix('platform')->middleware('throttle:20,1')->group(function () {
    Route::get('/login', PlatformLogin::class)->name('platform.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('platform.forgot-password');
    Route::get('/reset-password/{token?}', ResetPassword::class)->name('platform.reset-password');
});

// Multi-Firm CA Practice Authentication (/c/{firm-slug}/*)
Route::prefix('c/{firmSlug}')->middleware('throttle:20,1')->group(function () {
    Route::get('/login', FirmLogin::class)->name('firm.login');
    Route::get('/forgot-password', ForgotPassword::class)->name('firm.forgot-password');
    Route::get('/reset-password/{token?}', ResetPassword::class)->name('firm.reset-password');
    Route::get('/activate', AccountActivation::class)->name('firm.activate');
});

// Standard Laravel Password Broker Reset Route
Route::get('/reset-password/{token?}', ResetPassword::class)->name('password.reset');

// ─── Workspace & Portal Routes ─────────────────────────────────────────────

// Production DhakaWala ERP Portal & Login Console
Route::get('/', AppShell::class)->name('home');

// DhakaWala Platform Administration Hub
Route::get('/platform/dashboard', DashboardPreview::class)->name('platform.dashboard');
Route::get('/platform/dashboard-preview', DashboardPreview::class)->name('platform.dashboard.preview');

// CA Firm Practice Workspaces (firm-scoped)
Route::get('/c/{firmSlug}/dashboard', PracticePreview::class)->name('firm.practice.dashboard');
Route::get('/c/{firmSlug}/preview/{previewRole?}/{section?}', PracticePreview::class)
    ->where('previewRole', 'partner|manager|student')
    ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings')
    ->name('firm.practice.preview');

// ─── Authenticated Live Routes ─────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user && $user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }
        if ($user && $user->isFirmMember()) {
            if ($user->firm) {
                return redirect()->route('firm.practice.preview', [
                    'firmSlug'    => $user->firm->slug,
                    'previewRole' => $user->role,
                    'section'     => 'dashboard',
                ]);
            }
            return redirect()->route('home');
        }
        return redirect()->route('home');
    })->name('dashboard');
});
