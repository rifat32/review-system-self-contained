<?php

namespace App\Services\Staff;

use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Rule\RuleEngineService;
use Carbon\Carbon;

class StaffService
{
    private RuleEngineService $ruleEngineService;

    public function __construct(RuleEngineService $ruleEngineService)
    {
        $this->ruleEngineService = $ruleEngineService;
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

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange['start']);
                $endDate = Carbon::parse($dateRange['end']);
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when($userBranchId, function ($query) use ($userBranchId) {
                return $query->where('branch_id', $userBranchId);
            })
            ->globalFilters(0, $businessId)
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

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange['start'])->subMonth();
                $endDate = Carbon::parse($dateRange['end'])->subMonth();
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->when($userBranchId, function ($query) use ($userBranchId) {
                return $query->where('branch_id', $userBranchId);
            })
            ->globalFilters(0, $businessId)
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

        // DEBUG: Log final result
        \Log::info('getStaffMetricsWithComparison result', [
            'metrics' => $overallMetrics
        ]);

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

        $currentSentiment = $this->calculateAverageSentiment($currentReviews);
        $currentTotalReviews = $currentReviews->count();

        $previousSentiment = $this->calculateAverageSentiment($previousReviews);
        $previousTotalReviews = $previousReviews->count();

        $ratingChange = $previousAvgRating > 0 ?
            round((($currentAvgRating - $previousAvgRating) / $previousAvgRating) * 100, 1) : 0;

        $sentimentChange = $previousSentiment > 0 ?
            round($currentSentiment - $previousSentiment, 1) : 0;

        $reviewsChange = $previousTotalReviews > 0 ?
            $currentTotalReviews - $previousTotalReviews : $currentTotalReviews;

        return [
            'overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $ratingChange,
                'change_type' => $this->ruleEngineService->getChangeType($ratingChange)
            ],
            'overall_sentiment' => [
                'value' => $currentSentiment,
                'change' => $sentimentChange,
                'change_type' => $this->ruleEngineService->getChangeType($sentimentChange)
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'change' => $reviewsChange,
                'change_type' => $this->ruleEngineService->getChangeType($reviewsChange)
            ]
        ];
    }
    public function calculateAverageSentiment($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        return round(($positiveReviews / $reviews->count()) * 100);
    }
}
