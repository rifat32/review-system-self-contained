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

    public function handle()
    {
        $days = $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        
        // Recommendations older than X days
        $recCount = Recommendation::where('created_at', '<', $cutoff)->count();
        
        // Insights older than X days
        $insightCount = InsightRecord::where('time_window_end', '<', $cutoff)->count();
        
        $this->info("Found {$recCount} recommendations and {$insightCount} insights older than {$days} days");
        
        if (!$this->option('force')) {
            $this->warn('Dry run. Use --force to delete.');
            return 0;
        }
        
        if ($recCount > 0) {
            Recommendation::where('created_at', '<', $cutoff)->delete();
            $this->info("Deleted {$recCount} recommendations");
        }
        
        if ($insightCount > 0) {
            InsightRecord::where('time_window_end', '<', $cutoff)->delete();
            $this->info("Deleted {$insightCount} insights");
        }
        
        return 0;
    }
}