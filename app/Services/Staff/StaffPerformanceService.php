<?php

namespace App\Services\Staff;

use App\Models\ReviewNew;
use App\Models\User;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\Rule\RuleEngineService;
use App\Services\Review\ReviewService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StaffPerformanceService
{
    private RuleEngineService $ruleEngineService;
    private ReviewService $reviewService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        ReviewService $reviewService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->reviewService = $reviewService;
    }
    // ==================== STAFF PERFORMANCE SNAPSHOT ====================

    /**
     * Get staff performance snapshot dynamically
     */
    public function getStaffPerformanceSnapshot($businessId, $dateRange, ?int $staffId = null)
    {
        $query = ReviewNew::with('staff')
            ->where('business_id', $businessId)
            ->globalFilters(0, $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->withCalculatedRating();

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        $staffReviews = $query->get();

        if ($staffId && $staffReviews->count() < 3) {
            return null;
        }

        $staffData = [];
        $groupedReviews = $staffReviews->groupBy('staff_id');

        foreach ($groupedReviews as $currentStaffId => $reviews) {
            if ($reviews->count() < 3) {
                continue;
            }

            $staff = $reviews->first()->staff;
            if (!$staff) {
                continue;
            }

            $avgRating = $reviews->isNotEmpty()
                ? round($reviews->avg('calculated_rating'), 1)
                : 0;

            // Use dynamic thresholds from rule engine
            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
            $negativeReviews = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

            $neutralLower = $this->ruleEngineService->getNeutralLowerThreshold();
            $neutralUpper = $this->ruleEngineService->getNeutralUpperThreshold();
            $neutralReviews = $reviews->whereBetween('calculated_rating', [$neutralLower, $neutralUpper])->count();

            $staff_suggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();

            $staffData[] = [
                'id' => $currentStaffId,
                'name' => $staff->name,
                'email' => $staff->email,
                "branches" => $staff->branches->pluck('name')->toArray(),
                'job_title' => $staff->job_title ?? 'Staff',
                'rating' => $avgRating,
                'image' => $staff->image ?? null,
                'review_count' => $reviews->count(),
                'sentiment_breakdown' => [
                    'positive' => $reviews->count() > 0
                        ? round(($positiveReviews / $reviews->count()) * 100)
                        : 0,
                    'neutral' => $reviews->count() > 0
                        ? round(($neutralReviews / $reviews->count()) * 100)
                        : 0,
                    'negative' => $reviews->count() > 0
                        ? round(($negativeReviews / $reviews->count()) * 100)
                        : 0
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

    // ==================== TOP PERFORMING STAFF ====================

    /**
     * Get top three staff dynamically
     */
    public function getTopThreeStaff($businessId, $filters = [])
    {
        $reviewsQuery = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->globalFilters(0, $businessId)
            ->withCalculatedRating();

        $reviewsQuery = $this->reviewService->applyFilters($reviewsQuery, $filters);
        $reviews = $reviewsQuery->get();

        if ($reviews->isEmpty()) {
            return [
                'message' => 'No staff reviews found',
                'staff' => []
            ];
        }

        $staffGroups = [];
        foreach ($reviews as $review) {
            if ($review->staff_id) {
                $staffGroups[$review->staff_id][] = $review;
            }
        }

        $staffPerformance = [];

        foreach ($staffGroups as $staffId => $reviewsArray) {
            $staff = User::find($staffId);
            if (!$staff) {
                continue;
            }

            $totalRating = 0;
            $totalReviews = count($reviewsArray);
            $positiveCount = 0;
            $latestReviewDate = null;
            $allTopics = [];

            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();

            foreach ($reviewsArray as $review) {
                $totalRating += $review->calculated_rating ?? 0;

                if (isset($review->sentiment_score) && $review->sentiment_score >= $positiveThreshold) {
                    $positiveCount++;
                }

                if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                    $latestReviewDate = $review->created_at;
                }

                if (!empty($review->topics) && is_array($review->topics)) {
                    $allTopics = array_merge($allTopics, $review->topics);
                }
            }

            $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
            $sentimentPercentage = $totalReviews > 0 ? round(($positiveCount / $totalReviews) * 100) : 0;

            $minimumReviews = $this->ruleEngineService->getMinimumReviewsForTopStaff();
            if ($totalReviews < $minimumReviews) {
                continue;
            }

            $topTopics = $this->extractStaffTopics(\collect($reviewsArray));

            $staffPerformance[] = [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'position' => $staff->job_title ?? 'Staff',
                'image' => $staff->image ?? null,
                'avg_rating' => round($avgRating, 1),
                'review_count' => $totalReviews,
                'sentiment_score' => $sentimentPercentage,
                'sentiment_label' => $this->getSentimentLabelByPercentage($sentimentPercentage),
                'top_topics' => array_slice($topTopics, 0, 3),
                'recent_activity' => $latestReviewDate
                    ? $latestReviewDate->diffForHumans()
                    : 'No recent activity'
            ];
        }

        usort($staffPerformance, function ($a, $b) {
            if ($b['avg_rating'] == $a['avg_rating']) {
                return $b['review_count'] <=> $a['review_count'];
            }
            return $b['avg_rating'] <=> $a['avg_rating'];
        });

        $staffPerformance = array_slice($staffPerformance, 0, 3);

        return [
            'total_staff_reviewed' => count($staffGroups),
            'staff' => $staffPerformance
        ];
    }

    // ==================== STAFF TOPICS EXTRACTION ====================

    /**
     * Extract staff topics dynamically
     */
    public function extractStaffTopics($staffReviews)
    {
        $allTopics = [];

        foreach ($staffReviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }

            if (empty($review->topics) && $review->comment) {
                $commonWords = $this->ruleEngineService->getCommonStaffTopicKeywords();
                $comment = strtolower($review->comment);

                foreach ($commonWords as $word) {
                    if (strpos($comment, $word) !== false) {
                        $allTopics[$word] = ($allTopics[$word] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }

    // ==================== STAFF METRICS ====================

    /**
     * Get all staff metrics from review value dynamically
     */
    public function getAllStaffMetricsFromReviewValue($reviews)
    {
        $staffGroups = [];
        foreach ($reviews as $review) {
            if ($review->staff_id) {
                $staffGroups[$review->staff_id][] = $review;
            }
        }

        $staffMetrics = [];

        foreach ($staffGroups as $staffId => $reviewsArray) {
            $staff = User::find($staffId);
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

                $sentimentScore = $review->sentiment_score ?? 0;
                $totalSentiment += $sentimentScore;

                if ($sentimentScore >= $positiveThreshold) {
                    $compliments++;
                } elseif ($sentimentScore < $negativeThreshold) {
                    $complaints++;
                } else {
                    $neutral++;
                }
            }

            $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
            $avgSentiment = $totalReviews > 0 ? $totalSentiment / $totalReviews : 0;

            $staffMetrics[] = [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'position' => $staff->job_title ?? 'Staff',
                'avg_rating' => round($avgRating, 1),
                'sentiment_score' => RuleEngineService::getSentimentLabelFromScore($avgSentiment),
                'compliments_count' => $compliments,
                'complaints_count' => $complaints,
                'neutral_count' => $neutral,
                'total_reviews' => $totalReviews,
                'sentiment_numeric' => round($avgSentiment * 100)
            ];
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
            return RuleEngineService::getStableTrendMessage();
        }
    }

    /**
     * Map suggestions to skill gaps
     */
    public function extractSuggestionsFromReviews(Collection $reviews): Collection
    {
        return $reviews->pluck('staff_suggestions')
            ->flatten()
            ->filter()
            ->unique();
    }

    /**
     * Extract skill gaps from suggestions dynamically
     */
    public function extractSkillGapsFromSuggestions($suggestions)
    {
        if (empty($suggestions)) {
            return [];
        }

        $suggestions = \collect($suggestions)
            ->filter(function ($suggestion) {
                if (is_string($suggestion)) {
                    $clean = trim($suggestion);
                    if ($clean === '[]' || $clean === '' || $clean === '""') {
                        return false;
                    }

                    if (str_starts_with($clean, '[') && str_ends_with($clean, ']')) {
                        $decoded = json_decode($clean, true);
                        return !empty($decoded) && is_array($decoded);
                    }
                }
                return !empty($suggestion);
            })
            ->flatMap(function ($suggestion) {
                if (is_string($suggestion) && str_starts_with($suggestion, '[')) {
                    $decoded = json_decode($suggestion, true);
                    return $decoded ?: [];
                }
                return [$suggestion];
            })
            ->filter()
            ->map(fn($s) => trim($s))
            ->unique();

        if ($suggestions->isEmpty()) {
            return [];
        }

        // Use rule engine to map suggestions to skill gaps
        $skillGaps = $this->ruleEngineService->mapSuggestionsToSkillGaps($suggestions);

        return $skillGaps;
    }

    /**
     * Extract recommended training dynamically
     */
    private function extractRecommendedTraining($suggestions)
    {
        $skillGaps = $this->extractSkillGapsFromSuggestions($suggestions);

        if (!empty($skillGaps)) {
            return $skillGaps[0] . ' Training';
        }

        return $this->ruleEngineService->getDefaultTrainingRecommendation();
    }
}
