<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AppShell;

/*
|--------------------------------------------------------------------------
| Web Routes - RA&C ERP SaaS (Phase 01)
|--------------------------------------------------------------------------
*/

Route::get('/', AppShell::class)->name('home');
