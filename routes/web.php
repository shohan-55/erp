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

// The public prototype is accessible only in LOCAL and TEST environments.
// It must never be mistaken for real production authentication or permissions.
if (in_array(app()->environment(), ['local', 'testing'], true)) {
    Route::get('/', AppShell::class)->name('home');
    Route::get('/dashboard', AppShell::class)->name('dashboard');
    Route::get('/platform/dashboard-preview', DashboardPreview::class)->name('platform.dashboard.preview');

    Route::prefix('platform')->group(function () {
        Route::get('/login', PlatformLogin::class)->name('platform.login');
        Route::get('/forgot-password', ForgotPassword::class)->name('platform.forgot-password');
        Route::get('/reset-password', ResetPassword::class)->name('platform.reset-password');
    });

    Route::get('/c/{firmSlug}/preview/{previewRole?}/{section?}', PracticePreview::class)
        ->where('previewRole', 'partner|manager|student')
        ->where('section', 'dashboard|attendance|leave|conveyance|payroll|notifications|policies|settings')
        ->name('firm.practice.preview');

    Route::prefix('c/{firmSlug}')->group(function () {
        Route::get('/login', FirmLogin::class)->name('firm.login');
        Route::get('/forgot-password', ForgotPassword::class)->name('firm.forgot-password');
        Route::get('/reset-password', ResetPassword::class)->name('firm.reset-password');
        Route::get('/activate', AccountActivation::class)->name('firm.activate');
    });
} else {
    Route::view('/', 'erp.welcome')->name('home');
    Route::get('/c/{firmSlug}/login', fn (string $firmSlug) =>
        redirect()->route('erp.login', ['firmSlug' => $firmSlug])
    )->name('firm.login');
}

require __DIR__.'/erp.php';