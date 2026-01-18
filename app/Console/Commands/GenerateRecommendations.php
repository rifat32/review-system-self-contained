<?php
// app/Console/Commands/GenerateRecommendations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateRecommendations extends Command
{
    protected $signature = 'recommendations:generate 
                           {--business= : Specific business ID}
                           {--all : All businesses}
                           {--force : Force regenerate}';

    protected $description = 'Generate recommendations from insights';


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
        try {
            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            Log::channel('daily')->info("Generate Recommendations started at " . now());

            $this->info('Starting recommendation generation...');
            Log::channel('daily')->info("Starting recommendation generation...");

            $businesses = $this->getBusinessesToProcess();

            if ($businesses->isEmpty()) {
                $this->error('No businesses found.');
                Log::channel('daily')->info("No businesses found.");
                return 1;
            }

            $msg = "Processing {$businesses->count()} business(es)";
            $this->info($msg);
            Log::channel('daily')->info($msg);

            $results = ['success' => 0, 'failed' => 0];

            foreach ($businesses as $business) {
                try {
                    if (!$this->shouldProcess($business)) {
                        $this->line("○ Business {$business->id}: Skipped (recently processed)");
                        Log::channel('daily')->info("○ Business {$business->id}: Skipped (recently processed)");
                        continue;
                    }

                    // 1. Aggregate insights (last 30 days)
                    $this->line("  → Aggregating insights...");
                    Log::channel('daily')->info("  → Aggregating insights for Business {$business->id}...");

                    // Use the injected service instance
                    $aggResult = $this->insightAggregationService->aggregateReviewsForBusiness($business->id, 30);

                    if ($aggResult['insights_created'] === 0) {
                        $this->line("  → No new insights found");
                        Log::channel('daily')->info("  → No new insights found");
                        continue;
                    }

                    // 2. Generate recommendations using injected service
                    $this->line("  → Generating recommendations...");
                    Log::channel('daily')->info("  → Generating recommendations...");
                    $recs = $this->recommendationGeneratorService->generateFromInsights($business->id, 30);

                    // Update last processed
                    $business->update(['last_recommendation_at' => now()]);

                    $successMsg = "✓ Business {$business->id}: Created " . count($recs) . " recommendations";
                    $this->info($successMsg);
                    Log::channel('daily')->info($successMsg);

                    $results['success']++;
                } catch (\Exception $e) {
                    $errorMsg = "✗ Business {$business->id}: {$e->getMessage()}";
                    $this->error($errorMsg);
                    Log::channel('daily')->info($errorMsg);

                    // Also keep standard logging
                    Log::error('Recommendation generation failed', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['failed']++;
                }
            }

            $endMsg = "\nComplete: {$results['success']} success, {$results['failed']} failed";
            $this->info($endMsg);
            Log::channel('daily')->info($endMsg);

            return $results['failed'] > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("FATAL ERROR: " . $e->getMessage());
            return 1;
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
}
