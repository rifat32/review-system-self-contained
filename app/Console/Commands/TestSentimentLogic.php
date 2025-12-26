<?php

namespace App\Console\Commands;

use App\Helpers\AIProcessor;
use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;

class TestSentimentLogic extends Command
{
    protected $signature = 'test:sentiment {review_id}';
    protected $description = 'Test sentiment logic';

    public function handle()
    {
        $reviewId = $this->argument('review_id');
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $this->error("Review #{$reviewId} not found.");
            return;
        }

        $this->info("🧪 Testing Sentiment Logic for Review #{$review->id}");
        $this->info(str_repeat('═', 60));
        
        // Test 1: Check getSentimentLabel function
        $this->info("\n📊 Test 1: getSentimentLabel function");
        $testScores = [0.9, 0.7, 0.5, 0.3, 0.1, 0.4, 0.6, 0.25];
        foreach ($testScores as $score) {
            $label = AIProcessor::getSentimentLabel($score);
            $this->info("   Score {$score} → {$label}");
        }
        
        // Test 2: Get current OpenAI response
        $this->info("\n📦 Test 2: Current OpenAI Response");
        if (empty($review->openai_raw_response)) {
            $this->error("   No OpenAI response in database.");
        } else {
            $response = json_decode($review->openai_raw_response, true);
            $this->info("   Raw score: " . ($response['sentiment']['score'] ?? 'N/A'));
            $this->info("   Raw label: " . ($response['sentiment']['label'] ?? 'N/A'));
            $this->info("   Confidence: " . (($response['explainability']['confidence_score'] ?? 0) * 100) . "%");
            
            // Calculate normalized score
            $rawScore = $response['sentiment']['score'] ?? 0.0;
            $normalized = ($rawScore + 1) / 2;
            $this->info("   Normalized: {$normalized}");
            $this->info("   Calculated label: " . OpenAIProcessor::getSentimentLabel($normalized));
        }
        
        // Test 3: Check database values
        $this->info("\n💾 Test 3: Database Values");
        $this->info("   sentiment_score: " . ($review->sentiment_score ?? 'N/A'));
        $this->info("   sentiment_label: " . ($review->sentiment_label ?? 'N/A'));
        $this->info("   ai_confidence: " . (($review->ai_confidence ?? 0) * 100) . "%");
        
        // Test 4: Manual calculation
        $this->info("\n🧮 Test 4: Manual Calculation");
        if (!empty($review->openai_raw_response)) {
            $response = json_decode($review->openai_raw_response, true);
            $rawScore = $response['sentiment']['score'] ?? 0.0;
            $normalized = ($rawScore + 1) / 2;
            
            $this->info("   Raw score from JSON: {$rawScore}");
            $this->info("   Normalized: {$normalized}");
            $this->info("   Should be in DB: {$normalized}");
            $this->info("   Actual in DB: " . ($review->sentiment_score ?? 'N/A'));
            
            if (abs(($review->sentiment_score ?? 0) - $normalized) > 0.01) {
                $this->error("   ⚠️  MISMATCH: Database value is wrong!");
            } else {
                $this->info("   ✅ Database value matches calculation");
            }
        }
        
        $this->info(str_repeat('═', 60));
    }
}