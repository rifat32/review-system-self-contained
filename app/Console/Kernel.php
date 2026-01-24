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
        })->name('generate-user-report')->dailyAt('04:00');

        // 1. Process New Reviews (Data Preparation)
        // This is the entry point where raw reviews are analyzed by AI (sentiment, etc.)
        $schedule->call(function () {
            Artisan::call('reviews:process');
        })->name('process-reviews')->everyFiveMinutes()->withoutOverlapping();
        // 2. AI Rule Execution (Logic Evaluation)
        // Evaluates the processed reviews/data against defined business rules
        $schedule->call(function () {
            Artisan::call('rules:execute-scheduled');
        })->name('execute-rules')->everyMinute();
        // 3. Generate Recommendations (Outcome Generation)
        // Uses rule results and processed data to generate actionable insights
        $schedule->call(function () {
            Artisan::call('recommendations:generate');
        })->name('generate-recommendations')->dailyAt('03:00');
        // 4. Regenerate Rule Explanations (Asset Maintenance)
        // Updates the descriptive content for rules (short/detailed explanations) 
        $schedule->call(function () {
            Artisan::call('rules:regenerate-explanations', [
                '--missing-only' => true,
                '--outdated-only' => true
            ]);
        })->name('regenerate-explanations')->dailyAt('05:00');
        // 5. Cleanup Old Recommendations (Housekeeping)
        // Final cleanup of legacy data
        $schedule->call(function () {
            Artisan::call('recommendations:cleanup', [
                '--days' => 90,
                '--force' => true
            ]);
        })->name('cleanup-recommendations')->weeklyOn(0, '04:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
