<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\AIProcessor;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAIReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-a-i-reviews {--use-openai : Use OpenAI for processing}';

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
        $useOpenAI = $this->option('use-openai');
        
        $this->info('Starting AI review processing...' . ($useOpenAI ? ' (Using OpenAI)' : ' (Using Fallback)'));
        
        $reviews = ReviewNew::where('is_ai_processed', 0)
            ->whereNotNull('raw_text')
            ->take($useOpenAI ? 20 : 50) // Smaller batch for OpenAI due to API limits
            ->get();

        if ($reviews->isEmpty()) {
            $this->info('No reviews to process.');
            return;
        }

        $processedCount = 0;
        $failedCount = 0;
        $openAICount = 0;
        $fallbackCount = 0;

        foreach ($reviews as $review) {
            try {
                $raw_text = $review->raw_text;
                
                if (empty(trim($raw_text))) {
                    $review->is_ai_processed = 1;
                    $review->save();
                    continue;
                }

                if ($useOpenAI) {
                    // Build OpenAI payload
                    $payload = self::buildOpenAIPayload($review);
                    $aiResults = OpenAIProcessor::processReview($raw_text, $review->staff_id, $payload);
                    $openAICount++;
                } else {
                    // Use fallback
                    $aiResults = OpenAIProcessor::processReview($raw_text, $review->staff_id);
                    $fallbackCount++;
                }
                
                // Store all results
                $review->moderation_results = $aiResults['moderation'];
                $review->sentiment_score = $aiResults['sentiment'];
                $review->topics = $aiResults['topics'];
                $review->ai_suggestions = $aiResults['recommendations'];
                $review->staff_suggestions = $aiResults['staff_suggestions'];
                $review->emotion = $aiResults['emotion'];
                $review->key_phrases = $aiResults['key_phrases'];
                $review->sentiment_label = $aiResults['sentiment_label'];
                
                // Store OpenAI results if available
                if (isset($aiResults['openai_result'])) {
                    $review->openai_results = $aiResults['openai_result'];
                    $review->ai_provider = 'openai';
                } else {
                    $review->ai_provider = 'fallback';
                }
                
                $review->is_ai_processed = 1;
                $review->processed_at = now();
                $review->save();

                $processedCount++;
                
                Log::info("AI processing completed for review ID: {$review->id}", [
                    'provider' => $review->ai_provider,
                    'sentiment' => $aiResults['sentiment'],
                    'sentiment_label' => $aiResults['sentiment_label']
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
            
            // Delay to avoid rate limiting (longer for OpenAI)
            usleep($useOpenAI ? 200000 : 100000); // 200ms for OpenAI, 100ms for fallback
        }

        $this->info("Processing complete.");
        $this->info("Total processed: {$processedCount}");
        $this->info("OpenAI processed: {$openAICount}");
        $this->info("Fallback processed: {$fallbackCount}");
        $this->info("Failed: {$failedCount}");
        
        if ($failedCount > 0) {
            $this->error("{$failedCount} reviews failed to process. Check logs for details.");
        }
    }
    
    /**
     * Build OpenAI payload from review
     */
    private static function buildOpenAIPayload($review)
    {
        $defaultBusinessAISettings = [
            'staff_intelligence' => true,
            'ignore_abusive_reviews_for_staff' => true,
            'min_reviews_for_staff_score' => 3,
            'confidence_threshold' => 0.7
        ];
        
        $defaultReviewMetadata = [
            'source' => 'platform',
            'business_type' => 'hotel',
            'branch_id' => 'BR-101',
            'submitted_at' => now()->toIso8601String()
        ];
        
        $defaultRatings = [
            'overall' => $review->rating ?? 3,
            'questions' => []
        ];
        
        $staffContext = [
            'staff_selected' => !empty($review->staff_id),
            'staff_id' => $review->staff_id ?? '',
            'staff_name' => $review->staff_name ?? ''
        ];
        
        $serviceUnit = [
            'unit_type' => 'Room',
            'unit_id' => $review->room_number ?? ''
        ];
        
        return [
            'business_ai_settings' => $defaultBusinessAISettings,
            'review_metadata' => $defaultReviewMetadata,
            'review_content' => [
                'text' => $review->raw_text,
                'voice_review' => false
            ],
            'ratings' => $defaultRatings,
            'staff_context' => $staffContext,
            'service_unit' => $serviceUnit
        ];
    }
}