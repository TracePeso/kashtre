<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // Check payment statuses every minute
        $schedule->command('payments:check-status')->everyMinute();
        
        // Check payment responsibility (deductible/copay) mobile money payment statuses every minute
        $schedule->command('payments:check-responsibility-status')->everyMinute();
        
        // Complete pay-back payments every minute
        $schedule->command('payments:complete-pay-back')->everyMinute();
        
        // Process extended service queue items every hour
        $schedule->command('service-queue:process-extended-items')->hourly();
        
        $schedule->command('inventory:process-main-outbox')->everyMinute();

        // Expire visit IDs at midnight, extend for clients with pending/in-progress items
        $schedule->command('visits:expire')->dailyAt('00:00');
        
        // Check and auto-reject overdue withdrawal requests every hour
        $schedule->command('withdrawals:auto-reject-overdue')->hourly();

        // Move matured service charges from entity account to Kashtre
        $schedule->command('service-charge:release-matured')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
