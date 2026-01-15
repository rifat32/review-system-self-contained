<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('guest_user_review_report:generate')->dailyAt('03:00');
        $schedule->command('user_review_report:generate')->dailyAt('03:00');

        // AI Rule Execution (Runs checks every minute, efficiently matches by frequency)
        $schedule->command('rules:execute-scheduled')->everyMinute();

        // Process New Reviews (Batch process every 5 minutes)
        $schedule->command('reviews:process')->everyFiveMinutes()->withoutOverlapping();

        // Generate Recommendations (Daily insights)
        $schedule->command('recommendations:generate')->dailyAt('03:00');

        // Cleanup Old Recommendations (Weekly maintenance)
        $schedule->command('recommendations:cleanup --days=90 --force')->weeklyOn(0, '04:00');

        // Regenerate Rule Explanations (Daily check for outdated/missing explanations)
        $schedule->command('rules:regenerate-explanations --missing-only --outdated-only')->dailyAt('05:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
