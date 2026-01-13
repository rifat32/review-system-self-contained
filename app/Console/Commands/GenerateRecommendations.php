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

    public function handle()
    {
        $this->info('Starting recommendation generation...');

        $businesses = $this->getBusinessesToProcess();

        if ($businesses->isEmpty()) {
            $this->error('No businesses found.');
            return 1;
        }

        $this->info("Processing {$businesses->count()} business(es)");

        $results = ['success' => 0, 'failed' => 0];

        foreach ($businesses as $business) {
            try {
                if (!$this->shouldProcess($business)) {
                    $this->line("○ Business {$business->id}: Skipped (recently processed)");
                    continue;
                }

                // 1. Aggregate insights (last 30 days)
                $this->line("  → Aggregating insights...");
                $aggResult = InsightAggregationService::aggregateReviewsForBusiness($business->id, 30);

                if ($aggResult['insights_created'] === 0) {
                    $this->line("  → No new insights found");
                    continue;
                }

                // 2. Generate recommendations
                $this->line("  → Generating recommendations...");
                $recs = RecommendationGeneratorService::generateFromInsights($business->id, 30);

                // Update last processed
                $business->update(['last_recommendation_at' => now()]);

                $this->info("✓ Business {$business->id}: Created " . count($recs) . " recommendations");
                $results['success']++;

            } catch (\Exception $e) {
                $this->error("✗ Business {$business->id}: {$e->getMessage()}");
                \Log::error('Recommendation generation failed', [
                    'business_id' => $business->id,
                    'error' => $e->getMessage()
                ]);
                $results['failed']++;
            }
        }

        $this->info("\nComplete: {$results['success']} success, {$results['failed']} failed");
        return $results['failed'] > 0 ? 1 : 0;
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