<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\AIProcessor;

class TestAIProcessing extends Command
{
    protected $signature = 'test:ai-processing';
    protected $description = 'Test AI processing functions with detailed output';

    public function handle()
    {
        $testReviews = [
            "Absolutely amazing experience! The food was delicious, the ambiance was classy, and the service was flawless.",
            "Very poor service, their behaviour was really bad. The food was cold and tasteless.",
            "It was okay, nothing special. The staff were friendly but the wait time was too long.",
            "This restaurant delivers excellence in every aspect! The dishes were bursting with fresh, authentic flavors.",
            "The staff was rude and unhelpful. Terrible experience overall.",
            "I'm not happy with the service. The food wasn't bad, but the wait was too long.",
            "Excellent! Best restaurant ever! Will definitely return!",
            "The place is dirty, the staff is rude, and the food is overpriced. Never coming back."
        ];

        $this->info(str_repeat('═', 80));
        $this->info("🤖 AI PROCESSING TEST SUITE");
        $this->info(str_repeat('═', 80));

        foreach ($testReviews as $index => $review) {
            $this->info("\n" . str_repeat('─', 80));
            $this->info("📝 TEST REVIEW #" . ($index + 1));
            $this->info(str_repeat('─', 80));
            $this->info("Review: \"{$review}\"");
            
            // Process using utility method
            $result = AIProcessor::processReview($review, 1);
            
            $this->info("\n📊 RESULTS:");
            $this->info("  🎯 Sentiment: {$result['sentiment']} ({$result['sentiment_label']})");
            $this->info("  😊 Emotion: {$result['emotion']}");
            $this->info("  🏷️ Topics: " . (count($result['topics']) ? implode(', ', $result['topics']) : 'None'));
            $this->info("  🔑 Key Phrases: " . (count($result['key_phrases']) ? implode(', ', $result['key_phrases']) : 'None'));
            $this->info("  🛡️ Moderation: " . (count($result['moderation']['issues_found']) ? implode(', ', $result['moderation']['issues_found']) : 'None'));
            $this->info("  👥 Staff Suggestions: " . (count($result['staff_suggestions']) ? implode(', ', $result['staff_suggestions']) : 'None'));
            $this->info("  💡 Recommendations: " . (count($result['recommendations']) ? implode(', ', $result['recommendations']) : 'None'));
        }
        
        $this->info("\n" . str_repeat('═', 80));
        $this->info("✅ AI PROCESSING TEST COMPLETE!");
        $this->info(str_repeat('═', 80));
    }
}