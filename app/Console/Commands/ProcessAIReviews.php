<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Services\AIProcessor\OpenAIProcessorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAIReviews extends Command
{
    protected $signature = 'reviews:process 
                          {--batch-size=50 : Number of reviews to process}
                          {--review-id= : Process specific review ID}
                          {--test : Test mode without saving}
                          {--force : Reprocess already processed reviews}';

    protected $description = 'Process reviews with OpenAI AI analysis';

    private OpenAIProcessorService $processor;



    public function __construct(OpenAIProcessorService $processor)
    {
        parent::__construct();
        $this->processor = $processor;
    }

    public function handle()
    {
        Log::channel('daily')->info("\n" . str_repeat("=", 80));
        Log::channel('daily')->info("AI Review Processing started at " . now());
        Log::channel('daily')->info(str_repeat("=", 80));

        Log::channel('daily')->info('🚀 Starting OpenAI Review Processing...');

        try {
            if ($this->option('review-id')) {
                $this->processSingleReview();
            } else {
                $this->processBatch();
            }

            Log::channel('daily')->info("Processing completed successfully at " . now());
            Log::channel('daily')->info(str_repeat("=", 80) . "\n");
        } catch (\Exception $e) {
            $errorMessage = "❌ Processing failed: " . $e->getMessage();
            Log::channel('daily')->info("ERROR: " . $errorMessage);
            Log::channel('daily')->info(str_repeat("=", 80) . "\n");
        }
    }

    protected function processSingleReview()
    {
        $reviewId = $this->option('review-id');
        Log::channel('daily')->info("Processing single review ID: {$reviewId}\n");

        $review = ReviewNew::find($reviewId);

        if (!$review) {
            $errorMessage = "Review ID {$reviewId} not found.";
            Log::channel('daily')->info("ERROR: " . $errorMessage . "\n");
            return;
        }

        $logMessage = "Processing Review #{$review->id}, Business: {$review->business_id}, ";
        $logMessage .= "Staff: " . ($review->staff_id ? "Yes (ID: {$review->staff_id})" : "No") . ", ";
        $logMessage .= "Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No');
        Log::channel('daily')->info($logMessage . "\n");

        Log::channel('daily')->info("📋 Processing Review #{$review->id}\n");
        Log::channel('daily')->info("   Business: {$review->business_id}\n");
        Log::channel('daily')->info("   Staff: " . ($review->staff_id ? "Yes (ID: {$review->staff_id})" : "No") . "\n");
        Log::channel('daily')->info("   Text: " . substr($review->raw_text ?? $review->comment ?? '', 0, 100) . "...\n");
        Log::channel('daily')->info("   Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No') . "\n");

        try {
            if ($this->option('test')) {
                Log::channel('daily')->info("TEST MODE: Analyzing review without saving\n");
                $payload = $this->processor->createPayloadFromReview($review);
                $enabledModules = $this->processor->getBusinessAiModules($review->business_id);
                $result = $this->processor->processReviewWithOpenAI($payload, $enabledModules);

                Log::channel('daily')->info("\n✅ OpenAI Analysis Results:\n");
                Log::channel('daily')->info("   Sentiment: " . ($result['sentiment']['label'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("   Emotion: " . ($result['emotion']['primary'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("   Language: " . ($result['language']['detected'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("   Themes: " . count($result['themes'] ?? []) . "\n");
                Log::channel('daily')->info("   Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%\n");
                Log::channel('daily')->info("   Summary: " . ($result['summary']['one_line'] ?? '') . "\n");

                // Log results
                Log::channel('daily')->info("Test Analysis Results:\n");
                Log::channel('daily')->info("  Sentiment: " . ($result['sentiment']['label'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("  Emotion: " . ($result['emotion']['primary'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("  Language: " . ($result['language']['detected'] ?? 'N/A') . "\n");
                Log::channel('daily')->info("  Themes Count: " . count($result['themes'] ?? []) . "\n");
                Log::channel('daily')->info("  Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%\n");

                if (isset($result['_metadata']['tokens_used'])) {
                    $tokens = $result['_metadata']['tokens_used'];
                    Log::channel('daily')->info("   Tokens Used: " . $tokens . "\n");
                    Log::channel('daily')->info("  Tokens Used: " . $tokens . "\n");
                }
            } else {
                Log::channel('daily')->info("PRODUCTION MODE: Processing and saving results\n");
                $forceReprocess = $this->option('force');

                if ($forceReprocess) {
                    Log::channel('daily')->info("Force reprocessing enabled\n");
                }

                $result = $this->processor->analyzeReview($review, $forceReprocess);

                if ($result['status'] === 'already_processed' && !$forceReprocess) {
                    $logMessage = "Review #{$review->id} already processed. Skipping.\n";
                    Log::channel('daily')->info($logMessage);

                    Log::channel('daily')->info("\n⚠️  Review already processed!\n");
                    Log::channel('daily')->info("   Sentiment: " . ($result['sentiment_label'] ?? 'N/A') . "\n");
                    Log::channel('daily')->info("   AI Confidence: " . round(($result['ai_confidence'] ?? 0) * 100) . "%\n");
                    Log::channel('daily')->info("   Status: " . ($result['is_abusive'] ? '⚠️ Flagged' : '✅ Active') . "\n");
                    Log::channel('daily')->info("   Use --force flag to reprocess.\n");
                    return;
                }

                // Refresh the review from database
                $review->refresh();

                Log::channel('daily')->info("\n✅ Review Processed Successfully:\n");
                Log::channel('daily')->info("   Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                Log::channel('daily')->info("   Emotion: " . ($review->emotion ?? 'N/A') . "\n");
                Log::channel('daily')->info("   Key Phrases: " . substr($review->key_phrases ?? '[]', 0, 100) . "\n");
                Log::channel('daily')->info("   AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                Log::channel('daily')->info("   Status: " . ($review->is_abusive ? '⚠️ Flagged' : '✅ Active') . "\n");

                // Log detailed results
                Log::channel('daily')->info("Review #{$review->id} processed successfully\n");
                Log::channel('daily')->info("  Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                Log::channel('daily')->info("  Emotion: " . ($review->emotion ?? 'N/A') . "\n");
                Log::channel('daily')->info("  AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                Log::channel('daily')->info("  Is Abusive: " . ($review->is_abusive ? 'Yes' : 'No') . "\n");
                Log::channel('daily')->info("  Status: " . ($result['message'] ?? 'completed') . "\n");

                if (isset($result['message'])) {
                    Log::channel('daily')->info("   Status: " . $result['message'] . "\n");
                }
            }
        } catch (\Exception $e) {
            $errorMessage = "Processing failed for review #{$review->id}: " . $e->getMessage();
            Log::channel('daily')->info("ERROR: " . $errorMessage . "\n");
            Log::channel('daily')->info("Stack trace: " . $e->getTraceAsString() . "\n");
        }
    }

    protected function processBatch()
    {
        $query = ReviewNew::whereNotNull('raw_text');

        if (!$this->option('force')) {
            $query->where('is_ai_processed', 0);
        }

        $reviews = $query->take($this->option('batch-size'))
            ->orderBy('created_at', 'asc')
            ->get();

        if ($reviews->isEmpty()) {
            $logMessage = "No reviews to process.\n";
            Log::channel('daily')->info($logMessage);
            return;
        }

        $logMessage = "Found {$reviews->count()} reviews to process";
        if ($this->option('force')) {
            $logMessage .= " (force mode: reprocess already processed)";
        }
        $logMessage .= "\n";
        Log::channel('daily')->info($logMessage);

        Log::channel('daily')->info("📊 Found {$reviews->count()} reviews to process\n");
        if ($this->option('force')) {
            Log::channel('daily')->info("⚠️  Force mode: Will reprocess already processed reviews\n");
        }

        $successCount = 0;
        $failedCount = 0;
        $totalTokens = 0;
        $alreadyProcessed = 0;

        Log::channel('daily')->info("Starting batch processing of " . $reviews->count() . " reviews\n");

        foreach ($reviews as $index => $review) {
            Log::channel('daily')->info("Processing review " . ($index + 1) . " of " . $reviews->count() . " (ID: {$review->id})\n");

            try {
                if ($this->option('test')) {
                    // Test mode
                    Log::channel('daily')->info("Testing review #{$review->id}\n");
                    $payload = $this->processor->createPayloadFromReview($review);

                    $enabledModules = $this->processor->getBusinessAiModules($review->business_id);
                    $result = $this->processor->processReviewWithOpenAI($payload, $enabledModules);

                    $tokens = $result['_metadata']['tokens_used'] ?? 0;
                    $totalTokens += $tokens;

                    $logMessage = "Review #{$review->id}: " .
                        ($result['sentiment']['label'] ?? 'unknown') .
                        " sentiment, " . ($result['themes'][0]['topic'] ?? 'no themes') .
                        " (Tokens: {$tokens})\n";
                    Log::channel('daily')->info($logMessage);
                } else {
                    // Production mode
                    $forceReprocess = $this->option('force');

                    if ($review->is_ai_processed && !$forceReprocess) {
                        $alreadyProcessed++;
                        Log::channel('daily')->info("Review #{$review->id} already processed, skipping\n");
                    } else {
                        $result = $this->processor->analyzeReview($review, $forceReprocess);

                        if ($result['status'] === 'already_processed') {
                            $alreadyProcessed++;
                            Log::channel('daily')->info("Review #{$review->id} already processed (via API), skipping\n");
                        } else {
                            $successCount++;
                            Log::channel('daily')->info("Review #{$review->id} processed successfully\n");
                            Log::channel('daily')->info("  Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                            Log::channel('daily')->info("  AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                        }
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errorMessage = "Review #{$review->id} failed: " . $e->getMessage();
                Log::channel('daily')->info("ERROR: " . $errorMessage . "\n");

                if (!$this->option('test')) {
                    $review->update(['is_ai_processed' => 1]);
                }
            }

            // Rate limiting delay
            if (!$this->option('test')) {
                usleep(300000); // 300ms delay
            }
        }

        Log::channel('daily')->info("📈 Processing Complete:\n");
        Log::channel('daily')->info("   ✅ Successfully processed: {$successCount}\n");

        if ($alreadyProcessed > 0) {
            Log::channel('daily')->info("   ⏭️  Already processed (skipped): {$alreadyProcessed}\n");
        }

        Log::channel('daily')->info("   ❌ Failed: {$failedCount}\n");

        if ($this->option('test')) {
            Log::channel('daily')->info("   ⚡ Estimated tokens used: {$totalTokens}\n");
            Log::channel('daily')->info("   💰 Estimated cost: $" . number_format($totalTokens * 0.00015 / 1000, 4) . "\n");
        }

        if ($failedCount > 0) {
            Log::channel('daily')->info("   ⚠️ Check logs for failed reviews\n");
        }

        // Log summary
        Log::channel('daily')->info("\nBatch Processing Summary:\n");
        Log::channel('daily')->info("  Successfully processed: {$successCount}\n");
        Log::channel('daily')->info("  Already processed (skipped): {$alreadyProcessed}\n");
        Log::channel('daily')->info("  Failed: {$failedCount}\n");

        if ($this->option('test')) {
            Log::channel('daily')->info("  Estimated tokens used: {$totalTokens}\n");
            Log::channel('daily')->info("  Estimated cost: $" . number_format($totalTokens * 0.00015 / 1000, 4) . "\n");
        }
    }
}
