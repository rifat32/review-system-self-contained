<?php

namespace App\Services\Rule;

use App\Models\{AiRule, AiRuleTrigger, AiRuleMetric, ReviewNew};
use Illuminate\Support\Facades\Log;

class RuleMetricsService
{
    // ==================== TRIGGER RECORDING ====================

    /**
     * Record rule trigger execution
     * 
     * @param AiRule $rule Rule that was triggered
     * @param ReviewNew $review Review that triggered the rule
     * @param array $matchedConditions Conditions that matched
     * @param array $actionsTriggered Actions that were executed
     * @param float $confidenceScore Confidence score (0-100)
     * @return AiRuleTrigger Created trigger record
     */
    public function recordTrigger(
        AiRule $rule,
        ReviewNew $review,
        array $matchedConditions,
        array $actionsTriggered,
        float $confidenceScore
    ): AiRuleTrigger {
        // Create trigger record
        $trigger = AiRuleTrigger::create([
            'rule_id' => $rule->rule_id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'confidence_score' => $confidenceScore,
            'matched_conditions' => $matchedConditions,
            'actions_triggered' => $actionsTriggered,
            'outcome' => 'pending'
        ]);

        // Update metrics
        $this->updateMetrics($rule->rule_id, $actionsTriggered);

        Log::info("Rule trigger recorded", [
            'rule_id' => $rule->rule_id,
            'review_id' => $review->id,
            'confidence' => $confidenceScore
        ]);

        return $trigger;
    }

    // ==================== METRICS UPDATE ====================

    /**
     * Update aggregated metrics for a rule
     * 
     * @param string $ruleId Rule ID
     * @param array $actionsTriggered Actions that were executed
     * @return AiRuleMetric Updated metrics
     */
    public function updateMetrics(string $ruleId, array $actionsTriggered): AiRuleMetric
    {
        $metrics = AiRuleMetric::firstOrCreate(['rule_id' => $ruleId]);

        // Increment lifetime triggers
        $metrics->incrementTriggers();

        // Record individual actions
        $actionList = array_is_list($actionsTriggered) ? $actionsTriggered : array_keys(array_filter((array) $actionsTriggered));

        foreach ($actionList as $action) {
            if (is_string($action)) {
                $metrics->recordAction($action);
            }
        }

        return $metrics;
    }

    // ==================== VERIFICATION ====================

    /**
     * Verify trigger outcome
     * 
     * @param int $triggerId Trigger ID
     * @param string $outcome Outcome (true_positive or false_positive)
     * @param int $userId User who verified
     * @param string|null $notes Verification notes
     * @return AiRuleTrigger Updated trigger
     */
    public function verifyTriggerOutcome(
        int $triggerId,
        string $outcome,
        int $userId,
        ?string $notes = null
    ): AiRuleTrigger {
        $trigger = AiRuleTrigger::findOrFail($triggerId);

        // Update trigger
        if ($outcome === 'true_positive') {
            $trigger->verifyAsTruePositive($userId, $notes);
        } else {
            $trigger->verifyAsFalsePositive($userId, $notes);
        }

        // Update metrics
        $metrics = AiRuleMetric::where('rule_id', $trigger->rule_id)->first();
        if ($metrics) {
            $metrics->recordVerification($outcome);
        }

        Log::info("Trigger verified", [
            'trigger_id' => $triggerId,
            'outcome' => $outcome,
            'verified_by' => $userId
        ]);

        return $trigger;
    }

    // ==================== PERFORMANCE CALCULATION ====================

    /**
     * Calculate precision rate for a rule
     * 
     * @param string $ruleId Rule ID
     * @return float|null Precision rate percentage (0-100) or null if insufficient data
     */
    public function calculatePrecisionRate(string $ruleId): ?float
    {
        $metrics = AiRuleMetric::where('rule_id', $ruleId)->first();

        if (!$metrics) {
            return null;
        }

        $total = $metrics->true_positives + $metrics->false_positives;

        if ($total < (config('ai.insights.opportunities.performance.min_total_for_precision') ?? 5)) {
            // Insufficient data for reliable precision rate
            return null;
        }

        return ($metrics->true_positives / $total) * 100;
    }

    // ==================== PERFORMANCE REPORT ====================

    /**
     * Get performance report for a rule
     * 
     * @param string $ruleId Rule ID
     * @param array $options Report options
     * @return array Performance report
     */
    public function getPerformanceReport(string $ruleId, array $options = []): array
    {
        $rule = AiRule::where('rule_id', $ruleId)->first();
        $metrics = AiRuleMetric::where('rule_id', $ruleId)->first();

        if (!$rule || !$metrics) {
            return [
                'error' => 'Rule or metrics not found'
            ];
        }

        // Get recent triggers
        $recentTriggers = AiRuleTrigger::where('rule_id', $ruleId)
            ->orderBy('created_at', 'desc')
            ->limit($options['limit'] ?? (config('ai.insights.opportunities.performance.report_limit') ?? 20))
            ->with(['review:id,comment,created_at'])
            ->get();

        // Get trigger trends
        $trendDays = $options['trend_days'] ?? (config('ai.insights.opportunities.performance.trend_days') ?? 30);
        $triggerTrends = AiRuleTrigger::where('rule_id', $ruleId)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($trendDays))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Outcome distribution
        $outcomeDistribution = AiRuleTrigger::where('rule_id', $ruleId)
            ->selectRaw('outcome, COUNT(*) as count')
            ->groupBy('outcome')
            ->get()
            ->pluck('count', 'outcome')
            ->toArray();

        return [
            'rule' => [
                'id' => $rule->rule_id,
                'name' => $rule->rule_name,
                'category' => $rule->category,
                'priority' => $rule->priority,
                'enabled' => $rule->enabled
            ],
            'metrics' => $metrics->getSummary(),
            'recent_triggers' => $recentTriggers,
            'trigger_trends' => $triggerTrends,
            'outcome_distribution' => $outcomeDistribution,
            'performance_indicators' => [
                'needs_attention' => $metrics->precision_rate < (config('ai.insights.opportunities.performance.precision_threshold') ?? 70) && $metrics->lifetime_triggers > (config('ai.insights.opportunities.performance.min_triggers_for_metrics') ?? 10),
                'performing_well' => $metrics->precision_rate >= (config('ai.insights.opportunities.performance.well_performing_threshold') ?? 85),
                'insufficient_data' => $metrics->lifetime_triggers < (config('ai.insights.opportunities.performance.min_triggers_for_metrics') ?? 10)
            ]
        ];
    }

    // ==================== BULK OPERATIONS ====================

    /**
     * Get metrics for multiple rules
     * 
     * @param array $ruleIds Array of rule IDs
     * @return array Metrics for all rules
     */
    public function getBulkMetrics(array $ruleIds): array
    {
        $metrics = AiRuleMetric::whereIn('rule_id', $ruleIds)->get();

        return $metrics->map(function ($metric) {
            return [
                'rule_id' => $metric->rule_id,
                'lifetime_triggers' => $metric->lifetime_triggers,
                'precision_rate' => $metric->getFormattedPrecisionRate(),
                'performance_grade' => $metric->getPerformanceGrade(),
                'total_impact' => $metric->getTotalImpact(),
                'last_triggered' => $metric->last_triggered_at?->diffForHumans()
            ];
        })->toArray();
    }

    // ==================== ANALYTICS ====================

    /**
     * Get top performing rules
     * 
     * @param int $businessId Business ID
     * @param int $limit Number of rules to return
     * @return array Top performing rules
     */
    public function getTopPerformingRules(int $businessId, ?int $limit = null): array
    {
        $limit = $limit ?? (config('ai.insights.opportunities.top_count') ?? 5);
        $rules = AiRule::where('business_id', $businessId)
            ->orWhere('scope', 'system')
            ->with('metrics')
            ->get();

        return $rules->filter(function ($rule) {
            return $rule->metrics && $rule->metrics->precision_rate !== null;
        })
            ->sortByDesc(function ($rule) {
                return $rule->metrics->precision_rate;
            })
            ->take($limit)
            ->map(function ($rule) {
                return [
                    'rule_id' => $rule->rule_id,
                    'rule_name' => $rule->rule_name,
                    'precision_rate' => $rule->metrics->getFormattedPrecisionRate(),
                    'lifetime_triggers' => $rule->metrics->lifetime_triggers,
                    'total_impact' => $rule->metrics->getTotalImpact()
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get rules needing attention
     * 
     * @param int $businessId Business ID
     * @param float $precisionThreshold Minimum precision rate threshold
     * @return array Rules with low precision rates
     */
    public function getRulesNeedingAttention(int $businessId, ?float $precisionThreshold = null): array
    {
        $precisionThreshold = $precisionThreshold ?? (config('ai.insights.opportunities.performance.precision_threshold') ?? 70.0);
        $rules = AiRule::where('business_id', $businessId)
            ->where('enabled', true)
            ->with('metrics')
            ->get();

        return $rules->filter(function ($rule) use ($precisionThreshold) {
            $minTriggers = config('ai.insights.opportunities.performance.min_triggers_for_metrics') ?? 10;
            return $rule->metrics
                && $rule->metrics->precision_rate !== null
                && $rule->metrics->precision_rate < $precisionThreshold
                && $rule->metrics->lifetime_triggers >= $minTriggers;
        })
            ->map(function ($rule) {
                return [
                    'rule_id' => $rule->rule_id,
                    'rule_name' => $rule->rule_name,
                    'precision_rate' => $rule->metrics->getFormattedPrecisionRate(),
                    'lifetime_triggers' => $rule->metrics->lifetime_triggers,
                    'false_positives' => $rule->metrics->false_positives,
                    'recommended_action' => 'Review rule conditions or disable'
                ];
            })
            ->values()
            ->toArray();
    }
}
