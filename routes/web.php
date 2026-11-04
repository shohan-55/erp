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


// Dynamic Firm-Aware PWA Web Manifest & Service Worker Fallback Routes (cPanel-safe)
Route::get('/manifest.webmanifest', function () {
    return \App\Support\PwaAssets::renderManifest(null);
})->name('pwa.manifest');

Route::get('/c/{firmSlug}/manifest.webmanifest', function (string $firmSlug) {
    return \App\Support\PwaAssets::renderManifest($firmSlug);
})->name('firm.pwa.manifest');

// PWA Service Worker (with real network-first fetch handler satisfying Chrome installability)
Route::get('/sw.js', function () {
    $js = <<<'JS'
const CACHE_NAME = 'dhakawala-erp-pwa-v3';
const PRECACHE_ASSETS = [
    '/manifest.webmanifest',
    '/pwa-icon-192.png',
    '/pwa-icon-512.png',
    '/images/pwa-icon-192.png',
    '/images/pwa-icon-512.png'
];

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE_ASSETS).catch(function () {});
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key !== CACHE_NAME;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(function (networkResponse) {
                return networkResponse;
            })
            .catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || new Response('Offline', { status: 503, statusText: 'Offline' });
                });
            })
    );
});
JS;
    return response($js, 200, [
        'Content-Type'           => 'application/javascript; charset=utf-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control'          => 'no-cache, no-store, must-revalidate',
    ]);
})->name('pwa.sw');

// Guaranteed 100% Reachable PWA Icon Routes (Serves PNG from file or embedded fallback)
Route::get('/pwa-icon-192.png', function () {
    return response(\App\Support\PwaAssets::getIcon192(), 200, [
        'Content-Type'  => 'image/png',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->name('pwa.icon.192');

Route::get('/images/pwa-icon-192.png', function () {
    return response(\App\Support\PwaAssets::getIcon192(), 200, [
        'Content-Type'  => 'image/png',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
});

Route::get('/pwa-icon-512.png', function () {
    return response(\App\Support\PwaAssets::getIcon512(), 200, [
        'Content-Type'  => 'image/png',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->name('pwa.icon.512');

Route::get('/images/pwa-icon-512.png', function () {
    return response(\App\Support\PwaAssets::getIcon512(), 200, [
        'Content-Type'  => 'image/png',
        'Cache-Control' => 'public, max-age=31536000',
    ]);
});

Route::get('/health', function () {
    $checks = [
        'database' => false,
        'storage'  => false,
        'scheduler' => false,
    ];

    try {
        \Illuminate\Support\Facades\DB::select('select 1');
        $checks['database'] = true;
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Health check DB probe failed: ' . $e->getMessage());
    }

    try {
        $probe = 'health/probe-' . \Illuminate\Support\Str::random(8) . '.txt';
        \Illuminate\Support\Facades\Storage::disk('local')->put($probe, (string) now()->timestamp);
        $checks['storage'] = \Illuminate\Support\Facades\Storage::disk('local')->exists($probe);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($probe);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Health check storage probe failed: ' . $e->getMessage());
    }

    // Did the scheduler run in the last 36 hours? A silently dead cron is the
    // most common cPanel failure and it stops backups without any error ever
    // reaching the application log.
    try {
        $disk = (string) config('erp.backup_disk', 'local');
        $backups = \Illuminate\Support\Facades\Storage::disk($disk)->files('backups/db');
        $latest = collect($backups)->map(fn ($f) => \Illuminate\Support\Facades\Storage::disk($disk)->lastModified($f))->max();
        $checks['scheduler'] = $latest !== null && $latest > now()->subHours(36)->getTimestamp();
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Health check scheduler probe failed: ' . $e->getMessage());
    }

    $schedulerRequired = app()->environment('production') || (bool) config('erp.require_scheduler_health', false);
    $healthy = $checks['database'] && $checks['storage'] && (! $schedulerRequired || $checks['scheduler']);

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

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    if (Auth::check()) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if ($user && $user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }
        if ($user && $user->isFirmMember() && $user->firm) {
            return redirect()->route('firm.practice.dashboard', [
                'firmSlug' => $user->firm->slug,
            ]);
        }
        return redirect()->route('home');
    }
    $customFirm = \App\Services\FirmRegistry::getByHost($request->getHost());
    if ($customFirm) {
        return redirect()->route('firm.login', ['firmSlug' => $customFirm['slug']]);
    }
    $defaultFirm = \App\Services\FirmRegistry::defaultFirm();
    if (! empty($defaultFirm['slug'])) {
        return redirect()->route('firm.login', ['firmSlug' => $defaultFirm['slug']]);
    }
    return redirect()->route('home');
})->name('dashboard');


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
