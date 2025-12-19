<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;

class DebugReviewProcessing extends Command
{
    protected $signature = 'debug:review {review_id}';
    protected $description = 'Debug review processing';

    public function handle()
    {
        $reviewId = $this->argument('review_id');
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $this->error("Review #{$reviewId} not found.");
            return;
        }

        $this->info("🔍 Debugging Review #{$review->id}");
        $this->info(str_repeat('─', 50));
        
        // Show review data
        $this->info("📝 Review Data:");
        $this->info("   Text: " . ($review->raw_text ?? $review->comment ?? 'No text'));
        $this->info("   Rating: " . ($review->rate ?? 'No rating'));
        $this->info("   Staff ID: " . ($review->staff_id ?? 'None'));
        $this->info("   Business Area: " . ($review->business_area_id ?? 'None'));
        $this->info("   Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No'));
        
        // Create payload
        $this->info("\n📦 Creating Payload...");
        $payload = OpenAIProcessor::createPayloadFromReview($review);
        
        $this->info("Payload created successfully.");
        $this->info("Review text length: " . strlen($payload['review_text'] ?? ''));
        $this->info("Has staff info: " . ($payload['staff_info'] ? 'Yes' : 'No'));
        
        // Test OpenAI directly
        $this->info("\n🔄 Testing OpenAI API...");
        try {
            $result = OpenAIProcessor::processReviewWithOpenAI($payload);
            
            if (isset($result['_fallback']) && $result['_fallback']) {
                $this->error("❌ USING FALLBACK ANALYSIS");
                if (isset($result['_error'])) {
                    $this->error("   Error: " . $result['_error']);
                }
            } else {
                $this->info("✅ OpenAI Analysis Successful!");
                $this->info("   Sentiment: " . ($result['sentiment']['label'] ?? 'N/A'));
                $this->info("   Emotion: " . ($result['emotion']['primary'] ?? 'N/A'));
                $this->info("   Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%");
                $this->info("   Summary: " . ($result['summary']['one_line'] ?? 'N/A'));
                
                if (isset($result['_metadata']['tokens_used'])) {
                    $this->info("   Tokens Used: " . $result['_metadata']['tokens_used']);
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ OpenAI Error: " . $e->getMessage());
        }
        
        // Try analyzing the review
        $this->info("\n🎯 Analyzing Review...");
        try {
            $analysis = OpenAIProcessor::analyzeReview($review);
            $this->info("✅ Analysis Complete!");
            $this->info("   Sentiment Label: " . ($review->sentiment_label ?? 'N/A'));
            $this->info("   Emotion: " . ($review->emotion ?? 'N/A'));
            $this->info("   AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%");
            $this->info("   Is Abusive: " . ($review->is_abusive ? 'Yes' : 'No'));
            
        } catch (\Exception $e) {
            $this->error("❌ Analysis Error: " . $e->getMessage());
        }
        
        $this->info(str_repeat('─', 50));
    }
}