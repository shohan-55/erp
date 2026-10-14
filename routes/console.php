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
