<?php
// app/Console/Commands/GenerateRecommendations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Services\AIProcessor\OpenAIProcessorService;
use App\Models\Business;
use App\Models\ReviewNew;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class GenerateRecommendations extends Command
{
    protected $signature = 'recommendations:generate 
                           {--business= : Specific business ID}
                           {--all : All businesses}
                           {--force : Force regenerate}';

    protected $description = 'Generate recommendations from insights';


    protected $insightAggregationService;
    protected $recommendationGeneratorService;
    protected $openaiProcessorService;

    public function __construct(
        InsightAggregationService $insightAggregationService,
        RecommendationGeneratorService $recommendationGeneratorService,
        OpenAIProcessorService $openaiProcessorService
    ) {
        parent::__construct();

        $this->insightAggregationService = $insightAggregationService;
        $this->recommendationGeneratorService = $recommendationGeneratorService;
        $this->openaiProcessorService = $openaiProcessorService;
    }

    public function handle()
    {
        try {
            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            log_message([
                'message' => str_repeat('=', 50),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info("Generate Recommendations started at " . now());
            log_message([
                'message' => "Generate Recommendations started at " . now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $this->info('Starting recommendation generation...');
            Log::channel('daily')->info("Starting recommendation generation...");
            log_message([
                'message' => 'Starting recommendation generation...',
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $businesses = $this->getBusinessesToProcess();

            if ($businesses->isEmpty()) {
                $this->error('No businesses found.');
                Log::channel('daily')->info("No businesses found.");
                log_message([
                    'message' => 'No businesses found.',
                    'path' => __FILE__,
                    'other information' => 'AI Process Logging'
                ], 'ai_process.log');
                return 1;
            }

            $msg = "Processing {$businesses->count()} business(es)";
            $this->info($msg);
            Log::channel('daily')->info($msg);
            log_message([
                'message' => $msg,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');


            $results = ['success' => 0, 'failed' => 0];

            $progressBar = $this->output->createProgressBar($businesses->count());
            $progressBar->start();
            $this->newLine();

            foreach ($businesses as $business) {
                try {
                    if (!$this->shouldProcess($business)) {
                        $this->line("○ Business {$business->id}: Skipped (recently processed)");
                        Log::channel('daily')->info("○ Business {$business->id}: Skipped (recently processed)");
                        log_message([
                            'message' => "Business {$business->id}: Skipped (recently processed)",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                        continue;
                    }

                    // Check for new reviews for rolling AI insights
                    $newReviews = ReviewNew::where('business_id', $business->id)
                        ->where('is_ai_processed', true)
                        ->where('is_rolling_aggregated', false)
                        ->orderBy('id', 'asc')
                        ->limit(50)
                        ->get();

                    $isFallbackRun = false;
                    if ($newReviews->isEmpty() && $this->option('force')) {
                        // Fallback: Fetch last 5 processed reviews for forced runs when no new reviews exist
                        $newReviews = ReviewNew::where('business_id', $business->id)
                            ->where('is_ai_processed', true)
                            ->orderBy('id', 'desc')
                            ->limit(5)
                            ->get()
                            ->reverse();
                        $isFallbackRun = true;
                    }

                    $canRunRollingInsight = $newReviews->count() >= 5 || $this->option('force');

                    if (!$canRunRollingInsight) {
                        $this->line("  → Skipping rolling AI insights (Only {$newReviews->count()} new reviews, need at least 5)");
                        Log::channel('daily')->info("  → Skipping rolling AI insights for Business {$business->id} (Only {$newReviews->count()} new reviews)");
                    } else if ($newReviews->isNotEmpty()) {
                        // Proceed to run rolling AI update
                        $latestInsight = [];
                        foreach ($newReviews as $rev) {
                            $openaiData = $rev->openai_raw_response ?? [];
                            $oneLineSummary = $openaiData['summary']['one_line'] 
                                ?? ($openaiData['summary']['manager_summary'] 
                                ?? ($rev->comment ?? 'No comment provided.'));

                            $latestInsight[] = [
                                'review_id' => $rev->id,
                                'rating' => $rev->calculated_rating ?? 5,
                                'sentiment' => $rev->sentiment_label ?? 'neutral',
                                'emotion' => $openaiData['emotion']['primary'] ?? 'neutral',
                                'topics' => $openaiData['topics'] ?? [],
                                'positive_aspects' => $openaiData['positive_aspects'] ?? [],
                                'negative_aspects' => $openaiData['negative_aspects'] ?? [],
                                'issues' => $openaiData['issues'] ?? [],
                                'summary' => $oneLineSummary,
                                'confidence' => (float) ($openaiData['explainability']['confidence_score'] ?? ($openaiData['sentiment']['confidence'] ?? 0.85))
                            ];
                        }

                        $previousInsight = $business->rolling_ai_insight;
                        $currentVersion = is_array($previousInsight) ? ($previousInsight['version'] ?? 0) : 0;
                        $nextVersion = $currentVersion + 1;
                        $prevTotalReviews = is_array($previousInsight) ? ($previousInsight['totalReviews'] ?? 0) : 0;
                        
                        // Prevent double-counting if it is a fallback/forced run with no actual new reviews
                        $totalReviewsCount = $prevTotalReviews + ($isFallbackRun ? 0 : $newReviews->count());
                        
                        $startReviewNum = $prevTotalReviews + 1;
                        $endReviewNum = $totalReviewsCount;

                        // Calculate batch statistics for this specific batch of new reviews
                        $batchRatings = $newReviews->pluck('calculated_rating')->filter()->values();
                        $batchAvgRating = $batchRatings->count() > 0 ? round($batchRatings->avg(), 2) : 0;
                        $batchSentiments = $newReviews->pluck('sentiment_label')->filter()->countBy()->all();
                        
                        // Count issue frequencies from this batch
                        $batchIssueFrequency = [];
                        foreach ($latestInsight as $reviewObj) {
                            foreach ($reviewObj['issues'] ?? [] as $issue) {
                                $cat = $issue['category'] ?? 'Others';
                                $batchIssueFrequency[$cat] = ($batchIssueFrequency[$cat] ?? 0) + 1;
                            }
                        }
                        arsort($batchIssueFrequency);

                        $currentMetrics = [
                            'overall_metrics' => [
                                'total_reviews' => ReviewNew::where('business_id', $business->id)->count(),
                                'average_rating' => round(ReviewNew::where('business_id', $business->id)->avg('calculated_rating') ?? 0, 2),
                                'sentiment_counts' => [
                                    'positive' => ReviewNew::where('business_id', $business->id)->where('sentiment_label', 'positive')->count(),
                                    'neutral' => ReviewNew::where('business_id', $business->id)->where('sentiment_label', 'neutral')->count(),
                                    'negative' => ReviewNew::where('business_id', $business->id)->where('sentiment_label', 'negative')->count(),
                                ],
                                'rule_trigger_counts' => \App\Models\AiRuleTrigger::where('was_suppressed', false)
                                    ->whereHas('review', function($q) use ($business) {
                                        $q->where('business_id', $business->id);
                                    })->count()
                            ],
                            'batch_statistics' => [
                                'batch_review_count' => $newReviews->count(),
                                'average_rating' => $batchAvgRating,
                                'rating_distribution' => $batchRatings->countBy()->all(),
                                'sentiment_distribution' => $batchSentiments,
                                'issue_frequency' => $batchIssueFrequency,
                            ]
                        ];

                        $this->line("  → Generating rolling AI insights...");
                        Log::channel('daily')->info("  → Generating rolling AI insights for Business {$business->id}...");
                        log_message([
                            'message' => "Generating rolling AI insights for Business {$business->id}",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');

                        try {
                            $updatedInsight = $this->openaiProcessorService->generateRollingInsight($previousInsight, $latestInsight, $currentMetrics);

                            if ($updatedInsight && !empty($updatedInsight['summary']) && is_string($updatedInsight['summary'])) {
                                // Overwrite metadata to ensure integrity and backward compatibility
                                $updatedInsight['version'] = $nextVersion;
                                $updatedInsight['summary_version'] = $nextVersion;
                                $updatedInsight['totalReviews'] = $totalReviewsCount;
                                $updatedInsight['time'] = now()->toIso8601String();
                                $updatedInsight['generated_at'] = now()->toIso8601String();
                                $updatedInsight['covers_reviews'] = "{$startReviewNum}-{$endReviewNum}";

                                DB::transaction(function () use ($business, $updatedInsight, $isFallbackRun, $newReviews, $totalReviewsCount, $nextVersion) {
                                    $business->update([
                                        'rolling_ai_insight' => $updatedInsight
                                    ]);

                                    // Save new version snapshot
                                    \App\Models\BusinessAiSummary::create([
                                        'business_id' => $business->id,
                                        'version' => $nextVersion,
                                        'summary' => $updatedInsight['summary'],
                                        'strengths' => $updatedInsight['strengths'] ?? [],
                                        'weaknesses' => $updatedInsight['weaknesses'] ?? [],
                                        'top_topics' => $updatedInsight['top_topics'] ?? [],
                                        'recommendations' => $updatedInsight['recommendations'] ?? [],
                                        'trend' => $updatedInsight['trend'] ?? 'Stable',
                                        'confidence' => $updatedInsight['confidence'] ?? 1.0,
                                        'total_reviews' => $totalReviewsCount,
                                    ]);

                                    // Mark reviews as aggregated
                                    if (!$isFallbackRun) {
                                        ReviewNew::whereIn('id', $newReviews->pluck('id'))->update(['is_rolling_aggregated' => true]);
                                    }
                                });
                            } else {
                                $this->line("  → Failed: OpenAI returned empty or invalid rolling insight");
                                Log::channel('daily')->warning("  → OpenAI returned empty or invalid rolling insight for Business {$business->id}");
                            }
                        } catch (\Exception $e) {
                            $this->line("  → Failed to generate rolling insight: " . $e->getMessage());
                            Log::channel('daily')->error("  → Failed to generate rolling insight for Business {$business->id}: " . $e->getMessage());
                        }
                    }

                    // 1. Aggregate insights (last 30 days)
                    $this->line("  → Aggregating insights...");
                    Log::channel('daily')->info("  → Aggregating insights for Business {$business->id}...");
                    log_message([
                        'message' => "Aggregating insights for Business {$business->id}",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');

                    // Use the injected service instance
                    $aggResult = $this->insightAggregationService->aggregateReviewsForBusiness($business->id, 30);

                    if ($aggResult['insights_created'] === 0) {
                        $this->line("  → No new insights found");
                        Log::channel('daily')->info("  → No new insights found");
                        log_message([
                            'message' => "No new insights found for Business {$business->id}",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                        continue;
                    }

                    // 2. Generate recommendations using injected service
                    $this->line("  → Generating recommendations...");
                    Log::channel('daily')->info("  → Generating recommendations...");
                    log_message([
                        'message' => "Generating recommendations for Business {$business->id}",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                    $recs = $this->recommendationGeneratorService->generateFromInsights($business->id, 30);

                    // Update last processed
                    $business->update(['last_recommendation_at' => now()]);

                    $successMsg = "✓ Business {$business->id}: Created " . count($recs) . " recommendations";
                    $this->info($successMsg);
                    Log::channel('daily')->info($successMsg);
                    log_message([
                        'message' => $successMsg,
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');

                    $results['success']++;
                } catch (\Exception $e) {
                    $errorMsg = "✗ Business {$business->id}: {$e->getMessage()}";
                    $this->error($errorMsg);
                    Log::channel('daily')->info($errorMsg);
                    log_message([
                        'message' => $errorMsg,
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');

                    // Also keep standard logging
                    Log::error('Recommendation generation failed', [
                        'business_id' => $business->id,
                        'error' => $e->getMessage()
                    ]);
                    $results['failed']++;
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $endMsg = "\nComplete: {$results['success']} success, {$results['failed']} failed";
            $this->info($endMsg);
            Log::channel('daily')->info($endMsg);
            log_message([
                'message' => $endMsg,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            return $results['failed'] > 0 ? 1 : 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("FATAL ERROR: " . $e->getMessage());
            log_message([
                'message' => "FATAL ERROR: " . $e->getMessage(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
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
            // Default: process businesses not updated in last 12 hours (unless on local)
            if (!app()->environment('local')) {
                $query->where(function ($q) {
                    $q->whereNull('last_recommendation_at')
                        ->orWhere('last_recommendation_at', '<', Carbon::now()->subHours(12));
                });
            }
            $query->limit(50); // Process max 50 per run
        }

        return $query->get();
    }

    private function shouldProcess($business)
    {
        if ($this->option('force') || app()->environment('local')) {
            return true;
        }

        // Skip if processed in last 6 hours
        if (
            $business->last_recommendation_at &&
            Carbon::parse($business->last_recommendation_at)->gt(Carbon::now()->subHours(6))
        ) {
            return false;
        }

        return true;
    }
}
