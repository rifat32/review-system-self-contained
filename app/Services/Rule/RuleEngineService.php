<?php

namespace App\Services\Rule;

use App\Models\{AiRule, ReviewNew, Business, InsightRecord, AiRuleEvaluation, Alert};
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
        return Cache::remember('rec_template_' . $templateId, 3600, function () use ($templateId) {
            $template = AiRule::where('category', 'recommendation_templates')
                ->where('key_name', $templateId)
                ->value('value');

            if ($template) {
                return $template;
            }

            // Fallback to GENERAL if specific template not found
            return AiRule::where('category', 'recommendation_templates')
                ->where('key_name', 'GENERAL')
                ->value('value') ?? 'Improve {{main_category}} based on {{count}} customer mentions.';
        });
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
        if (empty($actions)) return;

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
        return (float) AiRule::where('key_name', 'positive_sentiment_threshold')
            ->value('value') ?? 0.7;
    }

    public static function getNegativeSentimentThreshold(): float
    {
        return (float) AiRule::where('key_name', 'negative_sentiment_threshold')
            ->value('value') ?? 0.4;
    }

    public function getNeutralLowerThreshold(): float
    {
        return (float) AiRule::where('key_name', 'neutral_lower_threshold')
            ->value('value') ?? 2.1;
    }

    public function getNeutralUpperThreshold(): float
    {
        return (float) AiRule::where('key_name', 'neutral_upper_threshold')
            ->value('value') ?? 3.9;
    }

    public function getCsatThreshold(): float
    {
        return (float) AiRule::where('key_name', 'csat_threshold')
            ->value('value') ?? 4.0;
    }

    public static function getDefaultSentimentLabel(): string
    {
        return AiRule::where('key_name', 'default_sentiment_label')
            ->value('value') ?? 'neutral';
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
        return AiRule::where('key_name', 'default_training_recommendation')
            ->value('value') ?? 'General Training';
    }

    public function mapSuggestionsToSkillGaps($suggestions): array
    {
        $skillGaps = [];
        $skillMap = AiRule::where('category', 'skill_mapping')
            ->pluck('value', 'key_name')
            ->toArray();

        foreach ($suggestions as $suggestion) {
            $clean = strtolower(trim($suggestion));

            foreach ($skillMap as $pattern => $skill) {
                if (preg_match($pattern, $clean)) {
                    $skillGaps[] = $skill;
                    break;
                }
            }
        }

        return array_unique($skillGaps);
    }

    public function getOpportunityKeywords(): array
    {
        return AiRule::where('category', 'opportunity_keywords')
            ->pluck('value')
            ->toArray();
    }

    public function generateRatingPrediction(float $avgRating): array
    {
        $predictionRules = AiRule::where('category', 'rating_prediction')
            ->get();

        foreach ($predictionRules as $rule) {
            $conditions = $rule->conditions;
            if ($avgRating >= $conditions['min'] && $avgRating <= $conditions['max']) {
                return [
                    'prediction' => $rule->value,
                    'estimated_impact' => $conditions['impact'] ?? '+0.05 points',
                    'potential_rating' => min(5, $avgRating + ($conditions['increase'] ?? 0.05))
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
        return (int) AiRule::where('key_name', 'min_praise_recommendation')
            ->value('value') ?? 2;
    }

    public function getMinimumMentionsForIssue(): int
    {
        return (int) AiRule::where('key_name', 'min_mentions_issue')
            ->value('value') ?? 2;
    }

    public function getHighPriorityThreshold(): int
    {
        return (int) AiRule::where('key_name', 'high_priority_threshold')
            ->value('value') ?? 4;
    }

    public static function getIssuePatterns(): array
    {
        $patterns = AiRule::where('category', 'issue_patterns')
            ->get();

        $result = [];
        foreach ($patterns as $pattern) {
            $data = json_decode($pattern->value, true);
            $result[$pattern->key_name] = $data;
        }

        return $result;
    }

    public function detectStaffPraise($reviews): array
    {
        $praiseRules = AiRule::where('category', 'staff_praise_detection')
            ->get();

        $praiseCount = 0;
        foreach ($reviews as $review) {
            if (empty($review->comment))
                continue;

            $text = strtolower(trim($review->comment));
            foreach ($praiseRules as $rule) {
                $keywords = json_decode($rule->value, true);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        $praiseCount++;
                        break 2;
                    }
                }
            }
        }

        return [
            'count' => $praiseCount,
            'title' => 'Staff Excellence',
            'description' => 'Customers appreciate your staff\'s service and professionalism.'
        ];
    }

    public function getCommonTopicKeywords(): array
    {
        return AiRule::where('category', 'common_topics')
            ->pluck('value')
            ->toArray();
    }

    public function generateBranchComparisonInsights($branchesData): array
    {
        $insightTemplates = AiRule::where('category', 'branch_comparison_templates')
            ->get();

        if (empty($branchesData)) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        $template = $insightTemplates->first();
        $data = json_decode($template->value, true);

        return [
            'overview' => $data['overview'] ?? 'Branch comparison insights generated.',
            'key_findings' => $data['findings'] ?? []
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
        $sentimentRules = AiRule::where('category', 'sentiment_rules')
            ->get();

        foreach ($sentimentRules as $rule) {
            $conditions = $rule->conditions;
            if ($percentage >= $conditions['min'] && $percentage <= $conditions['max']) {
                return $rule->value;
            }
        }

        return 'Neutral';
    }

    public static function getSentimentDescription(float $percentage): string
    {
        $descriptions = AiRule::where('category', 'sentiment_descriptions')
            ->get();

        foreach ($descriptions as $desc) {
            $conditions = json_decode($desc->conditions, true);
            if ($percentage >= $conditions['min'] && $percentage <= $conditions['max']) {
                return $desc->value;
            }
        }

        return 'mixed';
    }

    public static function getTrendThreshold(): float
    {
        return (float) AiRule::where('key_name', 'trend_threshold')
            ->value('value') ?? 0.1;
    }

    public static function getImprovingTrendMessage(): string
    {
        return AiRule::where('key_name', 'improving_trend_message')
            ->value('value') ?? 'Improving sentiment trend';
    }

    public static function  getDecliningTrendMessage(): string
    {
        return AiRule::where('key_name', 'declining_trend_message')
            ->value('value') ?? 'Declining sentiment trend';
    }

    public static function getFrequentIssueThreshold(): int
    {
        return (int) AiRule::where('key_name', 'frequent_issue_threshold')
            ->value('value') ?? 5;
    }

    public function getMinimumReviewsForStaffEvaluation(): int
    {
        return (int) AiRule::where('key_name', 'min_reviews_staff_eval')
            ->value('value') ?? 3;
    }

    public function getInsufficientDataMessage(): string
    {
        return AiRule::where('key_name', 'insufficient_data_message')
            ->value('value') ?? 'Insufficient Data';
    }

    public function getStaffEvaluationFromRating(float $rating): string
    {
        $evaluations = AiRule::where('category', 'staff_evaluations')
            ->get();

        foreach ($evaluations as $eval) {
            $conditions = json_decode($eval->conditions, true);
            if ($rating >= $conditions['min'] && $rating <= $conditions['max']) {
                return $eval->value;
            }
        }

        return 'Consistent';
    }

    public function getPerformanceLabelFromRating(float $rating): string
    {
        $labels = Cache::remember('performance_labels', 3600, function () {
            return AiRule::where('category', 'performance_labels')->get();
        });

        foreach ($labels as $label) {
            $conditions = json_decode($label->conditions, true);
            if ($rating >= ($conditions['min'] ?? 0) && $rating <= ($conditions['max'] ?? 5)) {
                return $label->value;
            }
        }

        return 'Average';
    }

    public function generateActionForIssue(string $issue, int $evidenceCount): ?array
    {
        $actions = AiRule::where('category', 'action_items')
            ->where('key_name', $issue)
            ->first();

        if (!$actions)
            return null;

        $data = json_decode($actions->value, true);
        return [
            'title' => $data['title'] ?? 'Action Required',
            'description' => $data['description'] ?? 'Please address this issue.',
            'priority' => $evidenceCount >= ($data['high_priority_threshold'] ?? 4) ? 'high' : 'medium'
        ];
    }

    public function getMinimumReviewsForTrendAnalysis(): int
    {
        return (int) AiRule::where('key_name', 'min_reviews_trend')
            ->value('value') ?? 4;
    }

    public function getInsufficientDataForTrendMessage(): string
    {
        return AiRule::where('key_name', 'insufficient_trend_data')
            ->value('value') ?? 'insufficient_data';
    }

    public function getStableTrendMessage(): string
    {
        return AiRule::where('key_name', 'stable_trend_message')
            ->value('value') ?? 'stable';
    }

    public function getChangeType($value): string
    {
        return $value >= 0 ? 'positive' : 'negative';
    }

    public function getCommonStaffTopicKeywords(): array
    {
        return AiRule::where('category', 'staff_topic_keywords')
            ->pluck('value')
            ->toArray();
    }

    public function getMinimumReviewsForTopStaff(): int
    {
        return (int) AiRule::where('key_name', 'min_reviews_top_staff')
            ->value('value') ?? 5;
    }

    public function getSentimentLabelByPercentage(float $percentage): string
    {
        $labels = AiRule::where('category', 'percentage_sentiment_labels')
            ->get();

        foreach ($labels as $label) {
            $conditions = json_decode($label->conditions, true);
            if ($percentage >= $conditions['min'] && $percentage <= $conditions['max']) {
                return $label->value;
            }
        }

        return 'Average';
    }

    public static function getSentimentLabelFromScore(float $score): string
    {
        $labels = AiRule::where('category', 'score_sentiment_labels')
            ->get();

        foreach ($labels as $label) {
            $conditions = json_decode($label->conditions, true);
            if ($score >= $conditions['min'] && $score <= $conditions['max']) {
                return $label->value;
            }
        }

        return 'neutral';
    }

    public function getIssueKeywords(): array
    {
        return AiRule::where('category', 'issue_keywords')
            ->pluck('value')
            ->toArray();
    }

    public function getTrainingRecommendations($reviews): array
    {
        $recommendations = AiRule::where('category', 'training_recommendations')
            ->get();

        $result = [];
        foreach ($recommendations as $rec) {
            $data = json_decode($rec->value, true);
            $result[] = [
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $data['priority'],
                'category' => $data['category']
            ];
        }

        return $result;
    }

    public function analyzeSkillGaps($reviews): array
    {
        return [
            'strengths' => [],
            'improvement_areas' => []
        ];
    }

    public function calculateCustomerTone($reviews): array
    {
        return []; // Implement dynamic tone analysis
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
        $template = AiRule::where('key_name', 'summary_template')
            ->value('value') ?? "Customers are {$positivePercent}% positive and {$negativePercent}% negative.";

        return str_replace(
            ['{{positive}}', '{{negative}}'],
            [$positivePercent, $negativePercent],
            $template
        );
    }

    public function getDefaultSummaryPhrase(): string
    {
        return AiRule::where('key_name', 'default_summary_phrase')
            ->value('value') ?? "Common themes include staff friendliness, service speed, and occasional cleanliness concerns.";
    }

    public function getHighIssueThreshold(): int
    {
        return (int) AiRule::where('key_name', 'high_issue_threshold')
            ->value('value') ?? 3;
    }

    public function getMinimumMentionsForRecommendation(): int
    {
        return (int) AiRule::where('key_name', 'min_mentions_recommendation')
            ->value('value') ?? 2;
    }

    public function getStaffTrainingRecommendations(int $staffId, int $businessId): array
    {
        $trainings = AiRule::where('category', 'staff_training')
            ->get();

        $result = [];
        foreach ($trainings as $training) {
            $data = json_decode($training->value, true);
            $result[] = [
                'title' => $data['title'],
                'type' => $data['type'],
                'priority' => $data['priority']
            ];
        }

        return $result;
    }

    public function extractCommonPraise($reviews): array
    {
        $praisePatterns = AiRule::where('category', 'praise_patterns')
            ->get();

        $praiseCounts = [];
        foreach ($reviews as $review) {
            if (empty($review->comment))
                continue;

            $text = strtolower($review->comment);
            foreach ($praisePatterns as $pattern) {
                $keywords = json_decode($pattern->value, true);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        $praiseCounts[$pattern->key_name] = ($praiseCounts[$pattern->key_name] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        arsort($praiseCounts);

        $result = [];
        foreach ($praiseCounts as $key => $count) {
            $result[] = [
                'strength' => ucwords(str_replace('_', ' ', $key)),
                'count' => $count,
                'percentage' => count($reviews) > 0 ? round(($count / count($reviews)) * 100) : 0
            ];
        }

        return $result;
    }

    public function identifyPerformanceLevel(float $avgRating, float $avgSentiment, float $negativePercentage): string
    {
        $levels = AiRule::where('category', 'performance_levels')
            ->get();

        foreach ($levels as $level) {
            $conditions = json_decode($level->conditions, true);
            if (
                $avgRating >= $conditions['rating_min'] &&
                $avgRating <= $conditions['rating_max'] &&
                $avgSentiment >= $conditions['sentiment_min'] &&
                $negativePercentage <= $conditions['negative_max']
            ) {
                return $level->value;
            }
        }

        return 'Average - Room for Improvement';
    }

    public function generateTopWorstSummary($topStaff, $worstStaff, $allStaff): array
    {
        return [
            'overall_status' => 'Performance analysis completed',
            'rating_gap' => 0,
            'top_performers_key_strengths' => [],
            'worst_performers_key_issues' => []
        ];
    }

    public function getPerformanceCategories(): array
    {
        $categories = AiRule::where('category', 'performance_categories')
            ->get();

        $result = [];
        foreach ($categories as $category) {
            $result[$category->key_name] = json_decode($category->value, true);
        }

        return $result;
    }

    public function extractTopicsV2($reviews, $limit = 5): array
    {
        $topicConfig = AiRule::where('key_name', 'topic_extraction_config')
            ->first();

        if (!$topicConfig) {
            return [
                'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
                'all_topics' => [],
                'sources' => ['ai_topics' => 0, 'keyword_matches' => 0]
            ];
        }

        $config = json_decode($topicConfig->value, true);

        // Implement topic extraction using config
        return [
            'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
            'all_topics' => [],
            'sources' => ['ai_topics' => 0, 'keyword_matches' => 0]
        ];
    }

    public function getMinimumReviewsForStaffAnalysis(): int
    {
        return (int) AiRule::where('key_name', 'min_reviews_staff_analysis')
            ->value('value') ?? 3;
    }

    public function generateDashboardInsights($sentimentData, $topTopics, $totalReviews): array
    {
        $templates = AiRule::where('category', 'dashboard_insight_templates')
            ->get();

        $insights = [
            'summary' => '',
            'key_findings' => [],
            'recommendations' => []
        ];

        if ($totalReviews === 0) {
            $insights['summary'] = 'No reviews available for analysis.';
        } else {
            $template = $templates->first();
            $data = json_decode($template->value, true);
            $insights['summary'] = str_replace(
                ['{{positive}}', '{{avg_rating}}'],
                [$sentimentData['positive_percentage'], $sentimentData['average_score']],
                $data['summary']
            );
        }

        return $insights;
    }
}
