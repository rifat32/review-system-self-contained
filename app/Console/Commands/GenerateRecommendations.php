<?php
// app/Console/Commands/GenerateRecommendations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Models\Business;
use Carbon\Carbon;

class GenerateRecommendations extends Command
{
    protected $signature = 'recommendations:generate 
                           {--business= : Specific business ID}
                           {--all : All businesses}
                           {--force : Force regenerate}';

    protected $description = 'Generate recommendations from insights';

    private $logHandle;
    protected $insightAggregationService;
    protected $recommendationGeneratorService;

    public function __construct(
        InsightAggregationService $insightAggregationService,
        RecommendationGeneratorService $recommendationGeneratorService
    ) {
        parent::__construct();

        $this->insightAggregationService = $insightAggregationService;
        $this->recommendationGeneratorService = $recommendationGeneratorService;
    }

    public function handle()
    {
        $logFile = storage_path('logs/ai_processing.log');
        $this->logHandle = fopen($logFile, 'a');

        try {
            $this->fileWrite("\n" . str_repeat('=', 50) . "\n");
            $this->fileWrite("Generate Recommendations started at " . now() . "\n");

            $this->info('Starting recommendation generation...');
            $this->fileWrite("Starting recommendation generation...\n");

            $businesses = $this->getBusinessesToProcess();

            if ($businesses->isEmpty()) {
                $this->error('No businesses found.');
                $this->fileWrite("No businesses found.\n");
                return 1;
            }

            $msg = "Processing {$businesses->count()} business(es)";
            $this->info($msg);
            $this->fileWrite($msg . "\n");

            $results = ['success' => 0, 'failed' => 0];

            foreach ($businesses as $business) {
                try {
                    if (!$this->shouldProcess($business)) {
                        $this->line("○ Business {$business->id}: Skipped (recently processed)");
                        $this->fileWrite("○ Business {$business->id}: Skipped (recently processed)\n");
                        continue;
                    }

                    // 1. Aggregate insights (last 30 days)
                    $this->line("  → Aggregating insights...");
                    $this->fileWrite("  → Aggregating insights for Business {$business->id}...\n");

                    // Use the injected service instance
                    $aggResult = $this->insightAggregationService->aggregateReviewsForBusiness($business->id, 30);

                    if ($aggResult['insights_created'] === 0) {
                        $this->line("  → No new insights found");
                        $this->fileWrite("  → No new insights found\n");
                        continue;
                    }

                    // 2. Generate recommendations using injected service
                    $this->line("  → Generating recommendations...");
                    $this->fileWrite("  → Generating recommendations...\n");
                    $recs = $this->recommendationGeneratorService->generateFromInsights($business->id, 30);

                    // Update last processed
                    $business->update(['last_recommendation_at' => now()]);

                    $successMsg = "✓ Business {$business->id}: Created " . count($recs) . " recommendations";
                    $this->info($successMsg);
                    $this->fileWrite($successMsg . "\n");

                    $results['success']++;
                } catch (\Exception $e) {
                    $errorMsg = "✗ Business {$business->id}: {$e->getMessage()}";
                    $this->error($errorMsg);
                    $this->fileWrite($errorMsg . "\n");

                    // Also keep standard logging
                    \Log::error('Recommendation generation failed', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['failed']++;
                }
            }

            $endMsg = "\nComplete: {$results['success']} success, {$results['failed']} failed";
            $this->info($endMsg);
            $this->fileWrite($endMsg . "\n");

            return $results['failed'] > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->fileWrite("FATAL ERROR: " . $e->getMessage() . "\n");
            return 1;
        } finally {
            if ($this->logHandle) {
                fclose($this->logHandle);
            }
        }
    }

    private function getBusinessesToProcess()
    {
        $query = Business::where('is_active', true);

        if ($this->option('business')) {
            $query->where('id', $this->option('business'));
        }

        if (!$this->option('business') && !$this->option('all')) {
            // Default: process businesses not updated in last 12 hours
            $query->where(function ($q) {
                $q->whereNull('last_recommendation_at')
                    ->orWhere('last_recommendation_at', '<', Carbon::now()->subHours(12));
            })->limit(50); // Process max 50 per run
        }

        return $query->get();
    }

    private function shouldProcess($business)
    {
        if ($this->option('force'))
            return true;

        // Skip if processed in last 6 hours
        if (
            $business->last_recommendation_at &&
            $business->last_recommendation_at->gt(Carbon::now()->subHours(6))
        ) {
            return false;
        }

        return true;
    }

    private function fileWrite($message)
    {
        if ($this->logHandle) {
            fwrite($this->logHandle, $message);
        }
    }
}
