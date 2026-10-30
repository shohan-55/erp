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

// Canonical Multi-Firm Practice Workspace Entry Route (/c/{firmSlug})
Route::get('/c/{firmSlug}', function (string $firmSlug) {
    $cleanSlug = strtolower(trim($firmSlug));
    $firm = \App\Services\FirmRegistry::get($cleanSlug);

    if (! $firm) {
        abort(404, 'Practice workspace not found.');
    }

    if (Auth::check()) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user->isFirmMember() && $user->belongsToFirm($cleanSlug)) {
            return redirect()->route('firm.practice.dashboard', [
                'firmSlug' => $cleanSlug,
            ]);
        }
        if ($user->isPlatformAdmin()) {
            return redirect()->route('firm.practice.dashboard', [
                'firmSlug' => $cleanSlug,
            ]);
        }
    }

    return redirect()->route('firm.login', ['firmSlug' => $cleanSlug]);
})->name('firm.workspace');

// Standard Laravel Password Broker Reset Route
Route::get('/reset-password/{token?}', ResetPassword::class)->name('password.reset');

// ─── Workspace & Portal Routes ─────────────────────────────────────────────

// Production DhakaWala ERP Portal & Login Console
Route::get('/', AppShell::class)->name('home');

// DhakaWala Platform Administration Hub (Protected Production Route + Preview Route)
Route::get('/platform/dashboard', DashboardPreview::class)
    ->middleware(['auth', 'role:platform_admin'])
    ->name('platform.dashboard');

Route::get('/platform/dashboard-preview', DashboardPreview::class)
    ->middleware(['production.preview'])
    ->name('platform.dashboard.preview');

// CA Firm Practice Workspaces (Protected Firm-Scoped Production Route + Preview Route)
Route::get('/c/{firmSlug}/dashboard/{section?}', PracticePreview::class)
    ->middleware(['auth', 'firm.member'])
    ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings|team')
    ->name('firm.practice.dashboard');

Route::get('/c/{firmSlug}/preview/{previewRole?}/{section?}', PracticePreview::class)
    ->middleware(['production.preview'])
    ->where('previewRole', 'partner|manager|student')
    ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings|team')
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
                return redirect()->route('firm.practice.dashboard', [
                    'firmSlug' => $user->firm->slug,
                ]);
            }
            return redirect()->route('home');
        }
        return redirect()->route('home');
    })->name('dashboard');
});


// Secure cPanel-compatible Conveyance Voucher File Viewer (works even without storage:link symlink)
Route::get('/c/{firmSlug}/voucher-file/{filename}', function (string $firmSlug, string $filename) {
    $safeSlug = strtolower(trim($firmSlug));
    $safeFile = basename($filename);
    $path = storage_path("app/public/conveyance-receipts/{$safeSlug}/{$safeFile}");
    if (! file_exists($path)) {
        abort(404, "Voucher receipt file not found.");
    }
    return response()->file($path);
})->middleware(['auth', 'firm.member'])->name('firm.voucher.view');

// Secure cPanel-compatible Avatar File Viewer (works even without storage:link symlink)
Route::get('/c/{firmSlug}/avatar-file/{filename}', function (string $firmSlug, string $filename) {
    $safeSlug = strtolower(trim($firmSlug));
    $safeFile = basename($filename);
    $path = storage_path("app/public/avatars/{$safeSlug}/{$safeFile}");
    if (! file_exists($path)) {
        abort(404, "Avatar file not found.");
    }
    return response()->file($path);
})->middleware(['auth', 'firm.member'])->name('firm.avatar.view');
