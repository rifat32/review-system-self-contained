<?php

namespace App\Services\Staff;

use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Rule\RuleEngineService;
use App\Services\AIProcessor\AIProcessorService;
use Carbon\Carbon;

class StaffService
{
    private RuleEngineService $ruleEngineService;
    private AIProcessorService $aiProcessorService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        AIProcessorService $aiProcessorService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->aiProcessorService = $aiProcessorService;
    }

    /**
     * Get reviews for current period
     */
    public function getCurrentPeriodReviews(
        int $businessId,
        array $dateRange = null,
        ?User $user = null
    ) {
        $userBranchId = $user && ($user->hasRole('branch_manager') || $user->hasRole('business_owner')) ? $user->default_branch_id : null;

        return ReviewNew::where('review_news.business_id', $businessId)
            ->whereNotNull('staff_id')
            ->globalReviewFilters(0)
            ->when($dateRange, function ($query) use ($dateRange) {
                $start = \Carbon\Carbon::parse($dateRange['start'])->startOfDay();
                $end = \Carbon\Carbon::parse($dateRange['end'])->endOfDay();
                return $query->whereBetween('review_news.created_at', [$start, $end]);
            })
            ->when($userBranchId, function ($query) use ($userBranchId) {
                return $query->where('review_news.branch_id', $userBranchId);
            })
            ->withCalculatedRating()
            ->get();
    }

    /**
     * Get reviews for comparison (previous) period
     */
    public function getComparisonPeriodReviews(
        int $businessId,
        array $dateRange = null,
        ?User $user = null
    ) {
        $userBranchId = $user && ($user->hasRole('branch_manager') || $user->hasRole('business_owner')) ? $user->default_branch_id : null;

        // If no date range, we can't do comparison
        if (!$dateRange) {
            return \collect();
        }

        $daysOffset = $dateRange['daysOffset'] ?? 30;

        return ReviewNew::where('review_news.business_id', $businessId)
            ->whereNotNull('staff_id')
            ->globalReviewFilters(0) // Always ignore automatic date range for comparison
            ->when($dateRange, function ($query) use ($dateRange, $daysOffset) {
                $start = \Carbon\Carbon::parse($dateRange['start'])->subDays($daysOffset)->startOfDay();
                $end = \Carbon\Carbon::parse($dateRange['end'])->subDays($daysOffset)->endOfDay();
                return $query->whereBetween('review_news.created_at', [$start, $end]);
            })
            ->when($userBranchId, function ($query) use ($userBranchId) {
                return $query->where('review_news.branch_id', $userBranchId);
            })
            ->withCalculatedRating()
            ->get();
    }


    /**
     * Get staff metrics with period comparison
     *
     * @param int $businessId Business ID
     * @param array|null $dateRange Date range array with 'start' and 'end' keys
     * @param User|null $user User for branch filtering
     * @return array
     */
    public function getStaffMetricsWithComparison(
        int $businessId,
        ?array $dateRange = null,
        ?User $user = null
    ): array {

        // Get current period reviews
        $currentReviews = $this->getCurrentPeriodReviews(
            businessId: $businessId,
            dateRange: $dateRange,
            user: $user
        );


        // Get comparison period reviews
        $comparisonReviews = $this->getComparisonPeriodReviews(
            businessId: $businessId,
            dateRange: $dateRange,
            user: $user
        );


        // Calculate staff counts
        $currentStaffCount = $currentReviews->pluck('staff_id')->unique()->count();
        $comparisonStaffCount = $comparisonReviews->pluck('staff_id')->unique()->count();
        $staffCountChange = $currentStaffCount - $comparisonStaffCount;

        // Calculate review counts
        $currentReviewCount = $currentReviews->count();
        $comparisonReviewCount = $comparisonReviews->count();
        $reviewCountChange = $currentReviewCount - $comparisonReviewCount;

        // Calculate metrics using existing helper function
        $overallMetrics = $this->calculateOverallMetricsFromReviewValue($currentReviews, $comparisonReviews);

        // Enhance metrics with staff_count and review_count
        if (isset($overallMetrics['overall_rating'])) {
            $overallMetrics['overall_rating']['staff_count'] = $currentStaffCount;
            $overallMetrics['overall_rating']['review_count'] = $currentReviewCount;
        }

        if (isset($overallMetrics['overall_sentiment'])) {
            $overallMetrics['overall_sentiment']['staff_count'] = $currentStaffCount;
            $overallMetrics['overall_sentiment']['review_count'] = $currentReviewCount;
        }

        // Add total_staff metric
        $overallMetrics['total_staff'] = [
            'value' => $currentStaffCount,
            'previous_value' => $comparisonStaffCount,
            'change' => $staffCountChange,
            'change_type' => $staffCountChange >= 0 ? 'positive' : 'negative'
        ];

        // Add period information
        $overallMetrics['period_info'] = [
            'selected_period' => $dateRange,
            'current_period' => [
                'staff_count' => $currentStaffCount,
                'review_count' => $currentReviewCount
            ],
            'comparison_period' => [
                'staff_count' => $comparisonStaffCount,
                'review_count' => $comparisonReviewCount
            ]
        ];


        return $overallMetrics;
    }

    public function calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews)
    {
        $currentAvgRating = $currentReviews->isNotEmpty()
            ? round($currentReviews->avg('calculated_rating'), 1)
            : 0;

        $previousAvgRating = $previousReviews->isNotEmpty()
            ? round($previousReviews->avg('calculated_rating'), 1)
            : 0;

        // Use AIProcessorService for sentiment label (same as DashboardService all_sentiment)
        $currentSentimentLabel = $currentReviews->isNotEmpty()
            ? $this->aiProcessorService->calculateAggregatedSentiment($currentReviews)['sentiment_label']
            : 'neutral';

        $previousSentimentLabel = $previousReviews->isNotEmpty()
            ? $this->aiProcessorService->calculateAggregatedSentiment($previousReviews)['sentiment_label']
            : 'neutral';

        // Calculate sentiment percentage using RuleEngineService thresholds
        $currentSentimentPercentage = $this->calculateSentimentPercentage($currentReviews);
        $previousSentimentPercentage = $this->calculateSentimentPercentage($previousReviews);

        $currentTotalReviews = $currentReviews->count();
        $previousTotalReviews = $previousReviews->count();

        $ratingChange = $previousAvgRating > 0 ?
            round((($currentAvgRating - $previousAvgRating) / $previousAvgRating) * 100, 1) : 0;

        // Sentiment change based on percentage
        $sentimentChange = $previousSentimentPercentage > 0 ?
            round($currentSentimentPercentage - $previousSentimentPercentage, 1) : 0;

        $reviewsChange = $previousTotalReviews > 0 ?
            $currentTotalReviews - $previousTotalReviews : $currentTotalReviews;

        return [
            'overall_rating' => [
                'value' => $currentAvgRating,
                'previous_value' => $previousAvgRating,
                'change' => $ratingChange,
                'change_type' => $this->ruleEngineService->getChangeType($ratingChange)
            ],
            'overall_sentiment' => [
                'status' => $currentSentimentLabel,
                'value' => $currentSentimentPercentage,
                'previous_status' => $previousSentimentLabel,
                'previous_value' => $previousSentimentPercentage,
                'change' => $sentimentChange,
                'change_type' => $this->ruleEngineService->getChangeType($sentimentChange)
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'previous_value' => $previousTotalReviews,
                'change' => $reviewsChange,
                'change_type' => $this->ruleEngineService->getChangeType($reviewsChange)
            ]
        ];
    }

    /**
     * Calculate sentiment percentage using RuleEngineService thresholds
     */
    private function calculateSentimentPercentage($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();

        return round(($positiveReviews / $reviews->count()) * 100, 1);
    }
}
