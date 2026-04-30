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
}
