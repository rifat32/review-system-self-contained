<?php

namespace App\Services\Rule;

use App\Models\{AiRule, ReviewNew, Business, InsightRecord, AiRuleEvaluation, Alert, Recommendation};
use App\Services\AIProcessor\ConfidenceCalculatorService;

use App\Services\Rule\RuleExecutionService;


class RuleEngineService
{
    private ConfidenceCalculatorService $confidenceCalculatorService;
    private RuleExecutionService $executionService;

    public function __construct(
        ConfidenceCalculatorService $confidenceCalculatorService,
        RuleExecutionService $executionService
    ) {
        $this->confidenceCalculatorService = $confidenceCalculatorService;
        $this->executionService = $executionService;
    }

    // PUBLIC METHODS

    public function matchRulesToInsight(InsightRecord $insight): array
    {
        $rules = $this->getApplicableRules($insight->business_id);
        $matchedRules = [];

        foreach ($rules as $rule) {
            if ($this->ruleMatchesInsight($rule, $insight)) {
                $matchedRules[] = [
                    'rule' => $rule,
                    'confidence' => $this->calculateRuleConfidence($rule, $insight),
                    'severity' => $this->determineSeverity($rule, $insight),
                    'explanation' => $this->generateRuleExplanation($rule, $insight)
                ];
            }
        }

        return $matchedRules;
    }

    public function generateRecommendation(AiRule $rule, InsightRecord $insight): array
    {
        // $actions = $rule->actions;

        // if (!isset($actions['suggest_action']))
        //     return [];

        // instead of suggest_action use existing action and proceed.
        $text = $rule->short_explanation ?? $rule->rule_name;
        $filledTemplate = $this->fillTemplate($text, $insight->business_id, $rule, $insight);

        return [
            'type' => $rule->category ?? 'business',
            'text' => $filledTemplate,
            'priority' => $rule->priority,
            'confidence' => $this->calculateRuleConfidence($rule, $insight),
            'evidence' => [
                'mentions' => $insight->mentions_count,
                'severity' => $insight->severity,
                'trend' => $insight->trend,
                'review_ids' => array_slice($insight->review_ids ?? [], 0, 10)
            ],
            'explainability' => $this->generateExplainability($rule, $insight)
        ];
    }



    // PRIVATE METHODS

    private function getApplicableRules(int $businessId)
    {
        $business = Business::find($businessId);

        return AiRule::where('enabled', true)
            ->where(function ($query) use ($businessId, $business) {
                $query->where('scope', 'system')
                    ->orWhere(function ($q) use ($business) {
                        if ($business?->type) {
                            $q->where('scope', 'business_type')
                                ->where('business_type', $business->type);
                        }
                    })
                    ->orWhere(function ($q) use ($businessId) {
                        $q->where('scope', 'business')
                            ->where('business_id', $businessId);
                    });
            })
            ->get();
    }

    private function ruleMatchesInsight(AiRule $rule, InsightRecord $insight): bool
    {
        $conditions = $rule->conditions;

        // Dynamic category matching
        if (isset($conditions['category_match'])) {
            if (!$this->matchesCategoryPattern($conditions['category_match'], $insight)) {
                return false;
            }
        }

        // Staff condition matching
        if (isset($conditions['staff'])) {
            if (!$this->matchesStaffCondition($conditions['staff'], $insight)) {
                return false;
            }
        }

        // Check all other conditions
        return $this->checkAllConditions($conditions, $insight);
    }

    private function matchesCategoryPattern(array $condition, InsightRecord $insight): bool
    {
        $mainCat = $condition['main_category'] ?? null;
        $subCat = $condition['sub_category'] ?? null;

        // 1. Exact match (Food|Temperature)
        if ($mainCat && $subCat) {
            return $insight->main_category === $mainCat && $insight->sub_category === $subCat;
        }

        // 2. Category-only match (Food|any)
        if ($mainCat && !$subCat) {
            return $insight->main_category === $mainCat;
        }

        // 3. Wildcard matching
        if (isset($condition['match_type'])) {
            return match ($condition['match_type']) {
                'contains' => str_contains($insight->main_category, $condition['contains'] ?? ''),
                'starts_with' => str_starts_with($insight->main_category, $condition['starts_with'] ?? ''),
                'ends_with' => str_ends_with($insight->main_category, $condition['ends_with'] ?? ''),
                'regex' => preg_match($condition['pattern'], $insight->main_category),
                default => false
            };
        }

        return false;
    }

    private function matchesStaffCondition(array $condition, InsightRecord $insight): bool
    {
        // Generic staff/process detection
        if (isset($condition['generic_type'])) {
            return match ($condition['generic_type']) {
                'staff' => $insight->staff_blame_detected === true,
                'process' => $insight->staff_blame_detected === false,
                'any' => true,
                default => false
            };
        }

        // Specific staff conditions
        if (isset($condition['blame_detected']) && $condition['blame_detected'] !== $insight->staff_blame_detected) {
            return false;
        }

        return true;
    }

    private function checkAllConditions(array $conditions, InsightRecord $insight): bool
    {
        // Mentions count
        if (isset($conditions['repeat_occurrence']['count'])) {
            if ($insight->mentions_count < $conditions['repeat_occurrence']['count']) {
                return false;
            }
        }

        // Severity
        if (isset($conditions['severity'])) {
            $severity = $conditions['severity'];
            if (is_array($severity) && !in_array($insight->severity, $severity)) {
                return false;
            } elseif (is_string($severity) && $insight->severity !== $severity) {
                return false;
            }
        }

        // Confidence level
        if (isset($conditions['confidence_min'])) {
            $confScore = $this->confidenceCalculatorService->calculateInsightConfidence($insight)['score'];
            if ($confScore < $conditions['confidence_min']) {
                return false;
            }
        }

        return true;
    }

    private function calculateRuleConfidence(AiRule $rule, InsightRecord $insight): string
    {
        // Use ConfidenceCalculator for consistency
        $confidenceData = $this->confidenceCalculatorService->calculateInsightConfidence($insight);

        // Rule-specific confidence adjustment
        $adjustments = config('ai.insights.confidence.adjustments', []);
        $adjustment = $adjustments[$rule->priority] ?? 0;

        $adjustedScore = min(100, $confidenceData['score'] + $adjustment);

        $thresholds = config('ai.insights.confidence.thresholds', []);

        return match (true) {
            $adjustedScore >= ($thresholds['high'] ?? 80) => 'high',
            $adjustedScore >= ($thresholds['medium'] ?? 60) => 'medium',
            default => 'low'
        };
    }

    private function determineSeverity(AiRule $rule, InsightRecord $insight): string
    {
        // Start with the insight's severity
        $baseSeverity = $insight->severity ?? 'medium';

        // Adjust severity based on rule priority
        if ($rule->priority === 'critical') {
            return 'critical';
        }

        if ($rule->priority === 'high') {
            // If rule is high priority, escalate severity
            return match ($baseSeverity) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'critical',
                'critical' => 'critical',
                default => 'high'
            };
        }

        // Adjust based on mentions count
        $mentionsCount = $insight->mentions_count ?? 0;
        $escalationConfig = config('ai.insights.severity_escalation', []);

        if ($mentionsCount >= ($escalationConfig['very_high_frequency'] ?? 10)) {
            // High frequency issues should be escalated
            return match ($baseSeverity) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'critical',
                'critical' => 'critical',
                default => 'high'
            };
        }

        if ($mentionsCount >= ($escalationConfig['high_frequency'] ?? 5)) {
            // Moderate frequency issues
            return match ($baseSeverity) {
                'low' => 'medium',
                default => $baseSeverity
            };
        }

        // Return base severity for low frequency issues
        return $baseSeverity;
    }

    private function generateExplainability(AiRule $rule, InsightRecord $insight): array
    {
        $conditions = $rule->conditions;

        return [
            'rule_used' => $rule->rule_name,
            'rule_id' => $rule->rule_id,
            'conditions_met' => [
                'category' => "{$insight->main_category} - {$insight->sub_category}",
                'mentions' => $insight->mentions_count,
                'severity' => $insight->severity,
                'trend' => $insight->trend,
                'staff_related' => $insight->staff_blame_detected
            ],
            'confidence_factors' => $this->confidenceCalculatorService->calculateInsightConfidence($insight)['factors'],
            'time_period' => [
                'start' => $insight->time_window_start?->format('Y-m-d'),
                'end' => $insight->time_window_end?->format('Y-m-d')
            ]
        ];
    }

    private function fillTemplate(string $template, int $businessId, AiRule $rule, InsightRecord $insight): string
    {
        $business = Business::find($businessId);
        $conditions = $rule->conditions;

        $replacements = [
            '{{main_category}}' => $insight->main_category,
            '{{sub_category}}' => $insight->sub_category,
            '{{count}}' => $insight->mentions_count,
            '{{business_name}}' => $business->name ?? 'your business',
            '{{severity}}' => $insight->severity,
            '{{trend}}' => $insight->trend,
            '{{period}}' => $insight->time_window_start?->format('M d') . ' - ' . $insight->time_window_end?->format('M d')
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }



    private function generateRuleExplanation(AiRule $rule, InsightRecord $insight): string
    {
        $conditions = $rule->conditions;

        $parts = [];
        if (isset($conditions['category_match'])) {
            $parts[] = "Category: {$insight->main_category}";
            if ($insight->sub_category) {
                $parts[] = "Sub-category: {$insight->sub_category}";
            }
        }
        if (isset($conditions['repeat_occurrence']['count'])) {
            $parts[] = "Mentions: {$insight->mentions_count} (threshold: {$conditions['repeat_occurrence']['count']})";
        }
        if ($insight->staff_blame_detected) {
            $parts[] = "Staff-related issue";
        }

        return implode(', ', $parts);
    }

    // Add to RuleEngineHelper class

    public static function getPositiveSentimentThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.positive_score', 0.7);
    }

    public static function getNegativeSentimentThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.negative_score', 0.4);
    }


    public static function getNeutralUpperThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.positive_score', 0.7) - 0.01;
    }

    public static function getCsatThreshold(): float
    {

        return (float) \config('ai.sentiment.thresholds.csat', 4.0);
    }

    public static function getHighRatingThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.high_rating', 4.0);
    }

    public static function getNeutralRatingThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.neutral_rating', 3.0);
    }

    public static function getLowRatingThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.low_rating', 2.0);
    }

    public static function getDefaultSentimentLabel(): string
    {
        return (string) \config('ai.sentiment.thresholds.default_label', 'neutral');
    }

    public static function getSentimentLabelFromScore(float $score): string
    {
        // Use threshold-based logic (consistent with entire system)
        $positiveThreshold = self::getPositiveSentimentThreshold();
        $negativeThreshold = self::getNegativeSentimentThreshold();

        if ($score >= $positiveThreshold) {
            return 'positive';
        } elseif ($score >= $negativeThreshold) {
            return 'neutral';
        } else {
            return 'negative';
        }
    }

    /**
     * Determine an aggregated sentiment label based on counts (Plurality-based)
     *
     * @param int $positive
     * @param int $neutral
     * @param int $negative
     * @return string
     */
    public static function determineAggregatedLabel(int $positive, int $neutral, int $negative): string
    {
        $total = $positive + $neutral + $negative;
        if ($total === 0) {
            return self::getDefaultSentimentLabel();
        }

        $maxCount = max($positive, $neutral, $negative);

        if ($negative === $maxCount && $negative > 0) {
            // Prioritize negative if it's the highest (or tied for highest)
            return 'negative';
        } elseif ($positive === $maxCount && $positive > 0) {
            // Then positive
            return 'positive';
        } else {
            // Otherwise neutral
            return 'neutral';
        }
    }

    public static function getTrendThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.trend_threshold', 0.1);
    }

    public static function getImprovingTrendMessage(): string
    {
        return (string) \config('ai.sentiment.thresholds.improving_trend_message', 'Improving sentiment trend');
    }

    public static function getDecliningTrendMessage(): string
    {
        return (string) \config('ai.sentiment.thresholds.declining_trend_message', 'Declining sentiment trend');
    }




    public function getDefaultTrainingRecommendation(): string
    {
        return \config('ai.training.training_recommendations.0.title', 'General Training');
    }

    public function mapSuggestionsToSkillGaps($suggestions): array
    {
        // Suggestions are already dynamic AI outputs
        // We just need to ensure they are clean and unique
        return \collect($suggestions)
            ->map(fn($s) => ucwords((string)$s))
            ->unique()
            ->values()
            ->toArray();
    }


    public function generateRatingPrediction(float $avgRating): array
    {
        $predictionRules = \config('ai.performance.prediction_rules', []);

        foreach ($predictionRules as $rule) {
            if ($avgRating >= ($rule['min'] ?? 0) && $avgRating <= ($rule['max'] ?? 5)) {
                return [
                    'prediction' => $rule['prediction'] ?? 'Improving identified issues could boost overall rating.',
                    'estimated_impact' => $rule['impact'] ?? '+0.05 points',
                    'potential_rating' => min(5, $avgRating + ($rule['increase'] ?? 0.05))
                ];
            }
        }

        return [
            'prediction' => 'Improving identified issues could boost overall rating.',
            'estimated_impact' => '+0.05 points',
            'potential_rating' => min(5, $avgRating + 0.05)
        ];
    }



    public function getHighPriorityThreshold(): int
    {
        return (int) \config('ai.sentiment.thresholds.high_priority_threshold', 4);
    }







    public function generateBranchComparisonInsights($branchesData): array
    {
        if (empty($branchesData)) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        // Generate dynamic insights from actual branch data
        $findings = [];
        $totalBranches = count($branchesData);

        if ($totalBranches >= 2) {
            // Find best and worst performing branches
            $ratings = array_column(array_column($branchesData, 'metrics'), 'average_rating');
            $maxRating = max($ratings);
            $minRating = min($ratings);

            $findings[] = "Comparing {$totalBranches} branches with ratings ranging from {$minRating} to {$maxRating}";

            // Identify performance gaps
            if ($maxRating - $minRating > 0.5) {
                $findings[] = "Significant performance gap detected between branches";
            }
        }

        return [
            'overview' => "Analysis of {$totalBranches} branch" . ($totalBranches > 1  ? 'es' : '') . " completed successfully.",
            'key_findings' => $findings
        ];
    }

    public function generateComparisonHighlights($branchesData): array
    {
        if (empty($branchesData) || count($branchesData) < 2) {
            return [];
        }

        $highlights = [];

        // 1. Overall Rating Comparison
        usort($branchesData, fn($a, $b) => $b['metrics']['average_rating'] <=> $a['metrics']['average_rating']);
        $bestRated = $branchesData[0];
        $worstRated = $branchesData[count($branchesData) - 1];

        $ratingGap = $bestRated['metrics']['average_rating'] - $worstRated['metrics']['average_rating'];

        if ($ratingGap >= 0) {
            $bestLabel = $this->getPerformanceLabelFromRating($bestRated['metrics']['average_rating']);

            // Highlight the winner if the gap is noticeable OR if the winner is 'Excellent'/'Very Good'
            if ($ratingGap >= 0.1 || $bestRated['metrics']['average_rating'] >= 4.0) {
                $highlights[] = [
                    'type' => 'positive',
                    'category' => 'Top Performance',
                    'message' => "{$bestRated['branch']['name']} leads with a {$bestRated['metrics']['average_rating']} rating ({$bestLabel})."
                ];
            } else {
                $highlights[] = [
                    'type' => 'info',
                    'category' => 'Top Performance',
                    'message' => "All branches are performing equally with a {$bestRated['metrics']['average_rating']} rating."
                ];
            }

            // Highlight significant lag
            if ($ratingGap >= 0.5) {
                $highlights[] = [
                    'type' => 'warning',
                    'category' => 'Performance Gap',
                    'message' => "{$worstRated['branch']['name']} is lagging behind by " . round($ratingGap, 1) . " points."
                ];
            }
        }

        // 2. Sentiment Analysis
        usort($branchesData, fn($a, $b) => $b['metrics']['ai_sentiment_score'] <=> $a['metrics']['ai_sentiment_score']);
        $bestSentiment = $branchesData[0];
        $worstSentiment = $branchesData[count($branchesData) - 1];

        $sentimentGap = $bestSentiment['metrics']['ai_sentiment_score'] - $worstSentiment['metrics']['ai_sentiment_score'];

        if ($sentimentGap >= 5) {
            $sentimentLabel = $this->getSentimentLabelByPercentage($bestSentiment['metrics']['ai_sentiment_score']);
            $highlights[] = [
                'type' => 'positive',
                'category' => 'Customer Sentiment',
                'message' => "{$bestSentiment['branch']['name']} leads in sentiment with {$bestSentiment['metrics']['ai_sentiment_score']}% positive feedback ({$sentimentLabel})."
            ];
        }

        // 3. Response Rate
        usort($branchesData, fn($a, $b) => $b['metrics']['response_rate'] <=> $a['metrics']['response_rate']);
        $bestResponse = $branchesData[0];
        $worstResponse = $branchesData[count($branchesData) - 1];
        $responseGap = $bestResponse['metrics']['response_rate'] - $worstResponse['metrics']['response_rate'];

        if ($bestResponse['metrics']['response_rate'] > 0 && $responseGap >= 5) {
            $highlights[] = [
                'type' => 'info',
                'category' => 'Engagement',
                'message' => "{$bestResponse['branch']['name']} is the most responsive ({$bestResponse['metrics']['response_rate']}% response rate)."
            ];
        }

        // 4. CSAT Score
        usort($branchesData, fn($a, $b) => $b['metrics']['csat_score'] <=> $a['metrics']['csat_score']);
        $bestCSAT = $branchesData[0];
        $worstCSAT = $branchesData[count($branchesData) - 1];
        $csatGap = $bestCSAT['metrics']['csat_score'] - $worstCSAT['metrics']['csat_score'];

        if ($csatGap >= 5) {
            # Use same label helper since CSAT is also a percentage
            $csatLabel = $this->getSentimentLabelByPercentage($bestCSAT['metrics']['csat_score']);
            $highlights[] = [
                'type' => 'positive',
                'category' => 'Satisfaction',
                'message' => "{$bestCSAT['branch']['name']} has the highest Customer Satisfaction score ({$bestCSAT['metrics']['csat_score']}%) which is '{$csatLabel}'."
            ];
        }

        return array_slice($highlights, 0, 5);
    }

    public function determineOverallSentiment(int $positiveReviews, int $totalReviews): string
    {
        if ($totalReviews === 0)
            return 'Neutral';

        $percentage = ($positiveReviews / $totalReviews) * 100;
        $sentimentRules = \config('ai.sentiment.sentiment_rules', []);

        foreach ($sentimentRules as $rule) {
            $conditions = $rule;
            if ($percentage >= ($conditions['min'] ?? 0) && $percentage <= ($conditions['max'] ?? 100)) {
                return $rule['label'] ?? 'Neutral';
            }
        }

        return 'Neutral';
    }

    public function getSentimentLabelByPercentage(float $percentage): string
    {
        $sentimentRules = \config('ai.sentiment.sentiment_rules', []);

        foreach ($sentimentRules as $rule) {
            $conditions = $rule;
            if ($percentage >= ($conditions['min'] ?? 0) && $percentage <= ($conditions['max'] ?? 100)) {
                return $rule['label'] ?? 'Neutral';
            }
        }

        return 'Neutral';
    }

    public static function getSentimentDescription(float $percentage): string
    {
        $descriptions = \config('ai.sentiment.sentiment_descriptions', []);

        foreach ($descriptions as $desc) {
            if ($percentage >= ($desc['min'] ?? 0) && $percentage <= ($desc['max'] ?? 100)) {
                return $desc['value'] ?? 'mixed';
            }
        }

        return 'mixed';
    }



    public static function getFrequentIssueThreshold(): int
    {
        return (int) \config('ai.sentiment.thresholds.frequent_issue_threshold', 5);
    }






    public function getPerformanceLabelFromRating(float $rating): string
    {
        $labels = \config('ai.performance.rating_labels', []);

        foreach ($labels as $label) {
            if ($rating >= ($label['min'] ?? 0) && $rating <= ($label['max'] ?? 5)) {
                return $label['label'] ?? 'Average';
            }
        }

        return 'Average';
    }



    public function getMinimumReviewsForTrendAnalysis(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_reviews_trend', 4);
    }

    public function getInsufficientDataForTrendMessage(): string
    {
        return (string) \config('ai.sentiment.thresholds.insufficient_trend_data', 'insufficient_data');
    }

    public function getStableTrendMessage(): string
    {
        return (string) \config('ai.sentiment.thresholds.stable_trend_message', 'stable');
    }

    public function getChangeType($value): string
    {
        return $value >= 0 ? 'positive' : 'negative';
    }






    public function analyzeSkillGaps($reviews): array
    {
        $strengths = [];
        $improvementAreas = [];

        foreach ($reviews as $review) {
            $openaiData = $review->openai_raw_response ?: [];
            $skills = $openaiData['staff_intelligence']['soft_skill_scores'] ?? [];
            foreach ($skills as $skill => $score) {
                if ($score >= 4) {
                    $strengths[$skill] = ($strengths[$skill] ?? 0) + 1;
                } elseif ($score <= 2) {
                    $improvementAreas[$skill] = ($improvementAreas[$skill] ?? 0) + 1;
                }
            }
        }

        arsort($strengths);
        arsort($improvementAreas);

        return [
            'strengths' => array_keys(array_slice($strengths, 0, 3)),
            'improvement_areas' => array_keys(array_slice($improvementAreas, 0, 3))
        ];
    }

    public function calculateCustomerTone($reviews): array
    {
        $tones = [];
        foreach ($reviews as $review) {
            $tone = $review->emotion['primary'] ?? 'neutral';
            if ($tone) {
                $tones[$tone] = ($tones[$tone] ?? 0) + 1;
            }
        }

        arsort($tones);
        return $tones;
    }

    public function getSentimentGapMessage($gap, $staffAName = null, $staffBName = null): string
    {
        $staffALabel = $staffAName ?? 'Staff A';
        $staffBLabel = $staffBName ?? 'Staff B';

        if ($gap > 0) {
            return "{$staffALabel} has " . abs(round($gap, 1)) . "% more positive reviews than {$staffBLabel}";
        } elseif ($gap < 0) {
            return "{$staffBLabel} has " . abs(round($gap, 1)) . "% more positive reviews than {$staffALabel}";
        } else {
            return "{$staffALabel} and {$staffBLabel} have similar positive sentiment";
        }
    }

    public function getRatingGapMessage($gap, $staffAName = null, $staffBName = null): string
    {
        $staffALabel = $staffAName ?? 'Staff A';
        $staffBLabel = $staffBName ?? 'Staff B';

        if ($gap > 0) {
            return "{$staffALabel} is performing better with a " . abs(round($gap, 1)) . " star advantage over {$staffBLabel}";
        } elseif ($gap < 0) {
            return "{$staffBLabel} is performing better with a " . abs(round($gap, 1)) . " star advantage over {$staffALabel}";
        } else {
            return "{$staffALabel} and {$staffBLabel} are performing equally";
        }
    }

    public function generateSummaryTemplate(float $positivePercent, float $negativePercent): string
    {
        $template = \config('ai.sentiment.thresholds.summary_template', "Customers are {{positive}}% positive and {{negative}}% negative.");

        return str_replace(
            ['{{positive}}', '{{negative}}'],
            [$positivePercent, $negativePercent],
            $template
        );
    }

    public function getDefaultSummaryPhrase(): string
    {
        return (string) \config('ai.sentiment.thresholds.default_summary_phrase', "Common themes from recent feedback are analyzed in the insights section.");
    }

    public function getHighIssueThreshold(): int
    {
        return (int) \config('ai.sentiment.thresholds.high_issue_threshold', 3);
    }

    public function getMinimumMentionsForRecommendation(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_mentions_recommendation', 2);
    }

    public function getStaffTrainingRecommendations(int $staffId, int $businessId): array
    {
        return Recommendation::where('business_id', $businessId)
            ->where('type', 'training')
            ->whereJsonContains('evidence', ['staff_id' => $staffId])
            ->latest()
            ->limit(3)
            ->get()
            ->map(function ($rec) {
                return [
                    'title' => $rec->text,
                    'description' => $rec->text,
                    'priority' => $rec->priority >= 4 ? 'High' : ($rec->priority >= 2 ? 'Medium' : 'Low'),
                    'category' => 'Staff Training',
                    'type' => 'training'
                ];
            })->toArray();
    }









    public function generateDashboardInsights($sentimentData, $topTopics, $totalReviews): array
    {
        $templates = \config('ai.insights.dashboard_insight_templates', []);

        $insights = [
            'summary' => '',
            'key_findings' => [],
            'recommendations' => []
        ];

        if ($totalReviews === 0) {
            $insights['summary'] = 'No reviews available for analysis.';
        } else {
            $summaryTemplate = $templates['satisfaction'] ?? 'Customer satisfaction has {{trend}} by {{delta}} points.';
            $insights['summary'] = str_replace(
                ['{{trend}}', '{{delta}}'],
                [$sentimentData['positive_percentage'] >= 50 ? 'improved' : 'declined', abs($sentimentData['positive_percentage'] - 50)],
                $summaryTemplate
            );
        }

        return $insights;
    }

    public static function getMinReviewsStaffAnalysis(): int
    {
        return (int) config('ai.sentiment.thresholds.min_reviews_staff_analysis', 3);
    }

    public static function getMinReviewsTopStaff(): int
    {
        return (int) config('ai.sentiment.thresholds.min_reviews_top_staff', 5);
    }
}
