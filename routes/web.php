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

// Health / readiness probe (audit P2-19).
// `/up` only proves PHP booted; this also proves the database answers and the
// storage disk is writable — the two things that actually break on cPanel.
// It exposes no tenant data and no configuration values.
Route::get('/health', function () {
    $checks = [
        'database' => false,
        'storage'  => false,
        'scheduler' => false,
    ];

    try {
        \Illuminate\Support\Facades\DB::select('select 1');
        $checks['database'] = true;
    } catch (\Throwable) {
    }

    try {
        $probe = 'health/probe-' . \Illuminate\Support\Str::random(8) . '.txt';
        \Illuminate\Support\Facades\Storage::disk('local')->put($probe, (string) now()->timestamp);
        $checks['storage'] = \Illuminate\Support\Facades\Storage::disk('local')->exists($probe);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($probe);
    } catch (\Throwable) {
    }

    // Did the scheduler run in the last 36 hours? A silently dead cron is the
    // most common cPanel failure and it stops backups without any error ever
    // reaching the application log.
    try {
        $backups = \Illuminate\Support\Facades\Storage::disk('local')->files('backups/db');
        $latest = collect($backups)->map(fn ($f) => \Illuminate\Support\Facades\Storage::disk('local')->lastModified($f))->max();
        $checks['scheduler'] = $latest !== null && $latest > now()->subHours(36)->getTimestamp();
    } catch (\Throwable) {
    }

    $healthy = $checks['database'] && $checks['storage'];

    return response()->json([
        'status'     => $healthy ? 'ok' : 'degraded',
        'checks'     => $checks,
        'checked_at' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->name('health');

// Unified sign out route.
// POST only: a GET logout can be triggered by any third-party page (CSRF),
// which the audit flagged as P1-3.2. Every link is now a CSRF-protected form.
Route::post('/logout', function (\Illuminate\Http\Request $request) {
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
    // Private disk; `firm.member` has already proved the caller belongs to
    // this firm, so a slug from another tenant is a 403 before we get here.
    $path = \App\Support\FirmFile::locate('conveyance-receipts', $firmSlug, $filename);
    if (! $path) {
        abort(404, "Voucher receipt file not found.");
    }
    return response()->file($path);
})->middleware(['auth', 'firm.member'])->name('firm.voucher.view');

// Secure cPanel-compatible Avatar File Viewer (works even without storage:link symlink)
Route::get('/c/{firmSlug}/avatar-file/{filename}', function (string $firmSlug, string $filename) {
    $path = \App\Support\FirmFile::locate('avatars', $firmSlug, $filename);
    if (! $path) {
        abort(404, "Avatar file not found.");
    }
    return response()->file($path);
})->middleware(['auth', 'firm.member'])->name('firm.avatar.view');
