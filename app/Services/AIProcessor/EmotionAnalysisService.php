<?php

namespace App\Services\AIProcessor;

use App\Models\{ReviewEmotion, RatingMismatch};

/**
 * Service for emotion detection and rating mismatch analysis
 * Handles sentiment analysis extensions including emotion intensity detection
 */
class EmotionAnalysisService
{
    // ==================== EMOTION DETECTION ====================

    /**
     * Detect emotions with intensity
     * 
     * @param string $text Review comment
     * @param float $sensitivity Sensitivity level (0.0 to 1.0)
     * @return array Emotions with intensity scores
     */
    public static function detectEmotions(string $text, float $sensitivity = 0.7): array
    {
        $emotionKeywords = [
            'joy' => [
                'happy',
                'delighted',
                'wonderful',
                'amazing',
                'excellent',
                'love',
                'loved',
                'thrilled',
                'fantastic',
                'awesome',
                'brilliant',
                'superb',
                'outstanding',
                'perfect',
                'incredible',
                'enjoyable',
                'pleasant',
                'great',
                'beautiful'
            ],
            'anger' => [
                'angry',
                'furious',
                'outraged',
                'disgusted',
                'terrible',
                'horrible',
                'worst',
                'hate',
                'hated',
                'awful',
                'appalling',
                'unacceptable',
                'ridiculous',
                'pathetic',
                'shameful',
                'infuriating',
                'enraging',
                'offensive'
            ],
            'frustration' => [
                'frustrated',
                'annoying',
                'irritating',
                'disappointing',
                'slow',
                'waited',
                'finally',
                'still waiting',
                'took forever',
                'never',
                'always',
                'every time',
                'again',
                'repeatedly',
                'constant',
                'continual',
                'endless'
            ],
            'satisfaction' => [
                'satisfied',
                'pleased',
                'good',
                'nice',
                'comfortable',
                'smooth',
                'easy',
                'efficient',
                'professional',
                'polite',
                'helpful',
                'friendly',
                'welcoming',
                'accommodating',
                'attentive',
                'responsive',
                'reliable'
            ],
            'disappointment' => [
                'disappointed',
                'expected better',
                'not worth',
                'overpriced',
                'mediocre',
                'underwhelming',
                'letdown',
                'below expectations',
                'could be better',
                'lacking',
                'subpar',
                'not impressed',
                'unfortunate',
                'regret'
            ]
        ];

        $emotions = [];
        $textLower = strtolower($text);
        $wordCount = str_word_count($textLower);

        foreach ($emotionKeywords as $emotion => $keywords) {
            $score = 0;
            $matchCount = 0;
            $matchedKeywords = [];

            foreach ($keywords as $keyword) {
                if (str_contains($textLower, $keyword)) {
                    $matchCount++;
                    $matchedKeywords[] = $keyword;

                    // Stronger match for exact words vs partial
                    $wordValue = str_word_count($keyword);
                    $score += $wordValue * 0.15;
                }
            }

            if ($matchCount > 0) {
                // Calculate intensity based on match density
                $density = $matchCount / max(1, $wordCount / 10);
                $intensity = min(1.0, $score + ($density * 0.3));

                // Apply sensitivity threshold
                $threshold = 1.0 - $sensitivity;

                if ($intensity >= $threshold) {
                    $emotions[$emotion] = [
                        'score' => round($intensity, 2),
                        'intensity' => self::getIntensityLevel($intensity),
                        'match_count' => $matchCount,
                        'keywords' => array_slice($matchedKeywords, 0, 5) // Limit to 5 keywords
                    ];
                }
            }
        }

        return $emotions;
    }

    /**
     * Get intensity level from score
     * 
     * @param float $score Intensity score (0.0 to 1.0)
     * @return string Intensity level (low, medium, high)
     */
    private static function getIntensityLevel(float $score): string
    {
        if ($score >= 0.7)
            return 'high';
        if ($score >= 0.4)
            return 'medium';
        return 'low';
    }

    /**
     * Store emotions in database
     * 
     * @param int $reviewId Review ID
     * @param array $emotions Detected emotions
     * @return void
     */
    public static function storeEmotions(int $reviewId, array $emotions): void
    {
        foreach ($emotions as $emotion => $data) {
            ReviewEmotion::create([
                'review_id' => $reviewId,
                'emotion' => $emotion,
                'intensity_score' => $data['score'],
                'intensity_level' => $data['intensity'],
                'confidence' => $data['score'], // Using score as confidence for now
                'keywords_matched' => $data['keywords'] ?? []
            ]);
        }
    }

    // ==================== RATING MISMATCH DETECTION ====================

    /**
     * Detect rating-comment mismatch
     * 
     * @param float $rating Star rating (1-5)
     * @param string $comment Review comment
     * @param array $aiData AI analysis data (sentiment, emotions)
     * @return array Mismatch detection result
     */
    public static function detectRatingMismatch(float $rating, string $comment, array $aiData): array
    {
        $sentiment = $aiData['sentiment'] ?? 'neutral';
        $sentimentScore = $aiData['sentiment_score'] ?? 0.5;

        $isMismatch = false;
        $mismatchType = null;
        $severity = 'none';
        $explanation = '';

        // High rating (4-5 stars) with negative sentiment
        if ($rating >= 4.0 && in_array($sentiment, ['negative', 'very_negative'])) {
            $isMismatch = true;
            $mismatchType = 'high_rating_negative_comment';
            $severity = self::calculateMismatchSeverity($rating, $sentimentScore, 'high_negative');
            $explanation = "Customer gave {$rating} stars but expressed negative sentiment in comments";
        }

        // Low rating (1-2 stars) with positive sentiment
        if ($rating <= 2.0 && in_array($sentiment, ['positive', 'very_positive'])) {
            $isMismatch = true;
            $mismatchType = 'low_rating_positive_comment';
            $severity = self::calculateMismatchSeverity($rating, $sentimentScore, 'low_positive');
            $explanation = "Customer gave {$rating} stars but expressed positive sentiment in comments";
        }

        // Medium rating (3 stars) with extreme sentiment
        if ($rating >= 2.5 && $rating <= 3.5) {
            if ($sentimentScore >= 0.8 || $sentimentScore <= 0.2) {
                $isMismatch = true;
                $mismatchType = 'neutral_rating_extreme_sentiment';
                $severity = 'low';
                $explanation = "Customer gave neutral rating ({$rating} stars) but comments show extreme sentiment";
            }
        }

        return [
            'is_mismatch' => $isMismatch,
            'mismatch_type' => $mismatchType,
            'severity' => $severity,
            'explanation' => $explanation,
            'rating' => $rating,
            'sentiment' => $sentiment,
            'sentiment_score' => $sentimentScore,
            'suggested_action' => $isMismatch ? 'manual_review_recommended' : null,
            'confidence' => self::calculateMismatchConfidence($rating, $sentimentScore, $isMismatch)
        ];
    }

    /**
     * Calculate mismatch severity
     * 
     * @param float $rating Star rating
     * @param float $sentimentScore Sentiment score (0-1)
     * @param string $type Mismatch type
     * @return string Severity level (low, medium, high)
     */
    private static function calculateMismatchSeverity(float $rating, float $sentimentScore, string $type): string
    {
        if ($type === 'high_negative') {
            // High rating but negative sentiment
            $gap = abs(($rating / 5.0) - $sentimentScore);
        } else {
            // Low rating but positive sentiment
            $gap = abs((1 - $rating / 5.0) - $sentimentScore);
        }

        if ($gap >= 0.6)
            return 'high';
        if ($gap >= 0.4)
            return 'medium';
        return 'low';
    }

    /**
     * Calculate mismatch detection confidence
     * 
     * @param float $rating Star rating
     * @param float $sentimentScore Sentiment score
     * @param bool $isMismatch Whether mismatch was detected
     * @return float Confidence score (0-1)
     */
    private static function calculateMismatchConfidence(float $rating, float $sentimentScore, bool $isMismatch): float
    {
        if (!$isMismatch) {
            return 0.0;
        }

        // Higher confidence for larger gaps between rating and sentiment
        $normalizedRating = $rating / 5.0;
        $gap = abs($normalizedRating - $sentimentScore);

        // Confidence increases with gap size
        return min(1.0, $gap * 1.5);
    }

    /**
     * Store mismatch in database
     * 
     * @param int $reviewId Review ID
     * @param int $businessId Business ID
     * @param array $mismatchData Mismatch detection result
     * @return void
     */
    public static function storeMismatch(int $reviewId, int $businessId, array $mismatchData): void
    {
        if (!$mismatchData['is_mismatch']) {
            return;
        }

        RatingMismatch::create([
            'review_id' => $reviewId,
            'business_id' => $businessId,
            'mismatch_type' => $mismatchData['mismatch_type'],
            'severity' => $mismatchData['severity'],
            'rating' => $mismatchData['rating'],
            'detected_sentiment' => $mismatchData['sentiment'],
            'sentiment_score' => $mismatchData['sentiment_score'],
            'explanation' => $mismatchData['explanation'],
            'status' => 'pending'
        ]);
    }
}
