<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\AiRule;
use App\Services\Rule\ConditionBuilderService;
use App\Services\Rule\RuleEngineService;

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
        Collection $reviews
    ): array {
        $totalCount = $reviews->count();

        $business = Business::find($businessId);
        $threshold = (float) ($business?->threshold_rating ?? RuleEngineService::getCsatThreshold());

        $qualifyingCount = $reviews->filter(function ($review) use ($threshold) {
            return (float) ($review->calculated_rating ?? 0) >= $threshold;
        })->count();

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
     * @param Collection|null $reviews Optional pre-fetched reviews
     * @return array ['count' => int, 'percentage' => float]
     */
    public function getFlaggedReviews(
        int $businessId,
        Collection $reviews
    ): array {
        // Count reviews that triggered a Critical Alert in the granular outcomes table
        $reviewIds = $reviews->pluck('id');
        
        $flaggedCount = \App\Models\ReviewRuleOutcome::whereIn('review_id', $reviewIds)
            ->where('is_flagged', true)
            ->count();

        $totalCount = $reviews->count();
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
     * @param Collection $reviews
     * @return array
     */
    public function calculateSentimentBreakdown($reviews): array
    {
        // Use dynamic thresholds
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positiveCount = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $negativeCount = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();
        $totalCount = $reviews->count();
        $neutralCount = max(0, $totalCount - $positiveCount - $negativeCount);

        $currentSentimentScore = $reviews->avg('sentiment_score') ?? 0;

        return [
            'positive' => $positiveCount,
            'negative' => $negativeCount,
            'neutral' => $neutralCount,
            'total' => $totalCount,
            'score' => round($currentSentimentScore, 2),
            'avg_score' => round($currentSentimentScore, 2),
            'sentiment_label' => RuleEngineService::determineAggregatedLabel($positiveCount, $neutralCount, $negativeCount),
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
     * @param Collection $reviews
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
     * @param Collection $currentReviews
     * @param Collection $previousReviews
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
     * @param Collection $currentReviews
     * @param Collection $previousReviews
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
     * @param Collection $currentReviews
     * @param Collection $previousReviews
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
     * @param Collection $currentReviews
     * @param Collection $previousReviews
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
