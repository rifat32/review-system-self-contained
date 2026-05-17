<?php

namespace App\Services\Staff;

use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Rule\RuleEngineService;
use App\Services\Review\ReviewService;
use App\Services\Review\ReviewMetricsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StaffPerformanceService
{
    private RuleEngineService $ruleEngineService;
    private ReviewService $reviewService;
    private ReviewMetricsService $reviewMetricsService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        ReviewService $reviewService,
        ReviewMetricsService $reviewMetricsService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->reviewService = $reviewService;
        $this->reviewMetricsService = $reviewMetricsService;
    }
    // ==================== STAFF PERFORMANCE SNAPSHOT ====================

    /**
     * Get staff performance snapshot dynamically
     */
    public function getStaffPerformanceSnapshot($businessId, $dateRange, ?int $staffId = null)
    {
        $query = ReviewNew::with('staff')
            ->where('review_news.business_id', $businessId)
            ->globalReviewFilters(is_ai_processed: 0)
            ->whereNotNull('staff_id')
            ->when($dateRange, function ($query) use ($dateRange) {
                return $query->whereBetween('review_news.created_at', [$dateRange['start'], $dateRange['end']]);
            })
            ->withCalculatedRating();

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        $staffReviews = $query->get();


        $staffData = [];
        $groupedReviews = $staffReviews->groupBy('staff_id');

        foreach ($groupedReviews as $currentStaffId => $reviews) {
            $staff = $reviews->first()->staff;
            if (!$staff) {
                continue;
            }

            $avgRating = $reviews->isNotEmpty()
                ? round($reviews->avg('calculated_rating'), 1)
                : 0;

            // Use ReviewMetricsService for sentiment breakdown calculation
            $sentimentData = $this->reviewMetricsService->calculateSentimentBreakdown($reviews);

            $totalCount = $reviews->count();
            $positiveReviews = $sentimentData['positive'];
            $negativeReviews = $sentimentData['negative'];

            $staff_suggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();

            $staffData[] = [
                'id' => $currentStaffId,
                'name' => $staff->name,
                'email' => $staff->email,
                "branches" => $staff->branches->pluck('name')->toArray(),
                'job_title' => $staff->job_title ?? 'Staff',
                'role' => $staff->role() ? [
                    'id' => $staff->role()->id,
                    'name' => $staff->role()->name
                ] : null,
                'rating' => $avgRating,
                'image' => $staff->image ?? null,
                'review_count' => $totalCount,
                // 'sentiment_breakdown' => $sentimentData,
                'sentiment_breakdown' => [
                    'positive' => (int)round($sentimentData['percentages']['positive']),
                    'neutral' => (int)round($sentimentData['percentages']['neutral']),
                    'negative' => (int)round($sentimentData['percentages']['negative'])
                ],
                'positive_count' => $positiveReviews,
                'negative_count' => $negativeReviews,
                'skill_gaps' => $this->extractSkillGapsFromSuggestions($staff_suggestions),
                'recommended_training' => $this->extractRecommendedTraining($staff_suggestions),
                'last_review_date' => $reviews->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                'rating_trend' => $this->calculateStaffRatingTrend($reviews)
            ];
        }

        if ($staffId) {
            return !empty($staffData) ? $staffData[0] : null;
        }

        usort($staffData, fn($a, $b) => $b['rating'] <=> $a['rating']);

        $top = array_slice($staffData, 0, 3);
        $needsImprovement = array_slice(array_reverse($staffData), 0, 3);

        $totalStaffWithReviews = count($staffData);
        $overallAvgRating = $totalStaffWithReviews > 0
            ? round(array_sum(array_column($staffData, 'rating')) / $totalStaffWithReviews, 1)
            : 0;

        return [
            'top_performing' => $top,
            'needs_improvement' => $needsImprovement,
            'overall_stats' => [
                'total_staff_with_reviews' => $totalStaffWithReviews,
                'overall_average_rating' => $overallAvgRating,
                'top_performer_rating' => !empty($top) ? $top[0]['rating'] : 0,
                'lowest_performer_rating' => !empty($needsImprovement)
                    ? $needsImprovement[0]['rating']
                    : 0,
                'rating_gap' => !empty($top) && !empty($needsImprovement)
                    ? round($top[0]['rating'] - $needsImprovement[0]['rating'], 1)
                    : 0
            ]
        ];
    }

  

    // ==================== STAFF METRICS ====================

    /**
     * Get all staff metrics from review value dynamically
     */
    public function getAllStaffMetricsFromSentimentScore($reviews)
    {
        $staffGroups = [];
        foreach ($reviews as $review) {
            if ($review->staff_id) {
                $staffGroups[$review->staff_id][] = $review;
            }
        }

        $staffMetrics = [];

        foreach ($staffGroups as $staffId => $reviewsArray) {
            $staff = User::with([
                'roles:id,name',
                'branches',
                'branch',
                'branch.branch:id,name',
                'branch.branch.manager:id,first_Name,last_Name,email'
            ])->where(
                ['id' => $staffId]
            )->first();
            if (!$staff) {
                continue;
            }

            $totalRating = 0;
            $totalSentiment = 0;
            $totalReviews = count($reviewsArray);
            $compliments = 0;
            $complaints = 0;
            $neutral = 0;

            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            foreach ($reviewsArray as $review) {
                $totalRating += $review->calculated_rating ?? 0;

                $sentimentScore = is_numeric($review->sentiment_score) ? (float) $review->sentiment_score : null;

                if ($sentimentScore === null) {
                    $totalSentiment += 0.5;
                    $neutral++;
                } else {
                    $totalSentiment += $sentimentScore;

                    if ($sentimentScore >= $positiveThreshold) {
                        $compliments++;
                    } elseif ($sentimentScore < $negativeThreshold) {
                        $complaints++;
                    } else {
                        $neutral++;
                    }
                }
            }

            $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
            $avgSentiment = $totalReviews > 0 ? $totalSentiment / $totalReviews : 0;

            $staff_data = $staff->toArray();

            $staff_data['staff_id'] = $staffId;
            $staff_data['staff_name'] = $staff["name"];
            $staff_data['staff_number'] = $staff["phone"] ?? '';
            $staff_data['position'] = $staff["job_title"] ?? 'Staff';
            $staff_data['avg_rating'] = round($avgRating, 1);
            $staff_data['sentiment_score'] = RuleEngineService::determineAggregatedLabel($compliments, $neutral, $complaints);
            $staff_data['compliments_count'] = $compliments;
            $staff_data['complaints_count'] = $complaints;
            $staff_data['neutral_count'] = $neutral;
            $staff_data['total_reviews'] = $totalReviews;
            $staff_data['sentiment_numeric'] = round($avgSentiment * 100);


            $staffMetrics[] = $staff_data;
        }

        usort($staffMetrics, function ($a, $b) {
            return $b['avg_rating'] <=> $a['avg_rating'];
        });

        return $staffMetrics;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get sentiment label by percentage dynamically
     */
    public function getSentimentLabelByPercentage($percentage)
    {
        return $this->ruleEngineService->getSentimentLabelByPercentage($percentage);
    }

    /**
     * Calculate staff rating trend dynamically
     */
    public function calculateStaffRatingTrend($reviews)
    {
        if ($reviews->count() < $this->ruleEngineService->getMinimumReviewsForTrendAnalysis()) {
            return $this->ruleEngineService->getInsufficientDataForTrendMessage();
        }

        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstHalfAvg = $firstHalf->avg('calculated_rating') ?? 0;
        $secondHalfAvg = $secondHalf->avg('calculated_rating') ?? 0;

        $trendThreshold = RuleEngineService::getTrendThreshold();

        if ($secondHalfAvg > $firstHalfAvg + $trendThreshold) {
            return RuleEngineService::getImprovingTrendMessage();
        } elseif ($secondHalfAvg < $firstHalfAvg - $trendThreshold) {
            return RuleEngineService::getDecliningTrendMessage();
        } else {
            return $this->ruleEngineService->getStableTrendMessage();
        }
    }



    /**
     * Extract skill gaps from suggestions dynamically
     */
    public function extractSkillGapsFromSuggestions($suggestions)
    {
        if (empty($suggestions)) {
            return [];
        }

        $cleanSuggestions = \collect($suggestions)
            ->filter()
            ->map(fn($s) => trim((string)$s))
            ->unique();

        if ($cleanSuggestions->isEmpty()) {
            return [];
        }

        return $this->ruleEngineService->mapSuggestionsToSkillGaps($cleanSuggestions);
    }

    /**
     * Extract recommended training dynamically
     */
    public function extractRecommendedTraining($suggestions)
    {
        $skillGaps = $this->extractSkillGapsFromSuggestions($suggestions);

        if (!empty($skillGaps)) {
            return $skillGaps[0] . ' Training';
        }

        return $this->ruleEngineService->getDefaultTrainingRecommendation();
    }
}
