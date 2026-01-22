<?php

namespace App\Services\Branch;

use App\Models\Branch;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Business\BusinessAnalyticsService;
use App\Services\Review\ReviewTopicService;
use App\Services\Review\ReviewMetricsService;
use App\Services\Review\ReviewService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BranchService
{
    private ReviewService $reviewService;
    private ReviewMetricsService $reviewMetricsService;
    private ReviewTopicService $reviewTopicService;
    private BusinessAnalyticsService $businessAnalyticsService;

    public function __construct(
        ReviewService $reviewService,
        ReviewMetricsService $reviewMetricsService,
        ReviewTopicService $reviewTopicService,
        BusinessAnalyticsService $businessAnalyticsService
    ) {
        $this->reviewService = $reviewService;
        $this->reviewMetricsService = $reviewMetricsService;
        $this->reviewTopicService = $reviewTopicService;
        $this->businessAnalyticsService = $businessAnalyticsService;
    }

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

    /**
     * Get comprehensive branch metrics with comparison
     * 
     * @param int $branchId Branch ID (required)
     * @param array|null $dateRange Date range array with 'start' and 'end' keys
     * @param User|null $user User for permission checking
     * @return array
     */
    public function getBranchMetricsWithComparison(
        int $branchId,
        ?array $dateRange = null,
        ?User $user = null
    ): array {
        // Get branch with business relationship
        $branch = Branch::with(['business'])->findOrFail($branchId);
        $businessId = $branch->business_id;

        // ==================== GET REVIEWS USING REVIEW SERVICE ====================

        // Get current and previous period reviews using ReviewService
        $reviews = $this->reviewService->getCurrentAndComparisonReviews(
            businessId: $businessId,
            branchId: $branchId,
            dateRange: $dateRange
        );

        $currentReviews = $reviews['current'];
        $previousReviews = $reviews['previous'];

        // ==================== BASIC COUNTS ====================
        $reviewCounts = $this->reviewMetricsService->getReviewCountWithComparison($currentReviews, $previousReviews);

        // ==================== AVERAGE RATING ====================
        $ratingMetrics = $this->reviewMetricsService->getRatingWithComparison($currentReviews, $previousReviews);

        // ==================== SENTIMENT ANALYSIS ====================
        $sentimentMetrics = $this->reviewMetricsService->getSentimentWithComparison($currentReviews, $previousReviews);

        // ==================== CSAT SCORE ====================
        $csatMetrics = $this->reviewMetricsService->calculateCSATScore($businessId, $branchId, $dateRange);

        // ==================== FLAGGED REVIEWS ====================
        $flaggedMetrics = $this->reviewMetricsService->getFlaggedReviews($businessId, $branchId, $dateRange);

        // ==================== TOP TOPICS ====================
        $topTopicSummary = $this->reviewTopicService->getTopTopicSummary($currentReviews);

        // ==================== REPEATED ISSUES ====================
        $issueAnalysis = $this->businessAnalyticsService->extractIssuesFromRuleEngine(
            $businessId,
            $currentReviews,
            [
                'start' => $currentReviews->min('created_at') ?? \now()->subMonth(),
                'end' => $currentReviews->max('created_at') ?? \now()
            ]
        );

        $topIssue = !empty($issueAnalysis['repeated_issues'])
            ? $issueAnalysis['repeated_issues'][0]['issue']
            : null;

        $repeatedIssuesCount = count($issueAnalysis['repeated_issues'] ?? []);

        // ==================== STAFF METRICS ====================
        $staffMetrics = $this->reviewMetricsService->getStaffCountWithComparison($currentReviews, $previousReviews);

        // ==================== BUILD RESPONSE ====================
        return [
            'branch_info' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'location' => $branch->location,
                'business_id' => $businessId,
                'business_name' => $branch->business->Name ?? null
            ],
            'period_info' => [
                'date_range' => $dateRange,
                'is_comparison_enabled' => $dateRange !== null
            ],
            'total_reviews' => $reviewCounts,
            'average_rating' => $ratingMetrics,
            'sentiment' => $sentimentMetrics,
            'csat_score' => $csatMetrics,
            'flagged_reviews' => $flaggedMetrics,
            'top_topic' => $topTopicSummary,
            'repeated_issues' => [
                'count' => $repeatedIssuesCount,
                'top_issue' => $topIssue,
                'all_issues' => $issueAnalysis['repeated_issues'] ?? []
            ],
            'staff_metrics' => $staffMetrics
        ];
    }


    /**
     * Get top performing staff for a branch
     */
    public function getTopStaff(
        int $branchId,
        int $businessId,
        ?array $dateRange = null,
        int $limit = 5
    ): array {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->whereNotNull('staff_id')
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange['start'])->startOfDay();
                $endDate = Carbon::parse($dateRange['end'])->endOfDay();
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->globalReviewFilters(0, $businessId)
            ->withCalculatedRating()
            ->with('staff:id,first_Name,last_Name,image')
            ->get();

        // Group by staff and calculate metrics
        $staffMetrics = $reviews->groupBy('staff_id')->map(function ($staffReviews) {
            $staff = $staffReviews->first()->staff;
            $avgRating = $staffReviews->avg('calculated_rating');
            $avgSentiment = $staffReviews->avg('sentiment_score');

            return [
                'staff_id' => $staff->id,
                'staff_name' => $staff->first_Name . ' ' . $staff->last_Name,
                'staff_image' => $staff->image,
                'avg_rating' => round($avgRating, 2),
                'avg_sentiment' => round($avgSentiment, 2),
                'review_count' => $staffReviews->count(),
                'positive_reviews' => $staffReviews->filter(function ($r) {
                    return $r->sentiment_label === 'positive' || ($r->sentiment_label === null && $r->sentiment_score >= \App\Services\Rule\RuleEngineService::getPositiveSentimentThreshold());
                })->count(),
                'negative_reviews' => $staffReviews->filter(function ($r) {
                    return $r->sentiment_label === 'negative' || ($r->sentiment_label === null && $r->sentiment_score <= \App\Services\Rule\RuleEngineService::getNegativeSentimentThreshold());
                })->count(),
            ];
        })->sortByDesc('avg_rating')->take($limit)->values()->toArray();

        return $staffMetrics;
    }
}
