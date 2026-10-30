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

// Escalate yesterday's unclosed check-ins so payroll and the Partner both see
// them (audit P1-3.4). Runs after the backup so a failure cannot block it.
Schedule::command('erp:flag-missed-checkouts')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer();

// Remind the platform operator about subscriptions that have lapsed or are
// about to, instead of letting tenants silently lose access.
Schedule::command('erp:subscription-expiry-reminder')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();
