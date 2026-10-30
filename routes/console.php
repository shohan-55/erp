<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| P0-7 in docs/PRODUCTION_READINESS_AUDIT_2026-10-30.md: the platform had no
| scheduled job at all, so nothing ever produced a backup. Run `php artisan
| schedule:work` (or the cron entry) for this to fire.
|
*/
Schedule::command('erp:backup')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer();
