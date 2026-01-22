<?php

namespace App\Services\Dashboard;

use App\Http\Utils\DateRangeUtil;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\Business\BusinessAnalyticsService;
use App\Services\Review\ReviewTopicService;
use App\Services\Review\ReviewMetricsService;
use App\Services\Review\ReviewService;
use Illuminate\Validation\ValidationException;

class DashboardService
{
    private ReviewService $reviewService;
    private ReviewMetricsService $reviewMetricsService;
    private ReviewTopicService $reviewTopicService;
    private BusinessAnalyticsService $businessAnalyticsService;
    private AIProcessorService $aiProcessorService;

    public function __construct(
        ReviewService $reviewService,
        ReviewMetricsService $reviewMetricsService,
        ReviewTopicService $reviewTopicService,
        BusinessAnalyticsService $businessAnalyticsService,
        AIProcessorService $aiProcessorService
    ) {
        $this->reviewService = $reviewService;
        $this->reviewMetricsService = $reviewMetricsService;
        $this->reviewTopicService = $reviewTopicService;
        $this->businessAnalyticsService = $businessAnalyticsService;
        $this->aiProcessorService = $aiProcessorService;
    }
    // ==================== PERIOD VALIDATION ====================

    private const FILTERABLE_FIELDS = [
        "last_30_days",
        "last_7_days",
        "this_month",
        "last_month",
        "all_time"
    ];

    /**
     * Validate period and return date range
     * 
     * @param string|null $period
     * @return array|null Returns date range array or null for 'all_time'
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateAndGetDateRange(?string $period = 'last_30_days'): ?array
    {
        $period = $period ?? 'last_30_days';

        if (!in_array($period, self::FILTERABLE_FIELDS)) {
            throw ValidationException::withMessages([
                'period' => 'Invalid period. Only allowed: ' . implode(', ', self::FILTERABLE_FIELDS)
            ]);
        }

        return $period === 'all_time' ? null : getDateRangeByPeriod($period);
    }

    // ==================== DASHBOARD METRICS CALCULATION ====================

    /**
     * Calculate comprehensive dashboard metrics for a business
     * 
     * @param int $businessId
     * @param array|null $dateRange Array with 'start' and 'end' Carbon instances
     * @return array
     */
    public function calculateMetrics($businessId, $dateRange = null, $user)
    {
        // Apply branch filter for branch managers or owner branch switch
        $userBranchId = $user->hasRole('branch_manager') || $user->hasRole('business_owner')
            ? $user->default_branch_id
            : null;



        // ==================== GET REVIEWS USING REVIEW SERVICE ====================

        // Get current and previous period reviews using ReviewService
        $reviewsData = $this->reviewService->getCurrentAndComparisonReviews(
            businessId: $businessId,
            branchId: $userBranchId,
            dateRange: $dateRange
        );

        $reviews = $reviewsData['current'];
        $previousReviews = $reviewsData['previous'];

        // ==================== USE REVIEW METRICS SERVICE ====================

        // Get review counts with comparison
        $reviewCounts = $this->reviewMetricsService->getReviewCountWithComparison($reviews, $previousReviews);
        $total = $reviewCounts['current'];
        $previousTotal = $reviewCounts['previous'];

        // Get rating with comparison  
        $ratingMetrics = $this->reviewMetricsService->getRatingWithComparison($reviews, $previousReviews);
        $currentAvgRating = $ratingMetrics['current'];
        $previousAvgRating = $ratingMetrics['previous'];
        $ratingChange = $ratingMetrics['change'];
        $ratingChangeType = $ratingMetrics['change_type'];

        // Get sentiment with comparison
        $sentimentMetrics = $this->reviewMetricsService->getSentimentWithComparison($reviews, $previousReviews);
        $current_sentiment_score = $sentimentMetrics['score'];
        $previous_sentiment_score = $sentimentMetrics['previous_score'];
        $positiveReviewsCount = $sentimentMetrics['positive'];
        $negativeReviewsCount = $sentimentMetrics['negative'];
        $sentimentChange = $dateRange !== null ? $sentimentMetrics['change'] : null;
        $sentimentChangeType = $sentimentMetrics['change_type'];

        // Top Topic (minimal summary)
        $topTopicSummary = $this->reviewTopicService->getTopTopicSummary($reviews);

        // Detect repeated issues (minimal data only)
        $issuesArray = $this->businessAnalyticsService->extractIssuesFromRuleEngine(
            $businessId,
            $reviews,
            [
                'start' => $reviews->min('created_at'),
                'end' => $reviews->max('created_at')
            ]
        );

        $topIssue = !empty($issuesArray)
            ? $issuesArray[0]['issue']
            : 'No major issues detected';

        // Calculate CSAT Score using ReviewMetricsService
        $csatMetrics = $this->reviewMetricsService->calculateCSATScore(
            businessId: $businessId,
            branchId: $userBranchId,
            dateRange: $dateRange
        );
        $csatPercentage = $csatMetrics['percentage'];
        $csatReviewsCount = $csatMetrics['qualifying_count'];

        // Calculate Flagged Reviews using ReviewMetricsService
        $flaggedMetrics = $this->reviewMetricsService->getFlaggedReviews(
            businessId: $businessId,
            branchId: $userBranchId,
            dateRange: $dateRange
        );
        $flaggedReviewsCount = $flaggedMetrics['count'];

        // Previous period CSAT (only if dateRange provided)
        $previousCSATPercentage = 0;
        $csatPercentageChange = null;
        $csatChangeType = null;

        if ($dateRange !== null) {
            $previousPeriodCSATCount = ReviewNew::where('business_id', $businessId)
                ->whereBetween('created_at', [
                    $dateRange['start']->copy()->subDays(30),
                    $dateRange['end']->copy()->subDays(30)
                ])
                ->whereMeetsThreshold()
                ->globalReviewFilters(0, $businessId)
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
                'change' => $dateRange !== null ? $this->reviewService->calculatePercentageChange(
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
                'change' => $dateRange !== null ? $this->reviewService->calculatePercentageChange($total, $previousTotal) : null
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
                'issue_count' => count($issuesArray),
                'top_issue' => $topIssue,
                'top_issue_details' => !empty($issuesArray) ? [
                    'issue_name' => $issuesArray[0]['issue'],
                    'occurrence_count' => $issuesArray[0]['mention_count'] ?? 0,
                    'severity' => $issuesArray[0]['severity'] ?? 'medium',
                    'confidence' => $issuesArray[0]['confidence'] ?? 0.7,
                ] : null
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
                'action_text' => 'Action Now',
                'review_count' => $total
            ],
            'all_sentiment' => [
                'status' => $reviews->isNotEmpty()
                    ? $this->aiProcessorService->calculateAggregatedSentiment($reviews)['sentiment_label']
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
