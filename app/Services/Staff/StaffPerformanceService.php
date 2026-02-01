<?php

namespace App\Services\Staff;

use App\Models\ReviewNew;
use App\Models\User;
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
            ->where('review_news.business_id', $businessId)
            ->globalReviewFilters(0)
            ->whereNotNull('staff_id')
            ->when($dateRange, function ($query) use ($dateRange) {
                return $query->whereBetween('review_news.created_at', [$dateRange['start'], $dateRange['end']]);
            })
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

            // Align neutral logic with AIProcessorService: score >= negative AND score < positive
            $neutralReviews = $reviews->where('sentiment_score', '>=', $negativeThreshold)
                ->where('sentiment_score', '<', $positiveThreshold)->count();

            $totalCount = $reviews->count();
            if ($totalCount > 0) {
                $posPct = (int)round(($positiveReviews / $totalCount) * 100);
                $neuPct = (int)round(($neutralReviews / $totalCount) * 100);
                $negPct = (int)round(($negativeReviews / $totalCount) * 100);
            } else {
                $posPct = $neuPct = $negPct = 0;
            }

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
                'sentiment_breakdown' => [
                    'positive' => $posPct,
                    'neutral' => $neuPct,
                    'negative' => $negPct
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
            ->globalReviewFilters(0)
            ->filterByDateRange()
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

            $negativeCount = 0;
            $neutralCount = 0;
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            foreach ($reviewsArray as $review) {
                $totalRating += $review->calculated_rating ?? 0;

                $sentimentScore = $review->sentiment_score ?? 0;
                if ($sentimentScore >= $positiveThreshold) {
                    $positiveCount++;
                } elseif ($sentimentScore < $negativeThreshold) {
                    $negativeCount++;
                } else {
                    $neutralCount++;
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
                'sentiment_label' => RuleEngineService::determineAggregatedLabel($positiveCount, $neutralCount, $negativeCount),
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
            foreach ($review->topics ?? [] as $topic) {
                $topicName = is_array($topic) ? ($topic['main_category'] ?? 'General') : $topic;
                $allTopics[$topicName] = ($allTopics[$topicName] ?? 0) + 1;
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
