<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:check-status')->everyMinute();
Schedule::command('payments:check-responsibility-status')->everyMinute();
Schedule::command('payments:complete-pay-back')->everyMinute();
Schedule::command('service-queue:process-extended-items')->hourly();
Schedule::command('inventory:process-main-outbox')->everyMinute();
Schedule::command('inventory:verify-forensic-audit --all')->dailyAt('02:15');
Schedule::command('visits:expire')->dailyAt('00:00');
Schedule::command('withdrawals:auto-reject-overdue')->hourly();
Schedule::command('service-charge:release-matured')->hourly();
