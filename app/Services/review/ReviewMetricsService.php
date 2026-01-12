<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use Carbon\Carbon;

class ReviewMetricsService
{
    /**
     * Calculate CSAT score for given criteria
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @return array ['score' => percentage, 'qualifying_count' => int, 'total_count' => int]
     */
    public static function calculateCSATScore(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null
    ): array {
        // Base query for total reviews
        $totalQuery = ReviewNew::where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange['start'])->startOfDay();
                $endDate = Carbon::parse($dateRange['end'])->endOfDay();
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            });

        $totalCount = (clone $totalQuery)->count();

        // Query for reviews meeting threshold
        $qualifyingCount = (clone $totalQuery)
            ->whereMeetsThreshold($businessId)
            ->count();

        $score = $totalCount > 0 ? round(($qualifyingCount / $totalCount) * 100, 1) : 0;

        return [
            'score' => $score,
            'percentage' => $score,
            'qualifying_count' => $qualifyingCount,
            'total_count' => $totalCount
        ];
    }

    /**
     * Get flagged reviews count and percentage
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @return array ['count' => int, 'percentage' => float]
     */
    public static function getFlaggedReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null
    ): array {
        // Base query
        $baseQuery = ReviewNew::where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange['start'])->startOfDay();
                $endDate = Carbon::parse($dateRange['end'])->endOfDay();
                return $query->whereBetween('created_at', [$startDate, $endDate]);
            });

        $totalCount = (clone $baseQuery)->count();
        $flaggedCount = (clone $baseQuery)
            ->whereDoesNotMeetThreshold($businessId)
            ->count();

        $percentage = $totalCount > 0 ? round(($flaggedCount / $totalCount) * 100, 1) : 0;

        return [
            'count' => $flaggedCount,
            'percentage' => $percentage,
            'total_count' => $totalCount
        ];
    }

    /**
     * Calculate sentiment breakdown
     * 
     * @param \Illuminate\Support\Collection $reviews
     * @return array
     */
    public static function calculateSentimentBreakdown($reviews): array
    {
        $positiveCount = $reviews->where('sentiment', 'positive')->count();
        $negativeCount = $reviews->where('sentiment', 'negative')->count();
        $neutralCount = $reviews->where('sentiment', 'neutral')->count();
        $totalCount = $reviews->count();

        $currentSentimentScore = $reviews->avg('sentiment_score') ?? 0;

        return [
            'positive' => $positiveCount,
            'negative' => $negativeCount,
            'neutral' => $neutralCount,
            'total' => $totalCount,
            'score' => round($currentSentimentScore, 2),
            'percentages' => [
                'positive' => $totalCount > 0 ? round(($positiveCount / $totalCount) * 100, 1) : 0,
                'negative' => $totalCount > 0 ? round(($negativeCount / $totalCount) * 100, 1) : 0,
                'neutral' => $totalCount > 0 ? round(($neutralCount / $totalCount) * 100, 1) : 0,
            ]
        ];
    }

    /**
     * Calculate average rating
     * 
     * @param \Illuminate\Support\Collection $reviews
     * @return float
     */
    public static function calculateAverageRating($reviews): float
    {
        return $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;
    }

    /**
     * Get review count with change comparison
     * 
     * @param \Illuminate\Support\Collection $currentReviews
     * @param \Illuminate\Support\Collection $previousReviews
     * @return array
     */
    public static function getReviewCountWithComparison($currentReviews, $previousReviews): array
    {
        $currentTotal = $currentReviews->count();
        $previousTotal = $previousReviews->count();
        $change = $currentTotal - $previousTotal;

        return [
            'current' => $currentTotal,
            'previous' => $previousTotal,
            'change' => $change,
            'change_type' => $change >= 0 ? 'positive' : 'negative',
            'change_percentage' => $previousTotal > 0
                ? round((($change / $previousTotal) * 100), 1)
                : 0
        ];
    }

    /**
     * Get rating with change comparison
     * 
     * @param \Illuminate\Support\Collection $currentReviews
     * @param \Illuminate\Support\Collection $previousReviews
     * @return array
     */
    public static function getRatingWithComparison($currentReviews, $previousReviews): array
    {
        $currentAvgRating = self::calculateAverageRating($currentReviews);
        $previousAvgRating = self::calculateAverageRating($previousReviews);
        $change = round($currentAvgRating - $previousAvgRating, 1);

        return [
            'current' => $currentAvgRating,
            'previous' => $previousAvgRating,
            'change' => $change,
            'change_type' => $change >= 0 ? 'positive' : 'negative'
        ];
    }

    /**
     * Get sentiment with change comparison
     * 
     * @param \Illuminate\Support\Collection $currentReviews
     * @param \Illuminate\Support\Collection $previousReviews
     * @return array
     */
    public static function getSentimentWithComparison($currentReviews, $previousReviews): array
    {
        $currentBreakdown = self::calculateSentimentBreakdown($currentReviews);
        $previousScore = $previousReviews->avg('sentiment_score') ?? 0;

        $change = round($currentBreakdown['score'] - $previousScore, 2);

        return array_merge($currentBreakdown, [
            'previous_score' => round($previousScore, 2),
            'change' => $change,
            'change_type' => $change >= 0 ? 'positive' : 'negative'
        ]);
    }

    /**
     * Get staff count with comparison
     * 
     * @param \Illuminate\Support\Collection $currentReviews
     * @param \Illuminate\Support\Collection $previousReviews
     * @return array
     */
    public static function getStaffCountWithComparison($currentReviews, $previousReviews): array
    {
        $currentStaffCount = $currentReviews->pluck('staff_id')->filter()->unique()->count();
        $previousStaffCount = $previousReviews->pluck('staff_id')->filter()->unique()->count();
        $change = $currentStaffCount - $previousStaffCount;

        return [
            'current_active_staff' => $currentStaffCount,
            'previous_active_staff' => $previousStaffCount,
            'change' => $change,
            'change_type' => $change >= 0 ? 'positive' : 'negative'
        ];
    }
}
