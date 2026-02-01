<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Services\AIProcessor\OpenAIProcessorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAIReviews extends Command
{
    protected $signature = 'reviews:process';

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
        log_message([
            'message' => str_repeat("=", 80),
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info("AI Review Processing started at " . now());
        log_message([
            'message' => "AI Review Processing started at " . now(),
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info(str_repeat("=", 80));
        log_message([
            'message' => str_repeat("=", 80),
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');

        Log::channel('daily')->info('🚀 Starting OpenAI Review Processing...');
        log_message([
            'message' => '🚀 Starting OpenAI Review Processing...',
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');

        try {
            $this->processBatch();

            Log::channel('daily')->info("Processing completed successfully at " . now());
            log_message([
                'message' => "Processing completed successfully at " . now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info(str_repeat("=", 80) . "\n");
            log_message([
                'message' => str_repeat("=", 80),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
        } catch (\Exception $e) {
            $errorMessage = "❌ Processing failed: " . $e->getMessage();
            Log::channel('daily')->info("ERROR: " . $errorMessage);
            log_message([
                'message' => "ERROR: " . $errorMessage,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info(str_repeat("=", 80) . "\n");
            log_message([
                'message' => str_repeat("=", 80),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
        }
    }



    protected function processBatch()
    {
        $query = ReviewNew::whereNotNull('raw_text')
            ->where('is_ai_processed', 0);

        $reviews = $query->orderBy('id', 'asc')
            ->get();

        if ($reviews->isEmpty()) {
            $logMessage = "No reviews to process.\n";
            Log::channel('daily')->info($logMessage);
            log_message([
                'message' => $logMessage,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            return;
        }

        $logMessage = "Found {$reviews->count()} reviews to process\n";
        Log::channel('daily')->info($logMessage);
        log_message([
            'message' => $logMessage,
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');

        Log::channel('daily')->info("📊 Found {$reviews->count()} reviews to process\n");
        log_message([
            'message' => "Found {$reviews->count()} reviews to process",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');


        $successCount = 0;
        $failedCount = 0;
        $alreadyProcessed = 0;

        Log::channel('daily')->info("Starting batch processing of " . $reviews->count() . " reviews\n");
        $progressBar = $this->output->createProgressBar($reviews->count());
        $progressBar->start();

        log_message([
            'message' => "Starting batch processing of " . $reviews->count() . " reviews",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');


        foreach ($reviews as $index => $review) {
            Log::channel('daily')->info("Processing review " . ($index + 1) . " of " . $reviews->count() . " (ID: {$review->id})\n");
            log_message([
                'message' => "Processing review " . ($index + 1) . " of " . $reviews->count() . " (ID: {$review->id})",
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            try {
                // Production mode
                $business = $review->business;
                if (!$business) {
                    Log::channel('daily')->info("Review #{$review->id} has no valid business, skipping\n");
                    log_message([
                        'message' => "Review #{$review->id} has no valid business, skipping",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                    continue;
                }

                // Check subscription
                if (!$business->is_subscribed) {
                    Log::channel('daily')->info("Business #{$business->id} has no active subscription, skipping review #{$review->id}\n");
                    log_message([
                        'message' => "Business #{$business->id} has no active subscription, skipping review #{$review->id}",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                    continue;
                }

                // Check token limit
                if ($business->is_token_limit_reached) {
                    Log::channel('daily')->info("Business #{$business->id} has reached AI token limit, skipping review #{$review->id}\n");
                    log_message([
                        'message' => "Business #{$business->id} has reached AI token limit, skipping review #{$review->id}",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                    continue;
                }

                if ($review->is_ai_processed) {
                    $alreadyProcessed++;
                    Log::channel('daily')->info("Review #{$review->id} already processed, skipping\n");
                    log_message([
                        'message' => "Review #{$review->id} already processed, skipping",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                } else {
                    $result = $this->processor->analyzeReview($review, false);

                    if ($result['status'] === 'already_processed') {
                        $alreadyProcessed++;
                        Log::channel('daily')->info("Review #{$review->id} already processed (via API), skipping\n");
                        log_message([
                            'message' => "Review #{$review->id} already processed (via API), skipping",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                    } else {
                        $successCount++;
                        Log::channel('daily')->info("  AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                        log_message([
                            'message' => "Review processed successfully #{$review->id}",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errorMessage = "Review #{$review->id} failed: " . $e->getMessage();
                Log::channel('daily')->info("ERROR: " . $errorMessage . "\n");
                log_message([
                    'message' => "ERROR: " . $errorMessage,
                    'path' => __FILE__,
                    'other information' => 'AI Process Logging'
                ], 'ai_process.log');
            }

            // Rate limiting delay
            usleep(300000); // 300ms delay
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\n");

        Log::channel('daily')->info("📈 Processing Complete:\n");
        log_message([
            'message' => 'Processing Complete',
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info("   ✅ Successfully processed: {$successCount}\n");
        log_message([
            'message' => "Successfully processed: {$successCount}",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');

        if ($alreadyProcessed > 0) {
            Log::channel('daily')->info("   ⏭️  Already processed (skipped): {$alreadyProcessed}\n");
            log_message([
                'message' => "Already processed (skipped): {$alreadyProcessed}",
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
        }

        Log::channel('daily')->info("   ❌ Failed: {$failedCount}\n");
        log_message([
            'message' => "Failed: {$failedCount}",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');

        if ($failedCount > 0) {
            Log::channel('daily')->info("   ⚠️ Check logs for failed reviews\n");
            log_message([
                'message' => 'Check logs for failed reviews',
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
        }

        // Log summary
        Log::channel('daily')->info("\nBatch Processing Summary:\n");
        log_message([
            'message' => 'Batch Processing Summary',
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info("  Successfully processed: {$successCount}\n");
        log_message([
            'message' => "Successfully processed: {$successCount}",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info("  Already processed (skipped): {$alreadyProcessed}\n");
        log_message([
            'message' => "Already processed (skipped): {$alreadyProcessed}",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
        Log::channel('daily')->info("  Failed: {$failedCount}\n");
        log_message([
            'message' => "Failed: {$failedCount}",
            'path' => __FILE__,
            'other information' => 'AI Process Logging'
        ], 'ai_process.log');
    }
}
