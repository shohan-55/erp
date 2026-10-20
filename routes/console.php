<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generates a prior-month draft only. Never approves, pays, or locks payroll.
Schedule::command('erp:payroll:auto-draft')
    ->monthlyOn(1, '03:30')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();


// Delete expired encrypted browser GPS after the workspace's retention period.
Schedule::command('erp:attendance:prune-locations')
    ->dailyAt('02:15')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();


// Phase 16: opt-in workspace-scoped, exactly-once private engagement reminders.
// Requires the hosting scheduler to invoke php artisan schedule:run.
Schedule::command('erp:milestone-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Dhaka')
    ->withoutOverlapping();

 
// Phase 17: release expired upload reservations and clean staging parts only.
// Never deletes completed/archived/held customer objects.
Schedule::command('erp:vault:expire-staging')
    ->hourly()
    ->withoutOverlapping();
