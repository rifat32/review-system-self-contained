<?php

namespace App\Services\Rule;

use App\Models\{AiRule, ReviewNew, Business, InsightRecord, AiRuleEvaluation, Alert, Recommendation};
use App\Services\AIProcessor\ConfidenceCalculatorService;
use App\Services\Rule\AutoRuleCreatorService;
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
        $rules = $this->getApplicableRules($insight);
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
        $actions = $rule->actions;

        if (!isset($actions['suggest_action']))
            return [];

        $template = $this->getRecommendationTemplate($actions['suggest_action']['template_id'] ?? 'GENERAL');
        $filledTemplate = $this->fillTemplate($template, $insight->business_id, $rule, $insight);

        return [
            'type' => $actions['suggest_action']['type'] ?? 'business',
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

    public function evaluateReviewRules(ReviewNew $review, array $openaiData): array
    {
        $rules = $this->getApplicableRulesForReview($review->business_id);
        $results = [];

        foreach ($rules as $rule) {
            // Apply branch filtering (done again in executionService but kept here for early exit if needed, 
            // though executionService handles it more robustly now)
            if (!empty($rule->branch_ids)) {
                if (!$review->branch_id || !in_array($review->branch_id, $rule->branch_ids)) {
                    continue;
                }
            }

            // Redirect to RuleExecutionService for unified execution, recording, and deduplication
            // This ensures all rule matches are recorded in ai_rule_triggers
            $this->executionService->runSingleRule($rule, $review, $openaiData);

            // If it matched, we still want to return a result for the log in OpenAIProcessorService
            // Note: executionService already matched it, but for simplicity of the return value,
            // we do a quick match check here or just assume it might have matched if runSingleRule didn't throw.
            // Actually, to avoid double matching, we could update runSingleRule to return match status.
            if ($this->ruleMatchesReview($rule, $review, $openaiData)) {
                $results[] = $this->createRuleEvaluation($rule, $review, $openaiData);
            }
        }

        // Auto-create rules for new patterns
        AutoRuleCreatorService::checkAndCreateRules($review, $openaiData);

        return $results;
    }

    // PRIVATE METHODS

    private function getApplicableRules(InsightRecord $insight)
    {
        $business = Business::find($insight->business_id);

        return AiRule::where(function ($query) use ($insight, $business) {
            $query->where('scope', 'system')
                ->orWhere(function ($q) use ($business) {
                    if ($business?->type) {
                        $q->where('scope', 'business_type')
                            ->where('business_type', $business->type);
                    }
                })
                ->orWhere(function ($q) use ($insight) {
                    $q->where('scope', 'business')
                        ->where('business_id', $insight->business_id);
                });
        })->where('enabled', true)->get();
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
        $adjustment = match ($rule->priority) {
            'critical' => 20,
            'high' => 10,
            default => 0
        };

        $adjustedScore = min(100, $confidenceData['score'] + $adjustment);

        return match (true) {
            $adjustedScore >= 80 => 'high',
            $adjustedScore >= 60 => 'medium',
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

        if ($mentionsCount >= 10) {
            // High frequency issues should be escalated
            return match ($baseSeverity) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'critical',
                'critical' => 'critical',
                default => 'high'
            };
        }

        if ($mentionsCount >= 5) {
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

    private function getRecommendationTemplate(string $templateId): string
    {
        // 1. Try to get from database first (seeded templates)
        $dbTemplate = AiRule::where('category', 'recommendation_templates')
            ->where('key_name', $templateId)
            ->where('enabled', true)
            ->value('value');

        if ($dbTemplate) {
            return $dbTemplate;
        }

        // 2. Fallback to config
        return \config('ai.insights.recommendation_templates.' . $templateId)
            ?? \config('ai.insights.recommendation_templates.GENERAL', 'Improve {{main_category}} based on {{count}} customer mentions.');
    }

    // REVIEW-LEVEL RULE MATCHING (for real-time evaluation)

    private function getApplicableRulesForReview(int $businessId)
    {
        $business = Business::find($businessId);

        return AiRule::where(function ($query) use ($businessId, $business) {
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
        })->where('enabled', true)->where('category', '!=', 'trend')->get(); // Exclude trend rules for single reviews
    }

    private function ruleMatchesReview(AiRule $rule, ReviewNew $review, array $openaiData): bool
    {
        // Use the centralized ConditionBuilderService for robust evaluation
        return ConditionBuilderService::evaluateConditions(
            $rule->conditions,
            $review,
            $openaiData
        );
    }

    private function reviewContainsCategory(array $openaiData, array $condition): bool
    {
        $categories = $openaiData['category_analysis'] ?? [];

        foreach ($categories as $category) {
            $mainCat = $category['main_category'] ?? '';
            $subCat = $category['sub_category'] ?? '';

            if (isset($condition['main_category']) && isset($condition['sub_category'])) {
                if ($mainCat === $condition['main_category'] && $subCat === $condition['sub_category']) {
                    return true;
                }
            } elseif (isset($condition['main_category'])) {
                if ($mainCat === $condition['main_category']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function createRuleEvaluation(AiRule $rule, ReviewNew $review, array $openaiData): array
    {
        return [
            'rule_id' => $rule->rule_id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'triggered' => true,
            'severity' => $rule->priority,
            'explanation' => "Rule '{$rule->rule_name}' triggered for review #{$review->id}",
            'confidence' => 0.8, // Default confidence for review-level matches
            'evaluation_data' => [
                'matched_conditions' => $rule->conditions,
                'review_categories' => $openaiData['category_analysis'] ?? [],
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    public function triggerRuleActions(AiRule $rule, ReviewNew $review, array $openaiData): void
    {
        $actions = $rule->actions;
        if (empty($actions))
            return;

        // Trigger alert
        if ($actions['trigger_alert'] ?? false) {
            Alert::create([
                'business_id' => $review->business_id,
                'type' => 'rule_triggered',
                'title' => $rule->rule_name,
                'message' => "Rule triggered for review #{$review->id}",
                'severity' => $actions['severity'] ?? 'medium',
                'metadata' => [
                    'rule_id' => $rule->rule_id,
                    'review_id' => $review->id,
                    'categories' => $openaiData['category_analysis'] ?? []
                ]
            ]);
        }
    }

    private function checkRatingCondition(array $condition, float $rating): bool
    {
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? 0;

        return match ($operator) {
            'lt' => $rating < $value,
            'lte' => $rating <= $value,
            'gt' => $rating > $value,
            'gte' => $rating >= $value,
            'eq' => $rating == $value,
            'between' => $rating >= ($condition['min'] ?? 0) && $rating <= ($condition['max'] ?? 5),
            default => false
        };
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

    public function getNeutralLowerThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.neutral_lower', 2.1);
    }

    public function getNeutralUpperThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.neutral_upper', 3.9);
    }

    public function getCsatThreshold(): float
    {
        return (float) \config('ai.sentiment.thresholds.csat', 4.0);
    }

    public static function getDefaultSentimentLabel(): string
    {
        return (string) \config('ai.sentiment.thresholds.default_label', 'neutral');
    }

    public static function getSentimentLabelFromScore(float $score): string
    {
        $labels = \config('ai.sentiment.score_labels', []);

        foreach ($labels as $label) {
            if ($score >= ($label['min'] ?? 0) && $score <= ($label['max'] ?? 1)) {
                return $label['label'] ?? 'neutral';
            }
        }

        return 'neutral';
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



    public function extractStaffMentions($reviews): array
    {
        $staffMentions = [];

        foreach ($reviews as $review) {
            if ($review->staff_id) {
                $staffMentions[$review->staff_id] = ($staffMentions[$review->staff_id] ?? 0) + 1;
            }
        }

        return $staffMentions;
    }

    public function getDefaultTrainingRecommendation(): string
    {
        return \config('ai.training.training_recommendations.0.title', 'General Training');
    }

    public function mapSuggestionsToSkillGaps($suggestions): array
    {
        // Suggestions are already dynamic AI outputs
        // We just need to ensure they are clean and unique
        return collect($suggestions)
            ->map(fn($s) => ucwords((string)$s))
            ->unique()
            ->values()
            ->toArray();
    }

    public function getOpportunityKeywords(): array
    {
        return \config('ai.topics.opportunity_keywords', []);
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

    public function getMinimumPraiseForRecommendation(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_praise_recommendation', 2);
    }

    public function getMinimumMentionsForIssue(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_mentions_issue', 2);
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
        return []; // Return empty for now, implement dynamic logic as needed
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

    public function getMinimumReviewsForStaffEvaluation(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_reviews_staff_eval', 3);
    }

    public function getInsufficientDataMessage(): string
    {
        return (string) \config('ai.sentiment.thresholds.insufficient_data_message', 'Insufficient Data');
    }

    public function getStaffEvaluationFromRating(float $rating): string
    {
        $evaluations = \config('ai.training.staff_evaluations', []);

        foreach ($evaluations as $eval) {
            if ($rating >= ($eval['min'] ?? 0) && $rating <= ($eval['max'] ?? 5)) {
                return $eval['label'] ?? 'Consistent';
            }
        }

        return 'Consistent';
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

    public function generateActionForIssue(string $issue, int $evidenceCount): ?array
    {
        $actionItems = \config('ai.insights.action_items', []);

        // This is a simplified version, as the original logic assumed per-issue records in DB
        // For now, let's return a generic action or try to match if we had a map.
        // We'll return null to maintain original behavior if not found.

        return [
            'title' => 'Action Required',
            'description' => 'Address the recurring issue: ' . $issue,
            'priority' => $evidenceCount >= \config('ai.sentiment.thresholds.high_priority_threshold', 4) ? 'high' : 'medium'
        ];
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





    public function getTrainingRecommendations($reviews): array
    {
        if (empty($reviews) || count($reviews) === 0) {
            return [];
        }

        $businessId = null;
        if (is_countable($reviews)) {
            $businessId = $reviews[0]->business_id ?? null;
        }

        // STEP 1: PRIORITIZE DATABASE TABLE (Generated by Cron)
        if ($businessId) {
            $dbRecs = Recommendation::where('business_id', $businessId)
                ->where('type', 'training')
                ->latest()
                ->limit(5)
                ->get();

            if ($dbRecs->isNotEmpty()) {
                return $dbRecs->map(function ($rec) {
                    return [
                        'title' => $rec->text,
                        'description' => $rec->text,
                        'priority' => $rec->priority >= 4 ? 'High' : ($rec->priority >= 2 ? 'Medium' : 'Low'),
                        'category' => 'Training'
                    ];
                })->toArray();
            }
        }

        // STEP 2: FALLBACK TO ON-THE-FLY EXTRACTION
        $recommendations = [];
        $uniqueRecs = [];

        foreach ($reviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $recs = $openaiData['staff_intelligence']['training_recommendations'] ?? [];
            if (empty($recs) && isset($openaiData['recommendations'])) {
                $recs = $openaiData['recommendations'];
            }

            if (!empty($recs) && is_array($recs)) {
                foreach ($recs as $rec) {
                    $recClean = trim((string)$rec);
                    if ($recClean && !isset($uniqueRecs[$recClean])) {
                        $uniqueRecs[$recClean] = true;
                        $recommendations[] = [
                            'title' => $recClean,
                            'description' => $recClean,
                            'priority' => 'Medium',
                            'category' => 'Training'
                        ];
                    }
                }
            }
        }

        return array_slice($recommendations, 0, 5);
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

    public function getSentimentGapMessage($gap): string
    {
        if ($gap > 0) {
            return "Staff A has more positive reviews";
        } elseif ($gap < 0) {
            return "Staff B has more positive reviews";
        } else {
            return "Both have similar positive sentiment";
        }
    }

    public function getRatingGapMessage($gap): string
    {
        if ($gap > 0) {
            return "Staff A is performing better";
        } elseif ($gap < 0) {
            return "Staff B is performing better";
        } else {
            return "Both staff are performing equally";
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
        return (string) \config('ai.sentiment.thresholds.default_summary_phrase', "Common themes include staff friendliness, service speed, and occasional cleanliness concerns.");
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
                    'category' => 'Staff Training'
                ];
            })->toArray();
    }

    public function extractCommonPraise($reviews): array
    {
        $praiseCounts = [];
        $totalReviews = count($reviews);

        foreach ($reviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $categories = $openaiData['category_analysis'] ?? [];

            foreach ($categories as $cat) {
                $sentiment = strtolower($cat['sentiment'] ?? 'neutral');
                if (in_array($sentiment, ['positive', 'very_positive'])) {
                    $name = $cat['main_category'] ?? 'General';
                    $praiseCounts[$name] = ($praiseCounts[$name] ?? 0) + 1;
                }
            }
        }

        arsort($praiseCounts);

        $result = [];
        foreach ($praiseCounts as $key => $count) {
            $result[] = [
                'strength' => $key,
                'count' => $count,
                'percentage' => $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0
            ];
        }

        return $result;
    }

    public function identifyPerformanceLevel(float $avgRating, float $avgSentiment, float $negativePercentage): string
    {
        $levels = \config('ai.performance.performance_levels', []);

        foreach ($levels as $level) {
            if ($avgRating >= ($level['min'] ?? 0) && $avgRating <= ($level['max'] ?? 5)) {
                return $level['label'] ?? 'Average - Room for Improvement';
            }
        }

        return 'Average - Room for Improvement';
    }

    public function generateTopWorstSummary($topStaff, $worstStaff, $allStaff): array
    {
        $topRating = !empty($topStaff) ? $topStaff[0]['avg_rating'] : 0;
        $worstRating = !empty($worstStaff) ? $worstStaff[0]['avg_rating'] : 0;
        $gap = round($topRating - $worstRating, 1);

        $strengths = [];
        foreach ($topStaff as $staff) {
            foreach ($staff['common_praise'] ?? [] as $praise) {
                $name = $praise['strength'] ?? 'General';
                $strengths[$name] = ($strengths[$name] ?? 0) + 1;
            }
        }
        arsort($strengths);

        $issues = [];
        foreach ($worstStaff as $staff) {
            foreach ($staff['skill_gaps'] ?? [] as $gap_item) {
                $issues[$gap_item] = ($issues[$gap_item] ?? 0) + 1;
            }
        }
        arsort($issues);

        return [
            'overall_status' => $gap > 1 ? 'Significant performance variations detected' : 'Consistent performance levels across staff',
            'rating_gap' => $gap,
            'top_performers_key_strengths' => array_keys(array_slice($strengths, 0, 3)),
            'worst_performers_key_issues' => array_keys(array_slice($issues, 0, 3))
        ];
    }

    public function getPerformanceCategories(): array
    {
        return \config('ai.performance.performance_categories', []);
    }

    public function extractTopicsV2($reviews, $limit = 5): array
    {
        if (empty($reviews) || count($reviews) === 0) {
            return [
                'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
                'all_topics' => [],
                'sources' => ['ai_analysis' => 0]
            ];
        }

        $topicCounts = [];
        $totalMentions = 0;

        foreach ($reviews as $review) {
            foreach ($review->topics ?? [] as $cat) {
                $name = $cat['main_category'] ?? 'General';
                if (!isset($topicCounts[$name])) {
                    $topicCounts[$name] = ['name' => $name, 'count' => 0, 'sentiment' => []];
                }
                $topicCounts[$name]['count']++;
                $topicCounts[$name]['sentiment'][] = $cat['sentiment'] ?? 'neutral';
                $totalMentions++;
            }
        }

        // Process and sort
        $processedTopics = [];
        foreach ($topicCounts as $name => $data) {
            $sentimentCounts = array_count_values($data['sentiment']);
            arsort($sentimentCounts);

            $processedTopics[] = [
                'name' => $name,
                'count' => $data['count'],
                'percentage' => $totalMentions > 0 ? round(($data['count'] / $totalMentions) * 100) : 0,
                'sentiment' => key($sentimentCounts) ?: 'neutral'
            ];
        }

        usort($processedTopics, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $topTopic = $processedTopics[0] ?? ['name' => 'General', 'count' => 0, 'percentage' => 0];

        return [
            'top_topic' => $topTopic,
            'all_topics' => array_slice($processedTopics, 0, $limit),
            'sources' => ['ai_analysis' => count($processedTopics)]
        ];
    }

    public function getMinimumReviewsForStaffAnalysis(): int
    {
        return (int) \config('ai.sentiment.thresholds.min_reviews_staff_analysis', 3);
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
}
