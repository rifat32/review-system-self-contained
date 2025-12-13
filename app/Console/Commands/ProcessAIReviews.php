<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\AIProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAIReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-a-i-reviews';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process unprocessed reviews with AI analysis';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting AI review processing...');
        
        $reviews = ReviewNew::where('is_ai_processed', 0)
            ->whereNotNull('raw_text')
            ->take(50) // Process in batches
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No reviews to process.');
            return;
        }

        $processedCount = 0;
        $failedCount = 0;

        foreach ($reviews as $review) {
            try {
                $raw_text = $review->raw_text;
                
                if (empty(trim($raw_text))) {
                    $review->is_ai_processed = 1;
                    $review->save();
                    continue;
                }

                // ✅ SINGLE CALL using processReview method
                $aiResults = AIProcessor::processReview($raw_text, $review->staff_id);
                
                // Store all results in the review
                $review->moderation_results = $aiResults['moderation'];
                $review->sentiment_score = $aiResults['sentiment'];
                $review->topics = $aiResults['topics'];
                $review->ai_suggestions = $aiResults['recommendations'];
                $review->staff_suggestions = $aiResults['staff_suggestions'];
                $review->emotion = $aiResults['emotion'];
                $review->key_phrases = $aiResults['key_phrases'];
                
                // Additional fields for report compatibility
                $review->sentiment_label = $aiResults['sentiment_label'];
                $review->is_ai_processed = 1;
                $review->save();

                $processedCount++;
                
                // Log successful processing
                Log::info("AI processing completed for review ID: {$review->id}", [
                    'sentiment' => $aiResults['sentiment'],
                    'sentiment_label' => $aiResults['sentiment_label'],
                    'topics' => count($aiResults['topics']),
                    'staff_suggestions' => count($aiResults['staff_suggestions'])
                ]);

            } catch (\Exception $e) {
                $failedCount++;
                Log::error("AI processing failed for review ID: {$review->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // Mark as processed even if failed to avoid infinite retry
                $review->is_ai_processed = 1;
                $review->save();
            }
            
            // Small delay to avoid rate limiting
            usleep(100000); // 100ms
        }

        $this->info("Processing complete. Success: {$processedCount}, Failed: {$failedCount}");
        
        if ($failedCount > 0) {
            $this->error("{$failedCount} reviews failed to process. Check logs for details.");
        }
    }
}