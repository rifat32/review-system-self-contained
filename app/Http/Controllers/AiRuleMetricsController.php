<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, AiRuleTrigger};
use App\Services\Rule\RuleMetricsService;
use App\Services\Rule\RuleReportService;
use App\Services\Rule\ConditionBuilderService;
use Illuminate\Http\Request;

/**
 * Extension to AiRuleController for metrics and validation
 */
class AiRuleMetricsController extends Controller
{
    protected RuleMetricsService $metricsService;
    protected RuleReportService $reportService;

    public function __construct(RuleMetricsService $metricsService, RuleReportService $reportService)
    {
        $this->metricsService = $metricsService;
        $this->reportService = $reportService;
    }

    // ==================== DASHBOARD & REPORTING ====================

  

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

        $rule = AiRule::where(function ($query) use ($ruleId) {
            $query->where('rule_id', $ruleId)
                ->orWhere('id', $ruleId);
        })
            ->forBusiness($businessId)
            ->firstOrFail();

        $report = $this->metricsService->getPerformanceReport($rule->rule_id, [
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

        $rule = AiRule::where(function ($query) use ($ruleId) {
            $query->where('rule_id', $ruleId)
                ->orWhere('id', $ruleId);
        })
            ->forBusiness($businessId)
            ->firstOrFail();

        $query = AiRuleTrigger::where('rule_id', $rule->rule_id)
            ->with(['review:id,comment,created_at', 'verifier:id,first_name,last_name'])
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
}
