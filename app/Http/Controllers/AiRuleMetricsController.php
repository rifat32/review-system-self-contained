<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, AiRuleTrigger};
use App\Services\RuleMetricsService;
use App\Helpers\ConditionBuilderHelper;
use Illuminate\Http\Request;

/**
 * Extension to AiRuleController for metrics and validation
 */
class AiRuleMetricsController extends Controller
{
    protected RuleMetricsService $metricsService;

    public function __construct(RuleMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    // ==================== METRICS & PERFORMANCE ====================

    /**
     * Get rule metrics and performance
     * GET /api/v1.0/ai-rules/{ruleId}/metrics
     *
     * @OA\Get(
     *     path="/api/v1.0/ai-rules/{ruleId}/metrics",
     *     tags={"AI Rules - Metrics"},
     *     summary="Get rule performance metrics",
     *     @OA\Response(response=200, description="Metrics retrieved")
     * )
     */
    public function getRuleMetrics(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->forBusiness($businessId)
            ->firstOrFail();

        $report = $this->metricsService->getPerformanceReport($ruleId, [
            'limit' => 20,
            'trend_days' => 30
        ]);

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Get trigger history for a rule
     * GET /api/v1.0/ai-rules/{ruleId}/trigger-history
     */
    public function getTriggerHistory(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->forBusiness($businessId)
            ->firstOrFail();

        $query = AiRuleTrigger::where('rule_id', $ruleId)
            ->with(['review:id,rating,comment,created_at', 'verifier:id,first_name,last_name'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('outcome')) {
            $query->where('outcome', $request->outcome);
        }

        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $triggers = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $triggers
        ]);
    }

    /**
     * Verify trigger outcome
     * POST /api/v1.0/ai-rules/triggers/{triggerId}/verify
     */
    public function verifyTrigger(Request $request, $triggerId)
    {
        $validated = $request->validate([
            'outcome' => 'required|in:true_positive,false_positive',
            'notes' => 'nullable|string|max:500'
        ]);

        $trigger = $this->metricsService->verifyTriggerOutcome(
            $triggerId,
            $validated['outcome'],
            $request->user()->id,
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Trigger outcome verified successfully',
            'data' => $trigger
        ]);
    }

    /**
     * Validate condition structure
     * POST /api/v1.0/ai-rules/validate-conditions
     */
    public function validateConditions(Request $request)
    {
        $validated = $request->validate([
            'conditions' => 'required|array'
        ]);

        $errors = ConditionBuilderHelper::validateConditionTree($validated['conditions']);

        if (empty($errors)) {
            return response()->json([
                'success' => true,
                'valid' => true,
                'message' => 'Conditions are valid'
            ]);
        }

        return response()->json([
            'success' => false,
            'valid' => false,
            'errors' => $errors
        ], 422);
    }

    /**
     * Get supported condition types
     * GET /api/v1.0/ai-rules/condition-types
     */
    public function getConditionTypes(Request $request)
    {
        $types = ConditionBuilderHelper::getSupportedTypes();

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get top performing rules
     * GET /api/v1.0/ai-rules/top-performers
     */
    public function getTopPerformers(Request $request)
    {
        $businessId = $request->user()->business_id;
        $limit = $request->input('limit', 5);

        $topRules = $this->metricsService->getTopPerformingRules($businessId, $limit);

        return response()->json([
            'success' => true,
            'data' => $topRules
        ]);
    }

    /**
     * Get rules needing attention
     * GET /api/v1.0/ai-rules/needs-attention
     */
    public function getRulesNeedingAttention(Request $request)
    {
        $businessId = $request->user()->business_id;
        $threshold = $request->input('precision_threshold', 70.0);

        $rules = $this->metricsService->getRulesNeedingAttention($businessId, $threshold);

        return response()->json([
            'success' => true,
            'data' => $rules,
            'count' => count($rules)
        ]);
    }
}
