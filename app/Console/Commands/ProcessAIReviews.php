<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use App\Helpers\OpenAIProcessor;
use Illuminate\Console\Command;

class ProcessAIReviews extends Command
{
    protected $signature = 'reviews:process 
                          {--batch-size=50 : Number of reviews to process}
                          {--review-id= : Process specific review ID}
                          {--test : Test mode without saving}
                          {--force : Reprocess already processed reviews}';
    
    protected $description = 'Process reviews with OpenAI AI analysis';

    private $logHandle;

    public function handle()
    {
        // Open log file for this processing session
        $logFile = storage_path('logs/ai_processing.log');
        $this->logHandle = fopen($logFile, 'a');
        
        $this->fileWrite("\n" . str_repeat("=", 80) . "\n");
        $this->fileWrite("AI Review Processing started at " . now() . "\n");
        $this->fileWrite(str_repeat("=", 80) . "\n");
        
        $this->fileWrite('🚀 Starting OpenAI Review Processing...');
        
        try {
            if ($this->option('review-id')) {
                $this->processSingleReview();
            } else {
                $this->processBatch();
            }
            
            $this->fileWrite("Processing completed successfully at " . now() . "\n");
            $this->fileWrite(str_repeat("=", 80) . "\n\n");
            
        } catch (\Exception $e) {
            $errorMessage = "❌ Processing failed: " . $e->getMessage();
            $this->fileWrite("ERROR: " . $errorMessage . "\n");
            $this->fileWrite(str_repeat("=", 80) . "\n\n");
        } finally {
            fclose($this->logHandle);
        }
    }
    
    protected function processSingleReview()
    {
        $reviewId = $this->option('review-id');
        $this->fileWrite("Processing single review ID: {$reviewId}\n");
        
        $review = ReviewNew::find($reviewId);
        
        if (!$review) {
            $errorMessage = "Review ID {$reviewId} not found.";
            $this->fileWrite("ERROR: " . $errorMessage . "\n");
            return;
        }
        
        $logMessage = "Processing Review #{$review->id}, Business: {$review->business_id}, ";
        $logMessage .= "Staff: " . ($review->staff_id ? "Yes (ID: {$review->staff_id})" : "No") . ", ";
        $logMessage .= "Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No');
        $this->fileWrite($logMessage . "\n");
        
        $this->fileWrite("📋 Processing Review #{$review->id}\n");
        $this->fileWrite("   Business: {$review->business_id}\n");
        $this->fileWrite("   Staff: " . ($review->staff_id ? "Yes (ID: {$review->staff_id})" : "No") . "\n");
        $this->fileWrite("   Text: " . substr($review->raw_text ?? $review->comment ?? '', 0, 100) . "...\n");
        $this->fileWrite("   Already Processed: " . ($review->is_ai_processed ? 'Yes' : 'No') . "\n");
        
        try {
            if ($this->option('test')) {
                $this->fileWrite("TEST MODE: Analyzing review without saving\n");
                $payload = OpenAIProcessor::createPayloadFromReview($review);
                $result = OpenAIProcessor::processReviewWithOpenAI($payload);
                
                $this->fileWrite("\n✅ OpenAI Analysis Results:\n");
                $this->fileWrite("   Sentiment: " . ($result['sentiment']['label'] ?? 'N/A') . "\n");
                $this->fileWrite("   Emotion: " . ($result['emotion']['primary'] ?? 'N/A') . "\n");
                $this->fileWrite("   Language: " . ($result['language']['detected'] ?? 'N/A') . "\n");
                $this->fileWrite("   Themes: " . count($result['themes'] ?? []) . "\n");
                $this->fileWrite("   Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%\n");
                $this->fileWrite("   Summary: " . ($result['summary']['one_line'] ?? '') . "\n");
                
                // Log results
                $this->fileWrite("Test Analysis Results:\n");
                $this->fileWrite("  Sentiment: " . ($result['sentiment']['label'] ?? 'N/A') . "\n");
                $this->fileWrite("  Emotion: " . ($result['emotion']['primary'] ?? 'N/A') . "\n");
                $this->fileWrite("  Language: " . ($result['language']['detected'] ?? 'N/A') . "\n");
                $this->fileWrite("  Themes Count: " . count($result['themes'] ?? []) . "\n");
                $this->fileWrite("  Confidence: " . round(($result['explainability']['confidence_score'] ?? 0) * 100) . "%\n");
                
                if (isset($result['_metadata']['tokens_used'])) {
                    $tokens = $result['_metadata']['tokens_used'];
                    $this->fileWrite("   Tokens Used: " . $tokens . "\n");
                    $this->fileWrite("  Tokens Used: " . $tokens . "\n");
                }
                
            } else {
                $this->fileWrite("PRODUCTION MODE: Processing and saving results\n");
                $forceReprocess = $this->option('force');
                
                if ($forceReprocess) {
                    $this->fileWrite("Force reprocessing enabled\n");
                }
                
                $result = OpenAIProcessor::analyzeReview($review, $forceReprocess);
                
                if ($result['status'] === 'already_processed' && !$forceReprocess) {
                    $logMessage = "Review #{$review->id} already processed. Skipping.\n";
                    $this->fileWrite($logMessage);
                    
                    $this->fileWrite("\n⚠️  Review already processed!\n");
                    $this->fileWrite("   Sentiment: " . ($result['sentiment_label'] ?? 'N/A') . "\n");
                    $this->fileWrite("   AI Confidence: " . round(($result['ai_confidence'] ?? 0) * 100) . "%\n");
                    $this->fileWrite("   Status: " . ($result['is_abusive'] ? '⚠️ Flagged' : '✅ Active') . "\n");
                    $this->fileWrite("   Use --force flag to reprocess.\n");
                    return;
                }
                
                // Refresh the review from database
                $review->refresh();
                
                $this->fileWrite("\n✅ Review Processed Successfully:\n");
                $this->fileWrite("   Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                $this->fileWrite("   Emotion: " . ($review->emotion ?? 'N/A') . "\n");
                $this->fileWrite("   Key Phrases: " . substr($review->key_phrases ?? '[]', 0, 100) . "\n");
                $this->fileWrite("   AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                $this->fileWrite("   Status: " . ($review->is_abusive ? '⚠️ Flagged' : '✅ Active') . "\n");
                
                // Log detailed results
                $this->fileWrite("Review #{$review->id} processed successfully\n");
                $this->fileWrite("  Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                $this->fileWrite("  Emotion: " . ($review->emotion ?? 'N/A') . "\n");
                $this->fileWrite("  AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                $this->fileWrite("  Is Abusive: " . ($review->is_abusive ? 'Yes' : 'No') . "\n");
                $this->fileWrite("  Status: " . ($result['message'] ?? 'completed') . "\n");
                
                if (isset($result['message'])) {
                    $this->fileWrite("   Status: " . $result['message'] . "\n");
                }
            }
            
        } catch (\Exception $e) {
            $errorMessage = "Processing failed for review #{$review->id}: " . $e->getMessage();
            $this->fileWrite("ERROR: " . $errorMessage . "\n");
            $this->fileWrite("Stack trace: " . $e->getTraceAsString() . "\n");
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
            $this->fileWrite($logMessage);
            return;
        }
        
        $logMessage = "Found {$reviews->count()} reviews to process";
        if ($this->option('force')) {
            $logMessage .= " (force mode: reprocess already processed)";
        }
        $logMessage .= "\n";
        $this->fileWrite($logMessage);
        
        $this->fileWrite("📊 Found {$reviews->count()} reviews to process\n");
        if ($this->option('force')) {
            $this->fileWrite("⚠️  Force mode: Will reprocess already processed reviews\n");
        }
        
        $successCount = 0;
        $failedCount = 0;
        $totalTokens = 0;
        $alreadyProcessed = 0;
        
        $this->fileWrite("Starting batch processing of " . $reviews->count() . " reviews\n");
        
        foreach ($reviews as $index => $review) {
            $this->fileWrite("Processing review " . ($index + 1) . " of " . $reviews->count() . " (ID: {$review->id})\n");
            
            try {
                if ($this->option('test')) {
                    // Test mode
                    $this->fileWrite("Testing review #{$review->id}\n");
                    $payload = OpenAIProcessor::createPayloadFromReview($review);
                    $result = OpenAIProcessor::processReviewWithOpenAI($payload);
                    $tokens = $result['_metadata']['tokens_used'] ?? 0;
                    $totalTokens += $tokens;
                    
                    $logMessage = "Review #{$review->id}: " . 
                                 ($result['sentiment']['label'] ?? 'unknown') . 
                                 " sentiment, " . ($result['themes'][0]['topic'] ?? 'no themes') .
                                 " (Tokens: {$tokens})\n";
                    $this->fileWrite($logMessage);
                    
                } else {
                    // Production mode
                    $forceReprocess = $this->option('force');
                    
                    if ($review->is_ai_processed && !$forceReprocess) {
                        $alreadyProcessed++;
                        $this->fileWrite("Review #{$review->id} already processed, skipping\n");
                    } else {
                        $result = OpenAIProcessor::analyzeReview($review, $forceReprocess);
                        
                        if ($result['status'] === 'already_processed') {
                            $alreadyProcessed++;
                            $this->fileWrite("Review #{$review->id} already processed (via API), skipping\n");
                        } else {
                            $successCount++;
                            $this->fileWrite("Review #{$review->id} processed successfully\n");
                            $this->fileWrite("  Sentiment: " . ($review->sentiment_label ?? 'N/A') . "\n");
                            $this->fileWrite("  AI Confidence: " . round(($review->ai_confidence ?? 0) * 100) . "%\n");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $failedCount++;
                $errorMessage = "Review #{$review->id} failed: " . $e->getMessage();
                $this->fileWrite("ERROR: " . $errorMessage . "\n");
                
                if (!$this->option('test')) {
                    $review->update(['is_ai_processed' => 1]);
                }
            }
            
            // Rate limiting delay
            if (!$this->option('test')) {
                usleep(300000); // 300ms delay
            }
        }
        
        $this->fileWrite("📈 Processing Complete:\n");
        $this->fileWrite("   ✅ Successfully processed: {$successCount}\n");
        
        if ($alreadyProcessed > 0) {
            $this->fileWrite("   ⏭️  Already processed (skipped): {$alreadyProcessed}\n");
        }
        
        $this->fileWrite("   ❌ Failed: {$failedCount}\n");
        
        if ($this->option('test')) {
            $this->fileWrite("   ⚡ Estimated tokens used: {$totalTokens}\n");
            $this->fileWrite("   💰 Estimated cost: $" . number_format($totalTokens * 0.00015 / 1000, 4) . "\n");
        }
        
        if ($failedCount > 0) {
            $this->fileWrite("   ⚠️ Check logs for failed reviews\n");
        }
        
        // Log summary
        $this->fileWrite("\nBatch Processing Summary:\n");
        $this->fileWrite("  Successfully processed: {$successCount}\n");
        $this->fileWrite("  Already processed (skipped): {$alreadyProcessed}\n");
        $this->fileWrite("  Failed: {$failedCount}\n");
        
        if ($this->option('test')) {
            $this->fileWrite("  Estimated tokens used: {$totalTokens}\n");
            $this->fileWrite("  Estimated cost: $" . number_format($totalTokens * 0.00015 / 1000, 4) . "\n");
        }
    }
    
    /**
     * File-based logging helper method
     */
    private function fileWrite($message)
    {
        if ($this->logHandle) {
            fwrite($this->logHandle, $message);
        }
    }
}