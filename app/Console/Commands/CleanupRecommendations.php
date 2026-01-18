<?php
// app/Console/Commands/CleanupRecommendations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Recommendation;
use App\Models\InsightRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupRecommendations extends Command
{
    protected $signature = 'recommendations:cleanup
                           {--days=90 : Delete older than X days}
                           {--force : Actually delete}';

    protected $description = 'Cleanup old recommendations';



    public function handle()
    {
        try {
            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            Log::channel('daily')->info("Cleanup Recommendations started at " . now());

            $days = $this->option('days');
            $cutoff = Carbon::now()->subDays($days);

            // Recommendations older than X days
            $recCount = Recommendation::where('created_at', '<', $cutoff)->count();

            // Insights older than X days
            $insightCount = InsightRecord::where('time_window_end', '<', $cutoff)->count();

            $msg = "Found {$recCount} recommendations and {$insightCount} insights older than {$days} days";
            $this->info($msg);
            Log::channel('daily')->info($msg);

            if (!$this->option('force')) {
                $this->warn('Dry run. Use --force to delete.');
                Log::channel('daily')->info("Dry run. Use --force to delete.");
                return 0;
            }

            if ($recCount > 0) {
                Recommendation::where('created_at', '<', $cutoff)->delete();
                $this->info("Deleted {$recCount} recommendations");
                Log::channel('daily')->info("Deleted {$recCount} recommendations");
            }

            if ($insightCount > 0) {
                InsightRecord::where('time_window_end', '<', $cutoff)->delete();
                $this->info("Deleted {$insightCount} insights");
                Log::channel('daily')->info("Deleted {$insightCount} insights");
            }

            Log::channel('daily')->info("Cleanup completed.");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("ERROR: " . $e->getMessage());
            return 1;
        }
    }
}
