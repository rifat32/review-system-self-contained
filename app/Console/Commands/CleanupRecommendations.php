<?php
// app/Console/Commands/CleanupRecommendations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Recommendation;
use App\Models\InsightRecord;
use Carbon\Carbon;

class CleanupRecommendations extends Command
{
    protected $signature = 'recommendations:cleanup
                           {--days=90 : Delete older than X days}
                           {--force : Actually delete}';

    protected $description = 'Cleanup old recommendations';

    private $logHandle;

    public function handle()
    {
        $logFile = storage_path('logs/ai_processing.log');
        $this->logHandle = fopen($logFile, 'a');

        try {
            $days = $this->option('days');
            $cutoff = Carbon::now()->subDays($days);

            $this->fileWrite("\n" . str_repeat('=', 50) . "\n");
            $this->fileWrite("Cleanup Recommendations started at " . now() . "\n");

            // Recommendations older than X days
            $recCount = Recommendation::where('created_at', '<', $cutoff)->count();

            // Insights older than X days
            $insightCount = InsightRecord::where('time_window_end', '<', $cutoff)->count();

            $msg = "Found {$recCount} recommendations and {$insightCount} insights older than {$days} days";
            $this->info($msg);
            $this->fileWrite($msg . "\n");

            if (!$this->option('force')) {
                $this->warn('Dry run. Use --force to delete.');
                $this->fileWrite("Dry run. Use --force to delete.\n");
                return 0;
            }

            if ($recCount > 0) {
                Recommendation::where('created_at', '<', $cutoff)->delete();
                $this->info("Deleted {$recCount} recommendations");
                $this->fileWrite("Deleted {$recCount} recommendations\n");
            }

            if ($insightCount > 0) {
                InsightRecord::where('time_window_end', '<', $cutoff)->delete();
                $this->info("Deleted {$insightCount} insights");
                $this->fileWrite("Deleted {$insightCount} insights\n");
            }

            $this->fileWrite("Cleanup completed.\n");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->fileWrite("ERROR: " . $e->getMessage() . "\n");
            return 1;
        } finally {
            if ($this->logHandle) {
                fclose($this->logHandle);
            }
        }
    }

    private function fileWrite($message)
    {
        if ($this->logHandle) {
            fwrite($this->logHandle, $message);
        }
    }
}
