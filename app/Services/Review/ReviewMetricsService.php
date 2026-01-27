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
    public function calculateCSATScore(
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
            })
            ->globalReviewFilters(0, 0, $dateRange !== null);

        $totalCount = (clone $totalQuery)->count();

        // Query for reviews meeting threshold
        $qualifyingCount = (clone $totalQuery)
            ->whereMeetsThreshold()
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
    public function getFlaggedReviews(
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
            })
            ->globalReviewFilters(0, 0, $dateRange !== null);

        $totalCount = (clone $baseQuery)->count();

        // Count flagged reviews using scope
        $flaggedCount = (clone $baseQuery)
            ->whereDoesNotMeetsThreshold()
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
    public function calculateSentimentBreakdown($reviews): array
    {
        // Use a more robust counting method that handles missing labels
        $counts = [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0
        ];

        foreach ($reviews as $review) {
            $label = $review->sentiment_label;

            // Fallback to score if label is missing
            if (!$label && isset($review->sentiment_score)) {
                $label = \App\Services\Rule\RuleEngineService::getSentimentLabelFromScore($review->sentiment_score);
            }

            $label = $label ?: 'neutral';
            if (isset($counts[$label])) {
                $counts[$label]++;
            }
        }

        $positiveCount = $counts['positive'];
        $negativeCount = $counts['negative'];
        $neutralCount = $counts['neutral'];
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
    public function calculateAverageRating($reviews): float
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
    public function getReviewCountWithComparison($currentReviews, $previousReviews): array
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
    public function getRatingWithComparison($currentReviews, $previousReviews): array
    {
        $currentAvgRating = $this->calculateAverageRating($currentReviews);
        $previousAvgRating = $this->calculateAverageRating($previousReviews);
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
    public function getSentimentWithComparison($currentReviews, $previousReviews): array
    {
        $currentBreakdown = $this->calculateSentimentBreakdown($currentReviews);
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
    public function getStaffCountWithComparison($currentReviews, $previousReviews): array
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

    /**
     * Get submissions over time
     * 
     * @param mixed $reviews Reviews collection or query builder
     * @param string $period Period (7d, 30d, 90d, 1y)
     * @return array Time-series data with ratings and sentiment
     */
    public function getSubmissionsOverTime($reviews, $period)
    {
        $endDate = Carbon::now();
        $startDate = match ($period) {
            '7d' => Carbon::now()->subDays(7),
            '90d' => Carbon::now()->subDays(90),
            '1y' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30)
        };

        $groupFormat = match ($period) {
            '7d' => 'd-m-Y',
            '90d', '1y' => 'm-Y',
            default => 'd-m-Y'
        };

        if ($reviews instanceof \Illuminate\Database\Eloquent\Builder) {
            $reviews = $reviews->get();
        }

        $reviewsArray = is_array($reviews) ? $reviews : $reviews->toArray();

        $filteredReviews = [];
        foreach ($reviewsArray as $review) {
            $createdAt = is_array($review)
                ? ($review['created_at'] ?? null)
                : ($review->created_at ?? null);

            if (!$createdAt)
                continue;

            $reviewDate = Carbon::parse($createdAt);
            if ($reviewDate->between($startDate, $endDate)) {
                $filteredReviews[] = $review;
            }
        }

        $submissionsByPeriod = [];
        foreach ($filteredReviews as $review) {
            $createdAt = is_array($review)
                ? ($review['created_at'] ?? null)
                : ($review->created_at ?? null);

            if (!$createdAt)
                continue;

            $periodKey = Carbon::parse($createdAt)->format($groupFormat);

            if (!isset($submissionsByPeriod[$periodKey])) {
                $submissionsByPeriod[$periodKey] = [
                    'total_rating' => 0,
                    'total_sentiment' => 0,
                    'count' => 0
                ];
            }

            $rating = is_array($review)
                ? ($review['calculated_rating'] ?? 0)
                : ($review->calculated_rating ?? 0);

            $sentiment = is_array($review)
                ? ($review['sentiment_score'] ?? 0)
                : ($review->sentiment_score ?? 0);

            $submissionsByPeriod[$periodKey]['total_rating'] += $rating;
            $submissionsByPeriod[$periodKey]['total_sentiment'] += $sentiment;
            $submissionsByPeriod[$periodKey]['count']++;
        }

        // GENERATE ALL DATES IN RANGE WITH ZERO VALUES
        $allPeriods = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format($groupFormat);

            if (!isset($allPeriods[$periodKey])) {
                $allPeriods[$periodKey] = [
                    'total_rating' => 0,
                    'total_sentiment' => 0,
                    'count' => 0
                ];
            }

            // Increment based on period type
            if ($groupFormat === 'd-m-Y') {
                $current->addDay();
            } else {
                $current->addMonth();
            }
        }

        // MERGE ACTUAL DATA WITH ALL PERIODS
        foreach ($submissionsByPeriod as $periodKey => $values) {
            $allPeriods[$periodKey] = $values;
        }

        // CONVERT TO ARRAY FORMAT
        $data = [];
        foreach ($allPeriods as $period => $values) {
            $data[] = [
                'period' => $period,
                'count' => $values['count'],
                'avg_rating' => $values['count'] > 0
                    ? round($values['total_rating'] / $values['count'], 1)
                    : 0,
                'avg_sentiment' => $values['count'] > 0
                    ? round($values['total_sentiment'] / $values['count'], 2)
                    : 0
            ];
        }

        // SORT BY PERIOD (CHRONOLOGICALLY)
        usort($data, function ($a, $b) use ($groupFormat) {
            $dateA = Carbon::createFromFormat($groupFormat, $a['period']);
            $dateB = Carbon::createFromFormat($groupFormat, $b['period']);
            return $dateA <=> $dateB;
        });

        return $data;
    }
}
