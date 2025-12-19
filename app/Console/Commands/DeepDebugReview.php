<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;

class DeepDebugReview extends Command
{
    protected $signature = 'debug:deep {review_id}';
    protected $description = 'Deep debug review processing';

    public function handle()
    {
        $reviewId = $this->argument('review_id');
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $this->error("Review #{$reviewId} not found.");
            return;
        }

        $this->info("🔍 Deep Debugging Review #{$review->id}");
        $this->info(str_repeat('═', 60));
        
        // Reset first
        $this->info("🔄 Resetting review...");
        $review->update([
            'is_ai_processed' => 0,
            'ai_confidence' => 0,
            'sentiment_label' => null,
            'sentiment_score' => null
        ]);
        $this->info("✅ Reset complete.");
        
        // Step 1: Create payload
        $this->info("\n📦 Step 1: Creating Payload");
        $payload = OpenAIProcessor::createPayloadFromReview($review);
        $this->info("   Review Text: " . ($payload['review_text'] ?? 'N/A'));
        $this->info("   Text Length: " . strlen($payload['review_text'] ?? ''));
        $this->info("   Rating: " . ($payload['rating'] ?? 'N/A'));
        
        // Step 2: Call OpenAI directly
        $this->info("\n🔄 Step 2: Calling OpenAI Directly");
        try {
            $openAIResult = OpenAIProcessor::processReviewWithOpenAI($payload);
            
            if (isset($openAIResult['_fallback']) && $openAIResult['_fallback']) {
                $this->error("❌ USING FALLBACK!");
                if (isset($openAIResult['_error'])) {
                    $this->error("   Error: " . $openAIResult['_error']);
                }
                if (isset($openAIResult['_warning'])) {
                    $this->warn("   Warning: " . $openAIResult['_warning']);
                }
            } else {
                $this->info("✅ OpenAI Response Received");
                $this->info("   Sentiment: " . ($openAIResult['sentiment']['label'] ?? 'N/A'));
                $this->info("   Score: " . ($openAIResult['sentiment']['score'] ?? 'N/A'));
                $this->info("   Emotion: " . ($openAIResult['emotion']['primary'] ?? 'N/A'));
                $this->info("   Confidence: " . round(($openAIResult['explainability']['confidence_score'] ?? 0) * 100) . "%");
                
                // Check if sentiment makes sense
                $text = strtolower($payload['review_text'] ?? '');
                if (str_contains($text, 'good') && ($openAIResult['sentiment']['label'] ?? '') === 'negative') {
                    $this->warn("⚠️  Warning: Text contains 'good' but sentiment is negative!");
                    $this->info("   This might be correct if overall context is negative.");
                }
                
                if (isset($openAIResult['_metadata']['tokens_used'])) {
                    $this->info("   Tokens Used: " . $openAIResult['_metadata']['tokens_used']);
                }
            }
            
            // Save raw response
            file_put_contents(storage_path("logs/review_{$reviewId}_openai.json"), 
                json_encode($openAIResult, JSON_PRETTY_PRINT));
            $this->info("   Raw response saved to: storage/logs/review_{$reviewId}_openai.json");
            
        } catch (\Exception $e) {
            $this->error("❌ OpenAI Error: " . $e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
        }
        
        // Step 3: Analyze with method
        $this->info("\n🎯 Step 3: Analyzing with analyzeReview method");
        try {
            $result = OpenAIProcessor::analyzeReview($review, true);
            
            $this->info("✅ Analysis Result:");
            $this->info("   Status: " . ($result['status'] ?? 'N/A'));
            $this->info("   Message: " . ($result['message'] ?? 'N/A'));
            
            if (isset($result['error'])) {
                $this->error("   Error: " . $result['error']);
            }
            
            // Refresh and show
            $review->refresh();
            $this->info("\n📊 Database State After Analysis:");
            $this->info("   is_ai_processed: " . ($review->is_ai_processed ? 'Yes' : 'No'));
            $this->info("   sentiment_label: " . ($review->sentiment_label ?? 'N/A'));
            $this->info("   sentiment_score: " . ($review->sentiment_score ?? 'N/A'));
            $this->info("   emotion: " . ($review->emotion ?? 'N/A'));
            $this->info("   ai_confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%");
            $this->info("   key_phrases: " . ($review->key_phrases ?? 'N/A'));
            
            // Check OpenAI response in database
            if ($review->openai_raw_response) {
                $dbResponse = json_decode($review->openai_raw_response, true);
                if (isset($dbResponse['_fallback'])) {
                    $this->error("❌ Database shows fallback was used!");
                } else {
                    $this->info("✅ Database shows OpenAI was used");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Analysis Error: " . $e->getMessage());
        }
        
        $this->info(str_repeat('═', 60));
        
        // Final check
        $this->info("\n🔍 Final Check:");
        if ($review->ai_confidence == 0) {
            $this->error("❌ PROBLEM: AI Confidence is 0% - Fallback was used");
            $this->info("   Possible causes:");
            $this->info("   1. OpenAI API is failing");
            $this->info("   2. There's an error in the payload");
            $this->info("   3. The review text is causing issues");
            $this->info("\n   Check: tail -f storage/logs/laravel.log");
        } else {
            $this->info("✅ SUCCESS: Review processed with " . round($review->ai_confidence * 100) . "% confidence");
        }
    }
}