<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            Artisan::call('guest_user_review_report:generate');
        })->name('generate-guest-report')->dailyAt('03:00');

        $schedule->call(function () {
            Artisan::call('user_review_report:generate');
        })->name('generate-user-report')->dailyAt('03:00');

        // AI Rule Execution
        $schedule->call(function () {
            Artisan::call('rules:execute-scheduled');
        })->name('execute-rules')->everyMinute();

        // Process New Reviews
        $schedule->call(function () {
            Artisan::call('reviews:process');
        })->name('process-reviews')->everyFiveMinutes()->withoutOverlapping();

        // Generate Recommendations
        $schedule->call(function () {
            Artisan::call('recommendations:generate');
        })->name('generate-recommendations')->dailyAt('03:00');

        // Cleanup Old Recommendations
        $schedule->call(function () {
            Artisan::call('recommendations:cleanup', [
                '--days' => 90,
                '--force' => true
            ]);
        })->name('cleanup-recommendations')->weeklyOn(0, '04:00');

        // Regenerate Rule Explanations
        $schedule->call(function () {
            Artisan::call('rules:regenerate-explanations', [
                '--missing-only' => true,
                '--outdated-only' => true
            ]);
        })->name('regenerate-explanations')->dailyAt('05:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
