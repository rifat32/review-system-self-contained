<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, AiRuleTrigger};
use App\Services\Rule\RuleMetricsService;
use App\Services\Rule\ConditionBuilderService;
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
     * GET /v1.0/ai-rules/{ruleId}/metrics
     *
     * @OA\Get(
     *     path="/v1.0/ai-rules/{ruleId}/metrics",
     *     tags={"AI Rules - Metrics"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Get rule performance metrics",
     *     description="Returns high-level performance metrics including precision rate, trigger counts, and trend data for a specific rule.",
     *     @OA\Parameter(
     *         name="ruleId",
     *         in="path",
     *         required=true,
     *         description="The external rule_id (e.g. SENTIMENT_ANALYSIS.1)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Metrics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="rule_id", type="string"),
     *                 @OA\Property(property="precision_rate", type="number", format="float"),
     *                 @OA\Property(property="total_triggers", type="integer"),
     *                 @OA\Property(property="true_positives", type="integer"),
     *                 @OA\Property(property="false_positives", type="integer"),
     *                 @OA\Property(property="pending_verification", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rule not found or unauthorized")
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
     * GET /v1.0/ai-rules/{ruleId}/trigger-history
     *
     * @OA\Get(
     *     path="/v1.0/ai-rules/{ruleId}/trigger-history",
     *     tags={"AI Rules - Metrics"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Get trigger history for a rule",
     *     description="Provides a paginated list of all times this rule was triggered.",
     * 
     *     @OA\Parameter(
     *         name="ruleId",
     *         in="path",
     *         required=true,
     *         description="External rule ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="outcome",
     *         in="query",
     *         required=false,
     *         description="Filter by outcome (true_positive, false_positive, pending)",
     *         @OA\Schema(type="string", enum={"true_positive", "false_positive", "pending"})
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         required=false,
     *         description="Filter from date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         required=false,
     *         description="Filter to date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trigger history retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", description="Paginated result")
     *         )
     *     )
     * )
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
     * POST /v1.0/ai-rules/triggers/{triggerId}/verify
     *
     * @OA\Post(
     *     path="/v1.0/ai-rules/triggers/{triggerId}/verify",
     *     tags={"AI Rules - Metrics"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Verify trigger outcome",
     *     description="Allows a manager to mark a trigger as True Positive or False Positive to refine AI accuracy.",
     *     @OA\Parameter(
     *         name="triggerId",
     *         in="path",
     *         required=true,
     *         description="Numerical ID of the trigger record",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"outcome"},
     *             @OA\Property(property="outcome", type="string", enum={"true_positive", "false_positive"}),
     *             @OA\Property(property="notes", type="string", maxLength=500, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Trigger verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
     * POST /v1.0/ai-rules/validate-conditions
     *
     * @OA\Post(
     *     path="/v1.0/ai-rules/validate-conditions",
     *     tags={"AI Rules - Validation"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Validate condition structure",
     *     description="Validates a JSON condition tree before saving a rule.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"conditions"},
     *             @OA\Property(property="conditions", type="object", description="The condition tree to validate")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conditions are valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="valid", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failures",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function validateConditions(Request $request)
    {
        $validated = $request->validate([
            'conditions' => 'required|array'
        ]);

        $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);

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
     * GET /v1.0/ai-rules/condition-types
     *
     * @OA\Get(
     *     path="/v1.0/ai-rules/condition-types",
     *     tags={"AI Rules - Validation"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Get supported condition types",
     *     description="Returns all available condition sources, types, and operators for the rule builder UI.",
     *     @OA\Response(
     *         response=200,
     *         description="Metadata retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getConditionTypes(Request $request)
    {
        $types = ConditionBuilderService::getSupportedTypes();

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get top performing rules
     * GET /v1.0/ai-rules/top-performers
     *
     * @OA\Get(
     *     path="/v1.0/ai-rules/top-performers",
     *     tags={"AI Rules - Metrics"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Get top performing rules",
     *     description="Returns rules with the highest precision and trigger counts for the current business.",
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top rules retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AiRule"))
     *         )
     *     )
     * )
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
     * GET /v1.0/ai-rules/needs-attention
     *
     * @OA\Get(
     *     path="/v1.0/ai-rules/needs-attention",
     *     tags={"AI Rules - Metrics"},
     *  *     security={{"bearerAuth":{}}},
     *     summary="Get rules needing attention",
     *     description="Returns rules that fall below a certain precision threshold.",
     *     @OA\Parameter(
     *         name="precision_threshold",
     *         in="query",
     *         required=false,
     *         description="Percentage threshold (0-100)",
     *         @OA\Schema(type="number", format="float", default=70.0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rules requiring review retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AiRule")),
     *             @OA\Property(property="count", type="integer")
     *         )
     *     )
     * )
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
