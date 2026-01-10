<?php

namespace App\Services\staff;

use App\Models\ReviewNew;
use Carbon\Carbon;

class StaffService
{
    /**
     * Get date range for current period based on period type
     */
    public static function getCurrentPeriodDateRange(string $period): array
    {
        return match ($period) {
            'this_week' => [
                'start' => Carbon::now()->startOfWeek(),
                'end' => Carbon::now()->endOfWeek()
            ],
            'this_month' => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth()
            ],
            'last_week' => [
                'start' => Carbon::now()->subWeek()->startOfWeek(),
                'end' => Carbon::now()->subWeek()->endOfWeek()
            ],
            'last_month' => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth()
            ],
            default => [
                'start' => Carbon::now()->startOfMonth(),
                'end' => Carbon::now()->endOfMonth()
            ]
        };
    }

    /**
     * Get date range for comparison (previous) period based on period type
     */
    public static function getComparisonPeriodDateRange(string $period): array
    {
        return match ($period) {
            'this_week' => [
                // Compare this week with last week
                'start' => Carbon::now()->subWeek()->startOfWeek(),
                'end' => Carbon::now()->subWeek()->endOfWeek()
            ],
            'this_month' => [
                // Compare this month with last month
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth()
            ],
            'last_week' => [
                // Compare last week with 2 weeks ago
                'start' => Carbon::now()->subWeeks(2)->startOfWeek(),
                'end' => Carbon::now()->subWeeks(2)->endOfWeek()
            ],
            'last_month' => [
                // Compare last month with 2 months ago
                'start' => Carbon::now()->subMonths(2)->startOfMonth(),
                'end' => Carbon::now()->subMonths(2)->endOfMonth()
            ],
            default => [
                'start' => Carbon::now()->subMonth()->startOfMonth(),
                'end' => Carbon::now()->subMonth()->endOfMonth()
            ]
        };
    }

    /**
     * Get reviews for current period
     */
    public static function getCurrentPeriodReviews(int $businessId, string $period)
    {
        $dateRange = self::getCurrentPeriodDateRange($period);

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();
    }

    /**
     * Get reviews for comparison (previous) period
     */
    public static function getComparisonPeriodReviews(int $businessId, string $period)
    {
        $dateRange = self::getComparisonPeriodDateRange($period);

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();
    }

    /**
     * Get staff metrics with period comparison
     */
    public static function getStaffMetricsWithComparison(int $businessId, string $period): array
    {
        // Get current period reviews
        $currentReviews = self::getCurrentPeriodReviews($businessId, $period);

        // Get comparison period reviews
        $comparisonReviews = self::getComparisonPeriodReviews($businessId, $period);

        // Get date ranges for context
        $currentDateRange = self::getCurrentPeriodDateRange($period);
        $comparisonDateRange = self::getComparisonPeriodDateRange($period);

        // Calculate staff counts
        $currentStaffCount = $currentReviews->pluck('staff_id')->unique()->count();
        $comparisonStaffCount = $comparisonReviews->pluck('staff_id')->unique()->count();
        $staffCountChange = $currentStaffCount - $comparisonStaffCount;

        // Calculate review counts
        $currentReviewCount = $currentReviews->count();
        $comparisonReviewCount = $comparisonReviews->count();
        $reviewCountChange = $currentReviewCount - $comparisonReviewCount;

        // Calculate metrics using existing helper function
        $overallMetrics = calculateOverallMetricsFromReviewValue($currentReviews, $comparisonReviews);

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
            'selected_period' => $period,
            'current_period' => [
                'start' => $currentDateRange['start']->format('Y-m-d'),
                'end' => $currentDateRange['end']->format('Y-m-d'),
                'label' => self::getPeriodLabel($period, 'current'),
                'staff_count' => $currentStaffCount,
                'review_count' => $currentReviewCount
            ],
            'comparison_period' => [
                'start' => $comparisonDateRange['start']->format('Y-m-d'),
                'end' => $comparisonDateRange['end']->format('Y-m-d'),
                'label' => self::getPeriodLabel($period, 'comparison'),
                'staff_count' => $comparisonStaffCount,
                'review_count' => $comparisonReviewCount
            ]
        ];

        return $overallMetrics;
    }

    /**
     * Get human-readable period label
     */
    private static function getPeriodLabel(string $period, string $type): string
    {
        if ($type === 'current') {
            return match ($period) {
                'this_week' => 'This Week',
                'this_month' => 'This Month',
                'last_week' => 'Last Week',
                'last_month' => 'Last Month',
                default => 'Current Period'
            };
        }

        // Comparison labels
        return match ($period) {
            'this_week' => 'Last Week',
            'this_month' => 'Last Month',
            'last_week' => 'Week Before Last',
            'last_month' => '2 Months Ago',
            default => 'Previous Period'
        };
    }
}
