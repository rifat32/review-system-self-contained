<?php

namespace App\Console\Commands;

use App\Models\ReviewNew;
use Illuminate\Console\Command;

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
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $reviews = ReviewNew::where('is_ai_processed', false)->get();

        foreach ($reviews as $review) {
            $raw_text = $review->raw_text;
               // Step 2: AI Moderation Pipeline
        $moderation_results = aiModeration($raw_text);

        // Step 3: AI Sentiment Analysis
        $sentiment_score = analyzeSentiment($raw_text);

        // Step 4: AI Topic Extraction
        $topics = extractTopics($raw_text);

        // Step 5: AI Staff Performance Scoring
        $staff_suggestions = analyzeStaffPerformance($raw_text, $review->staff_id);
        

        // Step 6: AI Recommendations Engine
        $ai_suggestions = generateRecommendations( $topics, $sentiment_score);

        $emotion = detectEmotion($raw_text);
        $key_phrases = extractKeyPhrases($raw_text);


            $review->moderation_results = $moderation_results;
            $review->sentiment_score = $sentiment_score;
            $review->topics = $topics;
            $review->ai_suggestions = $ai_suggestions;
            $review->staff_suggestions = $staff_suggestions;
            $review->emotion = $emotion;
            $review->key_phrases = $key_phrases;
            $review->is_ai_processed = true;
            $review->save();


        }

       
    }
}
