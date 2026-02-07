<?php

namespace App\Services\Rule;

use App\Models\AiRule;
use App\Models\ReviewNew;
use App\Models\InsightRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RuleReportService
{
    /**
     * Get or recreate a default rule, ensuring it exists
     */
    public function getDefaultRule(string $ruleKey, int $businessId): AiRule
    {
        $ruleId = $ruleKey . '.' . $businessId;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('is_default', true)
            ->first();

        throw new \Exception("Default rule '{$ruleKey}' not found for business {$businessId}.");

        return $rule;
    }

    /**
     * Get sentiment analysis report
     */
    public function getSentimentAnalysisReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('SENTIMENT_ANALYSIS', $businessId);

        $query = ReviewNew::where('business_id', $businessId)->globalReviewFilters(0);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $reviews = $query->get();

        $sentimentCounts = [
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0
        ];

        foreach ($reviews as $review) {
            $sentiment = $review->sentiment_label;

            // Fallback to score if label is missing
            if (!$sentiment && isset($review->sentiment_score)) {
                $sentiment = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score);
            }

            $sentiment = $sentiment ?: 'neutral';

            if (isset($sentimentCounts[$sentiment])) {
                $sentimentCounts[$sentiment]++;
            }
        }

        $total = $reviews->count();

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate,
                'conditions' => $rule->conditions
            ],
            'summary' => [
                'total_reviews_analyzed' => $total,
                'positive_count' => $sentimentCounts['positive'],
                'neutral_count' => $sentimentCounts['neutral'],
                'negative_count' => $sentimentCounts['negative'],
                'positive_percentage' => $total > 0 ? round(($sentimentCounts['positive'] / $total) * 100, 2) : 0,
                'neutral_percentage' => $total > 0 ? round(($sentimentCounts['neutral'] / $total) * 100, 2) : 0,
                'negative_percentage' => $total > 0 ? round(($sentimentCounts['negative'] / $total) * 100, 2) : 0,
            ],
            'trends' => $this->getSentimentTrends($businessId, $startDate, $endDate),
            'sample_reviews' => [
                'positive' => $reviews->filter(function ($r) {
                    return $r->sentiment_label === 'positive' || ($r->sentiment_label === null && $r->sentiment_score >= RuleEngineService::getPositiveSentimentThreshold());
                })->take(5)->values(),
                'negative' => $reviews->filter(function ($r) {
                    return $r->sentiment_label === 'negative' || ($r->sentiment_label === null && $r->sentiment_score <= RuleEngineService::getNegativeSentimentThreshold());
                })->take(5)->values()
            ]
        ];
    }

    /**
     * Get emotion intensity report
     */
    public function getEmotionIntensityReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('EMOTION_INTENSITY', $businessId);

        // This would query InsightRecords or AI analysis data
        // For now, returning structure
        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate
            ],
            'summary' => [
                'total_high_intensity_reviews' => 0,
                'percentage_of_total' => 0,
                'average_intensity_score' => 0,
                'emotions_detected' => []
            ],
            'trends' => [],
            'sample_reviews' => []
        ];
    }

    /**
     * Get rating/comment mismatch report
     */
    public function getRatingCommentMismatchReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('RATING_COMMENT_MISMATCH', $businessId);

        $query = ReviewNew::where('business_id', $businessId)->globalReviewFilters(0);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $reviews = $query->get();

        $highRatingNegativeComment = $reviews->filter(function ($review) use ($businessId) {
            $label = $review->sentiment_label;
            if (!$label && isset($review->sentiment_score)) {
                $label = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score, $businessId);
            }
            return ($review->calculated_rating ?? 0) >= (config('ai.insights.opportunities.reporting.mismatch_high_rating') ?? 4) && $label === 'negative';
        });

        $lowRatingPositiveComment = $reviews->filter(function ($review) use ($businessId) {
            $label = $review->sentiment_label;
            if (!$label && isset($review->sentiment_score)) {
                $label = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score, $businessId);
            }
            return ($review->calculated_rating ?? 0) <= (config('ai.insights.opportunities.reporting.mismatch_low_rating') ?? 2) && $label === 'positive';
        });

        $totalMismatches = $highRatingNegativeComment->count() + $lowRatingPositiveComment->count();

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate
            ],
            'summary' => [
                'total_mismatches' => $totalMismatches,
                'high_rating_negative_comment' => $highRatingNegativeComment->count(),
                'low_rating_positive_comment' => $lowRatingPositiveComment->count(),
                'percentage_of_total_reviews' => $reviews->count() > 0 ? round(($totalMismatches / $reviews->count()) * 100, 2) : 0
            ],
            'mismatch_patterns' => [
                [
                    'rating_range' => '4-5',
                    'negative_comments' => $highRatingNegativeComment->count(),
                    'common_issues' => []
                ]
            ],
            'sample_reviews' => $highRatingNegativeComment->take(10)->values()
        ];
    }

    /**
     * Get flagged reviews report
     */
    public function getFlaggedReviewsReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('FLAG_AND_ALERT', $businessId);

        $query = ReviewNew::where('business_id', $businessId)
            ->where('is_flagged', true)
            ->globalReviewFilters(0);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $flaggedReviews = $query->get();

        $criticalFlags = $flaggedReviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) < (config('ai.insights.opportunities.reporting.mismatch_low_rating') ?? 2);
        });

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate
            ],
            'summary' => [
                'total_flagged' => $flaggedReviews->count(),
                'critical_flags' => $criticalFlags->count(),
                'pending_response' => $flaggedReviews->whereNull('responded_at')->count(),
                'resolved' => $flaggedReviews->whereNotNull('responded_at')->count()
            ],
            'flagged_reviews' => $flaggedReviews->map(function ($review) {
                return [
                    'review_id' => $review->id,
                    'rating' => $review->calculated_rating,
                    'flag_reason' => 'Very low rating',
                    'flagged_at' => $review->created_at,
                    'status' => $review->responded_at ? 'resolved' : 'pending',
                    'priority' => ($review->calculated_rating ?? 0) < (config('ai.insights.opportunities.reporting.mismatch_low_rating') ?? 2) ? 'critical' : 'high'
                ];
            })->values(),
            'trends' => [],
            'response_time_analysis' => [
                'avg_time_to_respond' => 'N/A',
                'fastest_response' => 'N/A',
                'slowest_response' => 'N/A'
            ]
        ];
    }

    /**
     * Helper: Get sentiment trends over time
     */
    private function getSentimentTrends(int $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = ReviewNew::where('business_id', $businessId)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN sentiment_label = "positive" THEN 1 ELSE 0 END) as positive'),
                DB::raw('SUM(CASE WHEN sentiment_label = "neutral" THEN 1 ELSE 0 END) as neutral'),
                DB::raw('SUM(CASE WHEN sentiment_label = "negative" THEN 1 ELSE 0 END) as negative')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->globalReviewFilters(0)
            ->limit(config('ai.insights.opportunities.reporting.trend_limit_days') ?? 30);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->toArray();
    }

    /**
     * Generic method to get basic stats for any default rule
     */
    public function getBasicRuleReport(string $ruleKey, int $businessId): array
    {
        $rule = $this->getDefaultRule($ruleKey, $businessId);

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'description' => $rule->description,
                'precision_rate' => $rule->precision_rate,
                'category' => $rule->category,
                'priority' => $rule->priority,
                'enabled' => $rule->enabled
            ],
            'summary' => [
                'message' => 'This report is under development. Rule configuration loaded successfully.'
            ]
        ];
    }
}
