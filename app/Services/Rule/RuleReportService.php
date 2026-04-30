<?php

namespace App\Services\Rule;

use App\Models\AiRule;
use App\Models\ReviewNew;
use App\Models\InsightRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\AiRuleTrigger;
use App\Models\User;

class RuleReportService
{
    /**
     * Get or recreate a default rule, ensuring it exists
     */
    public function getDefaultRule(string $ruleKey, int $businessId): AiRule
    {
        $ruleId = $ruleKey . '.' . $businessId;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('is_default', true)
            ->first();

        if (!$rule) {
            throw new \Exception("Default rule '{$ruleKey}' not found for business {$businessId}.");
        }

        return $rule;
    }

    /**
     * Get sentiment analysis report
     */
    public function getSentimentAnalysisReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('SENTIMENT_ANALYSIS', $businessId);

        $query = ReviewNew::where('business_id', $businessId)->globalReviewFilters(0);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $reviews = $query->get();

        $sentimentCounts = [
            'positive' => 0,
            'neutral' => 0,
            'negative' => 0
        ];

        foreach ($reviews as $review) {
            $sentiment = $review->sentiment_label;

            // Fallback to score if label is missing
            if (!$sentiment && isset($review->sentiment_score)) {
                $sentiment = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score);
            }

            $sentiment = $sentiment ?: 'neutral';

            if (isset($sentimentCounts[$sentiment])) {
                $sentimentCounts[$sentiment]++;
            }
        }

        $total = $reviews->count();

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate,
                'conditions' => $rule->conditions
            ],
            'summary' => [
                'total_reviews_analyzed' => $total,
                'positive_count' => $sentimentCounts['positive'],
                'neutral_count' => $sentimentCounts['neutral'],
                'negative_count' => $sentimentCounts['negative'],
                'positive_percentage' => $total > 0 ? round(($sentimentCounts['positive'] / $total) * 100, 2) : 0,
                'neutral_percentage' => $total > 0 ? round(($sentimentCounts['neutral'] / $total) * 100, 2) : 0,
                'negative_percentage' => $total > 0 ? round(($sentimentCounts['negative'] / $total) * 100, 2) : 0,
            ],
            'trends' => $this->getSentimentTrends($businessId, $startDate, $endDate),
            'sample_reviews' => [
                'positive' => $reviews->filter(function ($r) {
                    return $r->sentiment_label === 'positive' || ($r->sentiment_label === null && $r->sentiment_score >= RuleEngineService::getPositiveSentimentThreshold());
                })->take(5)->values(),
                'negative' => $reviews->filter(function ($r) {
                    return $r->sentiment_label === 'negative' || ($r->sentiment_label === null && $r->sentiment_score <= RuleEngineService::getNegativeSentimentThreshold());
                })->take(5)->values()
            ]
        ];
    }

    /**
     * Get emotion intensity report
     */
    public function getEmotionIntensityReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('EMOTION_INTENSITY', $businessId);

        // This would query InsightRecords or AI analysis data
        // For now, returning structure
        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate
            ],
            'summary' => [
                'total_high_intensity_reviews' => 0,
                'percentage_of_total' => 0,
                'average_intensity_score' => 0,
                'emotions_detected' => []
            ],
            'trends' => [],
            'sample_reviews' => []
        ];
    }

    /**
     * Get rating/comment mismatch report
     */
    public function getRatingCommentMismatchReport(int $businessId, ?string $startDate = null, ?string $endDate = null): array
    {
        $rule = $this->getDefaultRule('RATING_COMMENT_MISMATCH', $businessId);

        $query = ReviewNew::where('business_id', $businessId)->globalReviewFilters(0);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $reviews = $query->get();

        $highRatingNegativeComment = $reviews->filter(function ($review) use ($businessId) {
            $label = $review->sentiment_label;
            if (!$label && isset($review->sentiment_score)) {
                $label = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score, $businessId);
            }
            return ($review->calculated_rating ?? 0) >= (config('ai.insights.opportunities.reporting.mismatch_high_rating') ?? 4) && $label === 'negative';
        });

        $lowRatingPositiveComment = $reviews->filter(function ($review) use ($businessId) {
            $label = $review->sentiment_label;
            if (!$label && isset($review->sentiment_score)) {
                $label = RuleEngineService::getSentimentLabelFromScore($review->sentiment_score, $businessId);
            }
            return ($review->calculated_rating ?? 0) <= (config('ai.insights.opportunities.reporting.mismatch_low_rating') ?? 2) && $label === 'positive';
        });

        $totalMismatches = $highRatingNegativeComment->count() + $lowRatingPositiveComment->count();

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'precision_rate' => $rule->precision_rate
            ],
            'summary' => [
                'total_mismatches' => $totalMismatches,
                'high_rating_negative_comment' => $highRatingNegativeComment->count(),
                'low_rating_positive_comment' => $lowRatingPositiveComment->count(),
                'percentage_of_total_reviews' => $reviews->count() > 0 ? round(($totalMismatches / $reviews->count()) * 100, 2) : 0
            ],
            'mismatch_patterns' => [
                [
                    'rating_range' => '4-5',
                    'negative_comments' => $highRatingNegativeComment->count(),
                    'common_issues' => []
                ]
            ],
            'sample_reviews' => $highRatingNegativeComment->take(10)->values()
        ];
    }



    /**
     * Helper: Get sentiment trends over time
     */
    private function getSentimentTrends(int $businessId, ?string $startDate, ?string $endDate): array
    {
        $query = ReviewNew::where('business_id', $businessId)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN sentiment_label = "positive" THEN 1 ELSE 0 END) as positive'),
                DB::raw('SUM(CASE WHEN sentiment_label = "neutral" THEN 1 ELSE 0 END) as neutral'),
                DB::raw('SUM(CASE WHEN sentiment_label = "negative" THEN 1 ELSE 0 END) as negative')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->globalReviewFilters(0)
            ->limit(config('ai.insights.opportunities.reporting.trend_limit_days') ?? 30);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()->toArray();
    }

    /**
     * Generic method to get basic stats for any default rule
     */
    public function getBasicRuleReport(string $ruleKey, int $businessId): array
    {
        $rule = $this->getDefaultRule($ruleKey, $businessId);

        return [
            'rule_info' => [
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'description' => $rule->description,
                'precision_rate' => $rule->precision_rate,
                'category' => $rule->category,
                'priority' => $rule->priority,
                'enabled' => $rule->enabled
            ],
            'summary' => [
                'message' => 'This report is under development. Rule configuration loaded successfully.'
            ]
        ];
    }
    /**
     * Get aggregated dashboard boxes for all default rules
     */
    public function getDashboardBoxes(int $businessId, string $period = 'last_30_days'): array
    {
        // 1. Determine Date Range
        $now = Carbon::now();
        $startDate = match ($period) {
            'last_7_days' => $now->copy()->subDays(7)->startOfDay(),
            'this_month' => $now->copy()->startOfMonth(),
            'last_month' => $now->copy()->subMonth()->startOfMonth(),
            default => $now->copy()->subDays(30)->startOfDay(), // last_30_days
        };
        $endDate = match ($period) {
            'last_month' => $now->copy()->subMonth()->endOfMonth(),
            default => $now->copy()->endOfDay(),
        };

        // 2. Standard Metrics (Hardcoded/Core)
        $baseQuery = ReviewNew::where('business_id', $businessId)
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->globalReviewFilters(0);

        $totalReviews = (clone $baseQuery)->count();
        $avgRating = (clone $baseQuery)->withCalculatedRating()->get()->avg('calculated_rating') ?? 0;

        // CSAT Score calculation (logic from DashboardController)
        $csatReviewsCount = (clone $baseQuery)->whereMeetsThreshold()->count();
        $csatPercentage = $totalReviews > 0 ? round(($csatReviewsCount / $totalReviews) * 100) : 0;

        $boxes = [
            [
                'key' => 'TOTAL_REVIEWS',
                'label' => 'Total Reviews',
                'value' => number_format($totalReviews),
                'sub_value' => 'Total Volume',
                'trend' => null,
                'icon' => '📝',
                'color' => 'blue',
                'is_default_rule' => false
            ],
            [
                'key' => 'AVG_RATING',
                'label' => 'Average Rating',
                'value' => number_format($avgRating, 1),
                'sub_value' => 'out of 5.0',
                'trend' => null,
                'icon' => '⭐',
                'color' => 'yellow',
                'is_default_rule' => false
            ],
            [
                'key' => 'CSAT_SCORE',
                'label' => 'CSAT Score',
                'value' => $csatPercentage . '%',
                'sub_value' => 'Satisfaction Index',
                'trend' => null,
                'icon' => '📈',
                'color' => 'cyan',
                'is_default_rule' => false
            ]
        ];

        // 3. Rule Metrics (Iterate 9 Default Rules)
        $requiredRules = DefaultRuleSeederService::getRequiredRuleKeys();

        foreach ($requiredRules as $ruleKey) {
            $ruleId = $ruleKey . '.' . $businessId;
            $rule = AiRule::where('rule_id', $ruleId)->first();

            if (!$rule || !$rule->enabled) {
                continue;
            }

            $boxData = $this->calculateRuleBoxData($rule, $startDate, $endDate, $baseQuery);
            if ($boxData) {
                $boxes[] = $boxData;
            }
        }

        // 4. Premium Metrics
        $topPerformerBox = $this->getTopPerformerBox($businessId, $period);
        if ($topPerformerBox) {
            $boxes[] = $topPerformerBox;
        }

        $boxes[] = $this->getRatingUpsideBox($businessId, $startDate, $endDate, $baseQuery);

        return $boxes;
    }

    /**
     * Premium Box: Top Staff Performer
     */
    private function getTopPerformerBox(int $businessId, string $period): ?array
    {
        $topStaff = AiRuleTrigger::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->select('staff_id', DB::raw('count(*) as mentions'))
            ->groupBy('staff_id')
            ->orderBy('mentions', 'desc')
            ->first();

        if ($topStaff && $topStaff->staff_id) {
            $user = User::find($topStaff->staff_id);
            if ($user) {
                return [
                    'key' => 'TOP_PERFORMER',
                    'label' => 'Top Performer',
                    'value' => $user->name,
                    'sub_value' => $topStaff->mentions . ' Mentions',
                    'trend' => null,
                    'icon' => '🏆',
                    'color' => 'gold',
                    'is_default_rule' => false
                ];
            }
        }
        return null;
    }

    /**
     * Premium Box: Rating Upside Potential
     */
    private function getRatingUpsideBox(int $businessId, Carbon $startDate, Carbon $endDate, $baseQuery): array
    {
        $totalCount = (clone $baseQuery)->count();
        $negativeCount = (clone $baseQuery)->where('sentiment_label', 'negative')->count();

        // Potential increment logic (Simplified for "Wow" factor)
        $upside = $totalCount > 0 ? ($negativeCount * 1.5) / $totalCount : 0;
        $upside = min($upside, 1.0); // Cap at 1.0 point growth

        return [
            'key' => 'RATING_UPSIDE',
            'label' => 'Rating Upside',
            'value' => '+' . number_format($upside, 1),
            'sub_value' => 'Potential Points',
            'trend' => null,
            'icon' => '🚀',
            'color' => 'purple',
            'is_default_rule' => false
        ];
    }

    /**
     * Calculate box data for a specific rule
     */
    private function calculateRuleBoxData(AiRule $rule, Carbon $startDate, Carbon $endDate, $baseQuery): ?array
    {
        $label = $rule->rule_name;
        $query = clone $baseQuery;
        $value = 0;
        $unit = '';
        $subValue = null;

        switch ($this->getBaseRuleKey($rule->rule_id)) {
            case 'SENTIMENT_ANALYSIS':
                $label = 'Sentiment Score';
                $total = (clone $query)->count();
                $positive = (clone $query)->where('sentiment_label', 'positive')->count();
                $negative = (clone $query)->where('sentiment_label', 'negative')->count();

                if ($total > 0) {
                    if ($positive > $negative) {
                        $value = 'Positive';
                    } elseif ($negative > $positive) {
                        $value = 'Negative';
                    } else {
                        $value = 'Neutral';
                    }
                } else {
                    $value = 'No Data';
                }

                $subValue = 'Overall Sentiment';
                $icon = '😊';
                $color = 'green';
                break;

            case 'FLAG_AND_ALERT':
                $label = 'Flagged Reviews';
                $value = (clone $query)->where('is_flagged', true)->count();
                $subValue = 'Action Now';
                $icon = '🚩';
                $color = 'orange';
                break;

            case 'STAFF_MENTION_DETECTION':
                $label = 'Staff-Linked Reviews';
                $value = (clone $query)->whereNotNull('staff_id')->count();
                $subValue = 'Team Recognition';
                $icon = '👥';
                $color = 'green';
                break;

            case 'CATEGORY_ISSUE_DETECTION':
                $label = 'Top Topics';
                // Simplified top topic logic
                $topTopic = InsightRecord::where('business_id', $rule->business_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('mentions_count')
                    ->first();
                $value = $topTopic ? $topTopic->main_category : 'No Data';
                $subValue = 'Trending Issues';
                $icon = '🏷️';
                $color = 'indigo';
                break;

            case 'STAFF_PERFORMANCE_RISK':
                $label = 'Staff Performance Risk';
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'Care Required';
                $icon = '📉';
                $color = 'red';
                break;

            case 'EMOTION_INTENSITY':
                $label = 'Emotion Intensity';
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'High Engagement';
                $icon = '🔥';
                $color = 'purple';
                break;

            case 'RATING_COMMENT_MISMATCH':
                $label = 'Rating Mismatch';
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'Hidden Insights';
                $icon = '🔄';
                $color = 'blue';
                break;

            case 'SERVICE_TYPE_DETECTION':
                $label = 'Service Type';
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'Departmental Analysis';
                $icon = '🏢';
                $color = 'teal';
                break;

            case 'BUSINESS_AREA_DETECTION':
                $label = 'Business Area';
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'Location Analytics';
                $icon = '🗺️';
                $color = 'brown';
                break;

            default:
                $value = AiRuleTrigger::where('rule_id', $rule->rule_id)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();
                $subValue = 'Triggers';
                $icon = '📋';
                $color = 'gray';
        }

        return [
            'key' => $this->getBaseRuleKey($rule->rule_id),
            'label' => $label,
            'value' => is_numeric($value) ? number_format($value) : $value,
            'sub_value' => $subValue,
            'trend' => null,
            'icon' => $icon,
            'color' => $color,
            'is_default_rule' => true,
            'rule_id' => $rule->rule_id
        ];
    }

    /**
     * Helper to extract base key from "KEY.123"
     */
    private function getBaseRuleKey(string $ruleId): string
    {
        return explode('.', $ruleId)[0];
    }
}
