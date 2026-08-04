<?php

namespace App\Services\Business;

use App\Models\BusinessService;
use App\Models\ReviewNew;
use App\Models\Business;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Services\Rule\RuleEngineService;

class BusinessAnalyticsService
{
    private AIProcessorService $aiProcessorService;
    private InsightAggregationService $insightAggregationService;
    private RecommendationGeneratorService $recommendationGeneratorService;
    private RuleEngineService $ruleEngineService;

    public function __construct(
        AIProcessorService $aiProcessorService,
        InsightAggregationService $insightAggregationService,
        RecommendationGeneratorService $recommendationGeneratorService,
        RuleEngineService $ruleEngineService
    ) {
        $this->aiProcessorService = $aiProcessorService;
        $this->insightAggregationService = $insightAggregationService;
        $this->recommendationGeneratorService = $recommendationGeneratorService;
        $this->ruleEngineService = $ruleEngineService;
    }
    // ==================== BUSINESS SERVICES PERFORMANCE ====================

    /**
     * Analyze business services performance
     */
    public function analyzeBusinessServicesPerformance($businessId, $dateRange = null, $user = null)
    {
        // Get all business services for this business
        $businessServices = BusinessService::where('business_id', $businessId)
            ->get(['id', 'name', 'description']);

        if ($businessServices->isEmpty()) {
            return [
                'top_services' => [],
                'worst_services' => [],
                'message' => 'No business services defined'
            ];
        }

        // Get reviews within date range with their associated services
        $reviewQuery = ReviewNew::where('business_id', $businessId)
            ->when($dateRange, fn($q) => $q->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]))
            ->globalReviewFilters(0)
            ->with(['business_services', 'value.tags']) // Eager load services, values, and tags
            ->withCalculatedRating();


        $reviews = $reviewQuery->get();

        if ($reviews->isEmpty()) {
            return [
                'top_services' => [],
                'worst_services' => [],
                'message' => 'No reviews available for the selected period'
            ];
        }

        $serviceMetrics = [];

        foreach ($businessServices as $service) {
            // Find reviews that mention this specific service
            $serviceReviews = $reviews->filter(function ($review) use ($service) {
                return $review->business_services->contains('id', $service->id);
            });

            if ($serviceReviews->isNotEmpty()) {
                // Calculate average rating for this service
                $avgRating = round($serviceReviews->avg('calculated_rating'), 2);
                $totalReviews = $serviceReviews->count();

                // Calculate sentiment for this service
                $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
                $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

                $positiveCount = $serviceReviews->where('sentiment_score', '>=', $positiveThreshold)->count();
                $negativeCount = $serviceReviews->where('sentiment_score', '<', $negativeThreshold)->count();
                $sentimentPercentage = $totalReviews > 0 ? round(($positiveCount / $totalReviews) * 100) : 0;

                // Get common tags for this service - one-liner approach
                $commonTags = collect($serviceReviews)
                    ->flatMap(fn($review) => $review->value ? $review->value->flatMap(fn($value) => $value->tags) : [])
                    ->pluck('tag')
                    ->countBy()
                    ->sortDesc();

                $topTags = $commonTags->keys()->take(3)->toArray();

                // Get sample comments
                $sampleComments = $serviceReviews->sortByDesc('calculated_rating')
                    ->take(2)
                    ->map(function ($review) {
                        return [
                            'comment' => mb_substr($review->comment ?? '', 0, 100) . (mb_strlen($review->comment ?? '') > 100 ? '...' : ''),
                            'rating' => round($review->calculated_rating ?? 0, 1),
                            'sentiment' => AIProcessorService::getSentimentLabel($review->sentiment_score ?? 0),
                            'date' => $review->created_at->format('M d, Y')
                        ];
                    })
                    ->values()
                    ->toArray();

                $serviceMetrics[$service->id] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'description' => $service->description,
                    'average_rating' => $avgRating,
                    'total_reviews' => $totalReviews,
                    'sentiment_score' => $sentimentPercentage,
                    'positive_reviews' => $positiveCount,
                    'negative_reviews' => $negativeCount,
                    'top_tags' => $topTags,
                    'sample_comments' => $sampleComments,
                    'performance_label' => $this->ruleEngineService->getPerformanceLabelFromRating($avgRating)
                ];
            }
        }

        // Sort by average rating (highest first)
        uasort($serviceMetrics, function ($a, $b) {
            // First by rating, then by number of reviews for tie-breaking
            if ($b['average_rating'] == $a['average_rating']) {
                return $b['total_reviews'] <=> $a['total_reviews'];
            }
            return $b['average_rating'] <=> $a['average_rating'];
        });

        // Get services with at least 3 reviews for accurate analysis
        $qualifiedServices = array_filter($serviceMetrics, function ($service) {
            return $service['total_reviews'] >= 3;
        });

        // Safely split into top and worst without overlap
        $allServices = count($qualifiedServices) >= 6 ? array_values($qualifiedServices) : array_values($serviceMetrics);
        $total = count($allServices);

        $topCount = min(3, (int)ceil($total / 2));
        $worstCount = min(3, max(0, $total - $topCount));

        $topServices = array_slice($allServices, 0, $topCount);
        $worstServices = $worstCount > 0 ? array_slice(array_reverse($allServices), 0, $worstCount) : [];

        return [
            'top_services' => $topServices,
            'worst_services' => $worstServices,
            'all_services_count' => count($serviceMetrics),
            'qualified_services_count' => count($qualifiedServices)
        ];
    }

    // ==================== AI INSIGHTS PANEL ====================

    /**
     * Get AI insights panel dynamically
     */
    public function getAiInsightsPanel($businessId, $dateRange = null): array
    {
        $reviewsQuery = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('ai_suggestions')
            ->globalReviewFilters(0)
            ->withCalculatedRating();

        if ($dateRange) {
            $reviewsQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }



        $reviews = $reviewsQuery->get();

        // Get insights from rule engine
        $reviewIds = $reviews->pluck('id')->toArray();
        $insights = $this->insightAggregationService->getDashboardInsights($businessId, 10, $reviewIds);

        // Map suggestions with their source review sentiment for proper weighting
        $suggestions = $reviews->flatMap(function ($review) {
            $suggestions = (array) ($review->ai_suggestions ?? []);
            return \collect($suggestions)->filter()->map(fn($text) => [
                'text' => $text,
                'sentiment' => $review->sentiment_score ?? 0.5
            ]);
        })->toArray();

        $summary = $this->generateAiSummaryFromRuleEngine($businessId, $reviews, $dateRange);
        $issues = $this->extractIssuesFromRuleEngine($businessId, $reviews);
        $opportunities = $this->aiProcessorService->extractOpportunities($businessId, $insights, $suggestions, $issues);
        $predictions = $this->aiProcessorService->generatePredictions($reviews);

        // Calculate rolling AI metadata
        $pendingReviewsCount = ReviewNew::where('business_id', $businessId)
            ->where('is_ai_processed', true)
            ->where('is_rolling_aggregated', false)
            ->count();

        $business = Business::find($businessId);
        $lastUpdated = null;
        if ($business && !empty($business->rolling_ai_insight) && isset($business->rolling_ai_insight['time'])) {
            $lastUpdated = $business->rolling_ai_insight['time'];
        }

        $requiredReviews = max(0, 5 - $pendingReviewsCount);

        $nextRun = null;
        if ($requiredReviews === 0) {
            $now = now();
            $minute = (int) $now->format('i');
            $minutesToNextRun = 10 - ($minute % 10);
            if ($minutesToNextRun === 0) {
                $minutesToNextRun = 10;
            }
            $nextRun = $now->copy()->addMinutes($minutesToNextRun)->startOfMinute()->toIso8601String();
        }

        $rollingSummary = null;
        if (empty($dateRange) && !$this->hasActiveFilters()) {
            if ($business && !empty($business->rolling_ai_insight)) {
                $rollingSummary = $business->rolling_ai_insight;
            }
        }

        return [
            'summary' => $summary,
            'detected_issues' => $issues,
            'opportunities' => $opportunities,
            'predictions' => $predictions,
            'rolling_metadata' => [
                'last_updated' => $lastUpdated,
                'pending_reviews' => $pendingReviewsCount,
                'required_reviews' => $requiredReviews,
                'next_run' => $nextRun,
            ],
            'rolling_strengths' => $rollingSummary['strengths'] ?? null,
            'rolling_weaknesses' => $rollingSummary['weaknesses'] ?? null,
            'rolling_recommendations' => $rollingSummary['recommendations'] ?? null,
            'rolling_trend' => $rollingSummary['trend'] ?? null,
            'rolling_confidence' => $rollingSummary['confidence'] ?? null,
            'rolling_top_topics' => $rollingSummary['top_topics'] ?? null,
        ];
    }

    // ==================== AI SUMMARY GENERATION ====================

    /**
     * Check if request has active filters that should prevent returning cached overall rolling summary
     */
    private function hasActiveFilters(): bool
    {
        $filterKeys = [
            'staff_id',
            'staff_ids',
            'has_staff',
            'is_overall',
            'sentiment_score',
            'sentiment',
            'topics',
            'survey_id',
            'survey_ids',
            'tag_ids',
            'star_ids',
            'rating',
            'csat_score',
            'insight_id',
            'is_private',
            'review_ids',
            'flagged_reviews',
            'branch_id',
            // Rule outcome flags
            'is_staff_mentioned',
            'is_sentiment_flagged',
            'is_category_detected',
            'is_critical_alert',
            'is_staff_risk',
            'is_high_emotion',
            'is_mismatch',
            'is_service_identified',
            'is_area_detected',
        ];

        foreach ($filterKeys as $key) {
            if (request()->has($key)) {
                $val = request()->input($key);

                if ($val === null || $val === '' || $val === 'all') {
                    continue;
                }

                if (is_array($val) && empty($val)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Generate AI summary using rule engine insights
     */
    public function generateAiSummaryFromRuleEngine(int $businessId, $reviews, $dateRange = null): string
    {
        // Only return the all-time rolling AI summary if no specific date range or filter is applied
        if (empty($dateRange) && !$this->hasActiveFilters()) {
            $business = Business::find($businessId);
            if ($business && !empty($business->rolling_ai_insight)) {
                if (isset($business->rolling_ai_insight['summary'])) {
                    return $business->rolling_ai_insight['summary'];
                }
                if (isset($business->rolling_ai_insight['updatedInsight'])) {
                    return $business->rolling_ai_insight['updatedInsight'];
                }
            }
        }

        $reviewIds = $reviews->pluck('id')->toArray();
        $insights = $this->insightAggregationService->getDashboardInsights($businessId, 10, $reviewIds);

        if (empty($insights)) {
            return 'No reviews to analyze.';
        }

        // Use dynamic thresholds
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = $this->ruleEngineService->getNegativeSentimentThreshold();

        $positiveCount = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $negativeCount = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();
        $total = $reviews->count();

        $positivePercent = $total > 0 ? round(($positiveCount / $total) * 100) : 0;

        $negativePercent = $total > 0 ? round(($negativeCount / $total) * 100) : 0;

        // Get top issue from insights
        $topIssue = null;
        foreach ($insights as $insight) {
            if ($insight['mentions'] >= $this->ruleEngineService->getHighIssueThreshold() && $insight['severity'] === 'high') {
                $topIssue = "{$insight['category']} - {$insight['sub_category']}";
                break;
            }
        }

        $summary = $this->ruleEngineService->generateSummaryTemplate($positivePercent, $negativePercent);

        if ($topIssue) {
            $summary .= " A recurring concern mentioned is {$topIssue}.";
        } else {
            // Take top 3 unique categories from insights to make summary dynamic
            $themes = \collect($insights)
                ->take(3)
                ->pluck('category')
                ->unique()
                ->map(fn($t) => strtolower($t))
                ->toArray();

            if (!empty($themes)) {
                $lastTheme = array_pop($themes);
                $themesStr = count($themes) > 0 ? implode(', ', $themes) . ' and ' . $lastTheme : $lastTheme;
                $summary .= " Common themes include " . $themesStr . ".";
            } else {
                $summary .= " " . $this->ruleEngineService->getDefaultSummaryPhrase();
            }
        }

        return $summary;
    }

    // ==================== ISSUE AND RECOMMENDATION EXTRACTION ====================

    /**
     * Extract issues from rule engine insights
     */
    public function extractIssuesFromRuleEngine(int $businessId, $reviews): array
    {
        $ratingThreshold = RuleEngineService::getNeutralUpperThreshold();
        $sentimentThreshold = RuleEngineService::getNegativeSentimentThreshold();

        // Filter for suboptimal reviews: rating below neutral_upper OR score below negative_score
        $suboptimalReviews = $reviews->filter(function ($r) use ($ratingThreshold, $sentimentThreshold) {
            $isLowRating = isset($r->calculated_rating) && $r->calculated_rating <= $ratingThreshold && $r->calculated_rating > 0;
            $isNegativeSentiment = $r->sentiment_score !== null && $r->sentiment_score < $sentimentThreshold;
            return ($isLowRating || $isNegativeSentiment);
        });

        if ($suboptimalReviews->isEmpty()) {
            return [
                [
                    'insight_id' => null,
                    'issue' => ' No major issues detected.',
                    'mention_count' => 0
                ]
            ];
        }

        $reviewIds = $suboptimalReviews->pluck('id')->toArray();
        $insights = $this->insightAggregationService->getDashboardInsights($businessId, 10, $reviewIds, true);

        if (empty($insights)) {
            return [
                [
                    'insight_id' => null,
                    'issue' => ' No major issues detected.',
                    'mention_count' => 0
                ]
            ];
        }

        $issues = [];
        foreach ($insights as $insight) {
            $issues[] = [
                'insight_id' => $insight['id'],
                'issue' => "{$insight['category']} - {$insight['sub_category']}",
                'mention_count' => $insight['mentions'],
                'severity' => $insight['severity'],
                'confidence' => $insight['confidence']
            ];
        }

        return $issues;
    }
}
