<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class FixReviewCache extends Command
{
    protected $signature = 'fix:cache {review_id}';
    protected $description = 'Clear cache and fix review processing';

    public function handle()
    {
        $reviewId = $this->argument('review_id');
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $this->error("Review #{$reviewId} not found.");
            return;
        }

        $this->info("🔧 Fixing Review #{$review->id}");
        $this->info(str_repeat('─', 50));
        
        // Step 1: Clear cache
        $this->info("🗑️  Step 1: Clearing cache...");
        $payload = OpenAIProcessor::createPayloadFromReview($review);
        $cacheKey = 'openai_review_' . md5(json_encode($payload));
        Cache::forget($cacheKey);
        $this->info("   Cache cleared for key: {$cacheKey}");
        
        // Step 2: Reset review
        $this->info("\n🔄 Step 2: Resetting review...");
        $review->update([
            'is_ai_processed' => 0,
            'ai_confidence' => 0,
            'sentiment_label' => null,
            'sentiment_score' => null,
            'sentiment' => null,
            'emotion' => null,
            'openai_raw_response' => null
        ]);
        $this->info("   Review reset.");
        
        // Step 3: Process with OpenAI
        $this->info("\n🎯 Step 3: Processing with OpenAI...");
        try {
            $result = OpenAIProcessor::analyzeReview($review, true);
            
            $this->info("\n✅ Result:");
            $this->info("   Status: " . ($result['status'] ?? 'N/A'));
            
            if ($result['status'] === 'fallback') {
                $this->error("   ❌ Still using fallback!");
                if (isset($result['error'])) {
                    $this->error("   Error: " . $result['error']);
                }
            } else {
                $review->refresh();
                $this->info("   Sentiment: " . ($review->sentiment_label ?? 'N/A'));
                $this->info("   Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%");
                $this->info("   Emotion: " . ($review->emotion ?? 'N/A'));
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
        }
        
        $this->info(str_repeat('─', 50));
    }
}