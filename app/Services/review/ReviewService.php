<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use Carbon\Carbon;

class ReviewService
{
    /**
     * Get current period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @return \Illuminate\Support\Collection
     */
    public static function getCurrentPeriodReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true
    ) {
        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply branch filter if provided
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Apply date range filter if provided
        if ($dateRange !== null) {
            $startDate = Carbon::parse($dateRange['start'])->startOfDay();
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        return $query->get();
    }

    /**
     * Get comparison (previous) period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end', 'daysOffset'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @return \Illuminate\Support\Collection
     */
    public static function getComparisonPeriodReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
    ) {
        // Return empty collection if no date range provided
        if ($dateRange === null) {
            return collect();
        }

        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply branch filter if provided
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        // Calculate previous period dates
        $prevStartDate = Carbon::parse($dateRange['start'])->subDays($dateRange['daysOffset'])->startOfDay();
        $prevEndDate = Carbon::parse($dateRange['end'])->subDays($dateRange['daysOffset'])->endOfDay();

        $query->whereBetween('created_at', [$prevStartDate, $prevEndDate]);

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        return $query->get();
    }

    /**
     * Get reviews for a specific time period with flexible filtering
     * 
     * @param int $businessId
     * @param array $filters Additional filters ['branch_id' => int, 'staff_id' => int, etc.]
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param bool $withCalculatedRating Whether to include calculated rating
     * @param array $with Relations to eager load
     * @return \Illuminate\Support\Collection
     */
    public static function getReviewsWithFilters(
        int $businessId,
        array $filters = [],
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
        array $with = []
    ) {
        $query = ReviewNew::globalFilters(0, $businessId)
            ->where('business_id', $businessId);

        // Apply additional filters
        foreach ($filters as $field => $value) {
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        // Apply date range filter if provided
        if ($dateRange !== null) {
            $startDate = Carbon::parse($dateRange['start'])->startOfDay();
            $endDate = Carbon::parse($dateRange['end'])->endOfDay();
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Add calculated rating if requested
        if ($withCalculatedRating) {
            $query->withCalculatedRating();
        }

        // Eager load relationships
        if (!empty($with)) {
            $query->with($with);
        }

        return $query->get();
    }

    /**
     * Get both current and comparison period reviews
     * 
     * @param int $businessId
     * @param int|null $branchId Optional branch filter
     * @param array|null $dateRange Optional date range with 'start' and 'end'
     * @param int $comparisonDaysOffset Number of days to go back for comparison (default 30)
     * @return array ['current' => Collection, 'previous' => Collection]
     */
    public static function getCurrentAndComparisonReviews(
        int $businessId,
        ?int $branchId = null,
        ?array $dateRange = null,
        bool $withCalculatedRating = true,
    ): array {
        return [
            'current' => self::getCurrentPeriodReviews(
                businessId: $businessId,
                branchId: $branchId,
                dateRange: $dateRange,
                withCalculatedRating: $withCalculatedRating
            ),
            'previous' => self::getComparisonPeriodReviews(
                businessId: $businessId,
                branchId: $branchId,
                dateRange: $dateRange,
                withCalculatedRating: $withCalculatedRating
            )
        ];
    }

}
