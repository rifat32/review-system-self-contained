<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
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

    public function handle()
    {
        $this->info('🚀 Starting OpenAI Review Processing...');
        
        if ($this->option('review-id')) {
            $this->processSingleReview();
        } else {
            $this->processBatch();
        }
    }
    
    protected function processSingleReview()
    {
        $reviewId = $this->option('review-id');
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $this->error("❌ Review ID {$reviewId} not found.");
            return;
        }
        
        $this->info("📋 Processing Review #{$review->id}");
        $this->info("   Business: {$review->business_id}");
        $this->info("   Staff: " . ($review->staff_id ? "Yes (ID: {$review->staff_id})" : "No"));
        $this->info("   Text: " . substr($review->raw_text ?? $review->comment ?? '', 0, 100) . "...");
        $this->info("   Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No'));
        
        try {
            if ($this->option('test')) {
                $payload = OpenAIProcessor::createPayloadFromReview($review);
                $result = OpenAIProcessor::processReviewWithOpenAI($payload);
                
                $this->info("\n✅ OpenAI Analysis Results:");
                $this->info("   Sentiment: " . ($result['sentiment']['label'] ?? 'N/A'));
                $this->info("   Emotion: " . ($result['emotion']['primary'] ?? 'N/A'));
                $this->info("   Language: " . ($result['language']['detected'] ?? 'N/A'));
                $this->info("   Themes: " . count($result['themes'] ?? []));
                $this->info("   Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%");
                $this->info("   Summary: " . ($result['summary']['one_line'] ?? ''));
                
                if (isset($result['_metadata']['tokens_used'])) {
                    $this->info("   Tokens Used: " . $result['_metadata']['tokens_used']);
                }
                
            } else {
                // Use force flag for single review processing
                $forceReprocess = $this->option('force');
                $result = OpenAIProcessor::analyzeReview($review, $forceReprocess);
                
                if ($result['status'] === 'already_processed' && !$forceReprocess) {
                    $this->warn("\n⚠️  Review already processed!");
                    $this->info("   Sentiment: " . ($result['sentiment_label'] ?? 'N/A'));
                    $this->info("   AI Confidence j: " . round(($result['ai_confidence'] ?? 0) * 100) . "%");
                    $this->info("   Status: " . ($result['is_abusive'] ? '⚠️ Flagged' : '✅ Active'));
                    $this->info("   Use --force flag to reprocess.");
                    return;
                }
                
                // Refresh the review from database
                $review->refresh();
                
                $this->info("\n✅ Review Processed Successfully:");
                $this->info("   Sentiment: " . ($review->sentiment_label ?? 'N/A'));
                $this->info("   Emotion: " . ($review->emotion ?? 'N/A'));
                $this->info("   Key Phrases: " . substr($review->key_phrases ?? '[]', 0, 100));
                $this->info("   AI Confidence k: " . round(($review->ai_confidence ?? 0) * 100) . "%");
$this->info(json_encode($result));
                
                $this->info("   Status: " . ($review->is_abusive ? '⚠️ Flagged' : '✅ Active'));
                
                if (isset($result['message'])) {
                    $this->info("   Status: " . $result['message']);
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Processing failed: " . $e->getMessage());
            Log::error('Single review processing failed', [
                'review_id' => $reviewId,
                'error' => $e->getMessage()
            ]);
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
            $this->info("✅ No reviews to process.");
            return;
        }
        
        $this->info("📊 Found {$reviews->count()} reviews to process");
        if ($this->option('force')) {
            $this->info("⚠️  Force mode: Will reprocess already processed reviews");
        }
        
        $progressBar = $this->output->createProgressBar($reviews->count());
        $progressBar->start();
        
        $successCount = 0;
        $failedCount = 0;
        $totalTokens = 0;
        $alreadyProcessed = 0;
        
        foreach ($reviews as $review) {
            try {
                if ($this->option('test')) {
                    // Test mode
                    $payload = OpenAIProcessor::createPayloadFromReview($review);
                    $result = OpenAIProcessor::processReviewWithOpenAI($payload);
                    $tokens = $result['_metadata']['tokens_used'] ?? 0;
                    $totalTokens += $tokens;
                    
                    $this->info("\nReview #{$review->id}: " . 
                               ($result['sentiment']['label'] ?? 'unknown') . 
                               " sentiment, " . ($result['themes'][0]['topic'] ?? 'no themes'));
                } else {
                    // Production mode - always force for batch when --force flag is used
                    $forceReprocess = $this->option('force');
                    $result = OpenAIProcessor::analyzeReview($review, $forceReprocess);
                    
                    if ($result['status'] === 'already_processed') {
                        $alreadyProcessed++;
                    } else {
                        $successCount++;
                    }
                }
                
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Review processing failed', [
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
                
                if (!$this->option('test')) {
                    $review->update(['is_ai_processed' => 1]);
                }
            }
            
            $progressBar->advance();
            
            // Rate limiting delay
            if (!$this->option('test')) {
                usleep(300000); // 300ms delay
            }
        }
        
        $progressBar->finish();
        
        $this->newLine(2);
        $this->info("📈 Processing Complete:");

        $this->info("   ✅ Successfully processed: {$successCount}");
        
        if ($alreadyProcessed > 0) {
            $this->info("   ⏭️  Already processed (skipped): {$alreadyProcessed}");
        }
        
        $this->info("   ❌ Failed: {$failedCount}");
        
        if ($this->option('test')) {
            $this->info("   ⚡ Estimated tokens used: {$totalTokens}");
            $this->info("   💰 Estimated cost: $" . number_format($totalTokens * 0.00015 / 1000, 4));
        }
        
        if ($failedCount > 0) {
            $this->error("   ⚠️ Check logs for failed reviews");
        }
    }
}