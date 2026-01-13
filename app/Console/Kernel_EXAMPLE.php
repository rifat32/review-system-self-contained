<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\ExecuteScheduledRulesJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // ==================== AI RULE EXECUTION SCHEDULE ====================

        // Execute hourly rules every hour
        $schedule->job(new ExecuteScheduledRulesJob('hourly'))
            ->hourly()
            ->name('execute-hourly-rules')
            ->withoutOverlapping()
            ->onOneServer();

        // Execute daily rules every day at 2:00 AM
        $schedule->job(new ExecuteScheduledRulesJob('daily'))
            ->dailyAt('02:00')
            ->name('execute-daily-rules')
            ->withoutOverlapping()
            ->onOneServer();

        // Execute weekly rules every Monday at 2:00 AM
        $schedule->job(new ExecuteScheduledRulesJob('weekly'))
            ->weeklyOn(1, '02:00')
            ->name('execute-weekly-rules')
            ->withoutOverlapping()
            ->onOneServer();

        // ==================== EXISTING SCHEDULES (if any) ====================
        // Add your existing cron jobs below

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
