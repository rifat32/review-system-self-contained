<?php

namespace App\Services;

use App\Http\Utils\DateRangeUtil;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\review\ReviewIssueDetectionService;
use App\Services\review\ReviewTopicService;

class DashboardService
{
    // ==================== DASHBOARD METRICS CALCULATION ====================

    /**
     * Calculate comprehensive dashboard metrics for a business
     * 
     * @param int $businessId
     * @param array|null $dateRange Array with 'start' and 'end' Carbon instances
     * @return array
     */
    public function calculateMetrics($businessId, $dateRange = null)
    {
        // Get current period reviews WITH calculated rating
        $reviewsQuery = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId)
            ->withCalculatedRating();

        // Apply date filter only if dateRange is provided
        if ($dateRange !== null) {
            $reviewsQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $reviews = $reviewsQuery->get();

        // Get previous period reviews WITH calculated rating (only if dateRange provided)
        $previousReviews = collect();
        $previousAvgRating = 0;
        $previousTotal = 0;
        $previous_sentiment_score = 0;

        if ($dateRange !== null) {
            $previousReviews = ReviewNew::globalFilters(0, $businessId)
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [
                    $dateRange['start']->copy()->subDays(30),
                    $dateRange['end']->copy()->subDays(30)
                ])
                ->globalFilters(0, $businessId)
                ->withCalculatedRating()
                ->get();

            $previousTotal = $previousReviews->count();

            // Calculate previous period ratings FROM calculated_rating field
            $previousAvgRating = $previousReviews->isNotEmpty()
                ? round($previousReviews->avg('calculated_rating'), 1)
                : 0;

            // Calculate sentiment scores (still from ReviewNew)
            $previous_sentiment_score = $previousReviews->avg('sentiment_score') ?? 0;
        }

        $total = $reviews->count();

        // Calculate current period ratings FROM calculated_rating field
        $currentAvgRating = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate sentiment scores (still from ReviewNew)
        $current_sentiment_score = $reviews->avg('sentiment_score') ?? 0;

        // Calculate positive/negative counts based on calculated_rating
        $positiveReviewsCount = $reviews->where('calculated_rating', '>=', 4)->count();
        $negativeReviewsCount = $reviews->where('calculated_rating', '<=', 2)->count();

        // Top Topic (minimal summary)
        $topTopicSummary = ReviewTopicService::getTopTopicSummary($reviews);

        // Detect repeated issues (minimal data only)
        $issueAnalysis = ReviewIssueDetectionService::detectRepeatedIssues($reviews, [
            'min_occurrences' => 3,
            'min_percentage' => 5,
            'include_trend' => false  // Disable trend for performance
        ]);

        $topIssue = !empty($issueAnalysis['repeated_issues'])
            ? $issueAnalysis['repeated_issues'][0]['issue']
            : null;

        // Calculate change percentage and type for sentiment score
        $sentimentChange = $dateRange !== null ? calculatePercentageChange(
            $current_sentiment_score,
            $previous_sentiment_score
        ) : null;

        $sentimentChangeType = null;
        if ($sentimentChange !== null) {
            if ($sentimentChange > 0) {
                $sentimentChangeType = 'positive';
            } elseif ($sentimentChange < 0) {
                $sentimentChangeType = 'negative';
            } else {
                $sentimentChangeType = 'neutral';
            }
        }

        // Calculate change percentage and type for average overall rating
        $ratingChange = $dateRange !== null ? calculatePercentageChange(
            $currentAvgRating,
            $previousAvgRating
        ) : null;

        $ratingChangeType = null;
        if ($ratingChange !== null) {
            if ($ratingChange > 0) {
                $ratingChangeType = 'positive';
            } elseif ($ratingChange < 0) {
                $ratingChangeType = 'negative';
            } else {
                $ratingChangeType = 'neutral';
            }
        }


        // Calculate CSAT Score (percentage of reviews meeting threshold)
        $baseReviewQuery = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Current period CSAT
        $csatReviewsCount = 0;
        $flaggedReviewsCount = 0;

        if ($dateRange !== null) {
            $csatReviewsCount = (clone $baseReviewQuery)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->whereMeetsThreshold($businessId)
                ->count();

            $flaggedReviewsCount = (clone $baseReviewQuery)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->whereDoesNotMeetsThreshold($businessId)
                ->count();
        } else {
            // All time
            $csatReviewsCount = (clone $baseReviewQuery)
                ->whereMeetsThreshold($businessId)
                ->count();

            $flaggedReviewsCount = (clone $baseReviewQuery)
                ->whereDoesNotMeetsThreshold($businessId)
                ->count();
        }

        $csatPercentage = $total > 0 ? round(($csatReviewsCount / $total) * 100) : 0;

        // Previous period CSAT (only if dateRange provided)
        $previousCSATPercentage = 0;
        $csatPercentageChange = null;
        $csatChangeType = null;

        if ($dateRange !== null) {
            $previousPeriodCSATCount = ReviewNew::globalFilters(0, $businessId)
                ->where('business_id', $businessId)
                ->whereBetween('created_at', [
                    $dateRange['start']->copy()->subDays(30),
                    $dateRange['end']->copy()->subDays(30)
                ])
                ->whereMeetsThreshold($businessId)
                ->count();

            $previousCSATPercentage = $previousTotal > 0
                ? round(($previousPeriodCSATCount / $previousTotal) * 100)
                : 0;

            $csatPercentageChange = $previousCSATPercentage > 0
                ? round($csatPercentage - $previousCSATPercentage, 1)
                : ($csatPercentage > 0 ? $csatPercentage : 0);

            $csatChangeType = $csatPercentageChange >= 0 ? 'increase' : 'decrease';
        }

        return [
            'avg_overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $dateRange !== null ? calculatePercentageChange(
                    $currentAvgRating,
                    $previousAvgRating
                ) : null,
                'change_type' => $ratingChangeType,
                'previous_value' => $previousAvgRating,
                'calculated_from' => 'review_value_news (via calculated_rating)',
                'review_count' => $total
            ],
            'ai_sentiment_score' => [
                'value' => round($current_sentiment_score * 10, 1),
                'max' => 10,
                'change' => $sentimentChange,
                'change_type' => $sentimentChangeType,
                'previous_value' => round($previous_sentiment_score * 10, 1),
                'review_count' => $total
            ],
            'total_reviews' => [
                'value' => $total,
                'change' => $dateRange !== null ? calculatePercentageChange($total, $previousTotal) : null
            ],
            'positive_negative_ratio' => [
                'positive' => $total > 0 ? round(($positiveReviewsCount / $total) * 100) : 0,
                'negative' => $total > 0 ? round(($negativeReviewsCount / $total) * 100) : 0,
                'positive_count' => $positiveReviewsCount,
                'negative_count' => $negativeReviewsCount,
                'review_count' => $total
            ],
            'staff_linked_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->whereNotNull('staff_id')->count() / $total) * 100) : 0,
                'count' => $reviews->whereNotNull('staff_id')->count(),
                'total' => $total,
                'review_count' => $total
            ],
            'voice_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->where('is_voice_review', true)->count() / $total) * 100) : 0,
                'count' => $reviews->where('is_voice_review', true)->count(),
                'total' => $total,
                'review_count' => $total
            ],
            'rating_distribution' => [
                '5_star' => $reviews->where('calculated_rating', '>=', 4.5)->count(),
                '4_star' => $reviews->whereBetween('calculated_rating', [4.0, 4.49])->count(),
                '3_star' => $reviews->whereBetween('calculated_rating', [3.0, 3.99])->count(),
                '2_star' => $reviews->whereBetween('calculated_rating', [2.0, 2.99])->count(),
                '1_star' => $reviews->where('calculated_rating', '<', 2.0)->count()
            ],
            'top_topic' => $topTopicSummary,
            'repeated_issues' => [
                'review_count' => $total,
                'issue_count' => $issueAnalysis['total_issues_found'],
                'top_issue' => $topIssue
            ],
            'csat_score' => [
                'percentage' => $csatPercentage,
                'percentage_change' => $csatPercentageChange !== null
                    ? ($csatPercentageChange != 0 ? sprintf('%+.1f%%', $csatPercentageChange) : '0%')
                    : null,
                'change_type' => $csatChangeType,
                'previous_percentage' => $dateRange !== null ? $previousCSATPercentage : null,
                'review_count' => $total,
                'csat_review_count' => $csatReviewsCount
            ],
            'flagged_reviews' => [
                'count' => $flaggedReviewsCount,
                'action_text' => 'Review Now',
                'review_count' => $total
            ],
            'all_sentiment' => [
                'status' => $reviews->isNotEmpty()
                    ? calculateAggregatedSentiment($reviews)['sentiment_label']
                    : 'neutral',
                'based_on' => $dateRange !== null
                    ? 'Based on selected period'
                    : 'Based on all time data',
                'review_count' => $total
            ]
        ];
    }

    // ==================== CUSTOMER METRICS ====================

    public function getCustomersByPeriod($period, $business)
    {
        $dateRange = DateRangeUtil::getDateRange($period);
        $start = $dateRange['start_date'];
        $end = $dateRange['end_date'];

        // Placeholder logic - replace with actual customer filtering based on business and date range
        $first_time_customers = User::distinct()->get();
        $returning_customers = User::distinct()->get();

        // Return the results
        return [
            'first_time_customers' => $first_time_customers,
            'returning_customers' => $returning_customers,
        ];
    }
}
