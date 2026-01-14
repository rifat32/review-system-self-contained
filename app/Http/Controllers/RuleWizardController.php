<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, ReviewNew, Branch};
use App\Services\Rule\ConditionBuilderService;
use App\Services\Rule\{RuleExplanationService, RuleMetricsService, RulePreviewService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};

/**
 * @OA\Schema(
 *     schema="AiRule",
 *     type="object",
 *     title="AI Rule",
 *     description="AI Rule model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="rule_id", type="string", example="custom_abc123"),
 *     @OA\Property(property="rule_name", type="string", example="High Priority Negative Sentiment Alert"),
 *     @OA\Property(property="description", type="string", example="Alert managers when reviews have negative sentiment"),
 *     @OA\Property(property="scope", type="string", example="business"),
 *     @OA\Property(property="business_id", type="integer", example=1),
 *     @OA\Property(property="category", type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"}, example="sentiment"),
 *     @OA\Property(property="priority", type="string", enum={"critical", "high", "medium", "low"}, example="high"),
 *     @OA\Property(property="enabled", type="boolean", example=true),
 *     @OA\Property(
 *         property="conditions",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="source", type="string"),
 *             @OA\Property(property="type", type="string"),
 *             @OA\Property(property="operator", type="string"),
 *             @OA\Property(property="value", type="string"),
 *             @OA\Property(property="logic", type="string")
 *         )
 *     ),
 *     @OA\Property(property="actions", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="multi_tag_detection", type="boolean", example=false),
 *     @OA\Property(property="trigger_only_on_first_occurrence", type="boolean", example=false),
 *     @OA\Property(property="run_frequency", type="string", enum={"real_time", "hourly", "daily", "weekly"}, example="daily"),
 *     @OA\Property(property="cooldown_days", type="integer", example=7),
 *     @OA\Property(property="deduplication_scope", type="string", example="staff"),
 *     @OA\Property(property="applies_to", type="string", enum={"new_reviews_only", "all_reviews"}, example="new_reviews_only"),
 *     @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer"), nullable=true),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="version", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-14T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-14T10:00:00Z")
 * )
 */

class RuleWizardController extends Controller
{
    protected RuleMetricsService $metricsService;
    protected RulePreviewService $previewService;

    public function __construct(RuleMetricsService $metricsService, RulePreviewService $previewService)
    {
        $this->metricsService = $metricsService;
        $this->previewService = $previewService;
    }

    // ==================== CREATE RULE ====================

    /**
     * Create a new AI rule
     * 
     * @OA\Post(
     *     path="/api/v1.0/ai-rules",
     *     operationId="createRule",
     *     tags={"AI Rules"},
     *     summary="Create new AI rule",
     *     description="Create a new AI rule with conditions and actions",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_id", "rule_name", "category", "priority", "conditions", "actions", "enabled"},
     *             @OA\Property(property="business_id", type="integer", example=1),
     *             @OA\Property(property="rule_name", type="string", maxLength=255, example="High Priority Negative Sentiment Alert"),
     *             @OA\Property(property="description", type="string", maxLength=1000, example="Alert managers when reviews have negative sentiment"),
     *             @OA\Property(property="category", type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"}, example="sentiment"),
     *             @OA\Property(property="priority", type="string", enum={"critical", "high", "medium", "low"}, example="high"),
     *             @OA\Property(
     *                 property="conditions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="source", type="string", enum={"Comment", "Rating", "Staff", "Area", "Emotion", "Trend"}),
     *                     @OA\Property(property="type", type="string", enum={"sentiment", "rating", "keyword", "staff_mention", "area_mention", "emotion", "service_type", "frequency", "trend_direction"}),
     *                     @OA\Property(property="operator", type="string", enum={"equals", "contains", "greater_than", "less_than", "between", "not_equals", "starts_with", "ends_with", "regex"}),
     *                     @OA\Property(property="value", type="string"),
     *                     @OA\Property(property="logic", type="string", enum={"AND", "OR"})
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="actions",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"flag_review", "notify_manager", "recommend_coaching", "link_staff", "escalate", "notify_slack", "notify_email"})
     *             ),
     *             @OA\Property(property="enabled", type="boolean", example=true),
     *             @OA\Property(property="multi_tag_detection", type="boolean", example=false),
     *             @OA\Property(property="trigger_only_on_first_occurrence", type="boolean", example=false),
     *             @OA\Property(property="run_frequency", type="string", enum={"real_time", "hourly", "daily", "weekly"}, example="daily"),
     *             @OA\Property(property="cooldown_days", type="integer", example=7),
     *             @OA\Property(property="deduplication_scope", type="string", enum={"review", "staff", "category", "branch", "staff_category"}, example="staff"),
     *             @OA\Property(property="applies_to", type="string", enum={"new_reviews_only", "all_reviews"}, example="new_reviews_only"),
     *             @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="sensitivity", type="number", minimum=0, maximum=100, example=70)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rule created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rule created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AiRule")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createRule(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            // Basic Information
            'business_id' => 'required|exists:businesses,id',
            'rule_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'required|in:critical,high,medium,low',

            // Conditions & Actions
            'conditions' => 'required|array|min:1',
            'conditions.*.source' => 'required|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.*.type' => 'required|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,service_type,frequency,trend_direction',
            'conditions.*.operator' => 'required|in:equals,contains,greater_than,less_than,between,not_equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required',
            'conditions.*.logic' => 'nullable|in:AND,OR',
            'conditions.*.analyse_comment_for' => 'nullable|string',
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|in:flag_review,notify_manager,recommend_coaching,link_staff,escalate,notify_slack,notify_email',

            // Configuration
            'enabled' => 'required|boolean',
            'multi_tag_detection' => 'nullable|boolean',
            'trigger_only_on_first_occurrence' => 'nullable|boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id',
            'sensitivity' => 'nullable|numeric|min:0|max:100'
        ]);

        // Verify user has access to business
        if ($user->business_id != $validated['business_id'] && !$user->hasRole('superadmin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to business'
            ], 403);
        }

        // Validate condition structure
        $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid condition structure',
                'errors' => $errors
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create the AI rule
            $rule = AiRule::create([
                'rule_id' => 'custom_' . uniqid(),
                'rule_name' => $validated['rule_name'],
                'description' => $validated['description'] ?? '',
                'scope' => 'business',
                'business_id' => $validated['business_id'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'enabled' => $validated['enabled'],
                'conditions' => $validated['conditions'],
                'actions' => $validated['actions'],
                'multi_tag_detection' => $validated['multi_tag_detection'] ?? false,
                'trigger_only_on_first_occurrence' => $validated['trigger_only_on_first_occurrence'] ?? false,
                'run_frequency' => $validated['run_frequency'] ?? 'daily',
                'cooldown_days' => $validated['cooldown_days'] ?? 7,
                'deduplication_scope' => $validated['deduplication_scope'] ?? 'staff',
                'applies_to' => $validated['applies_to'] ?? 'new_reviews_only',
                'branch_ids' => $validated['branch_ids'] ?? null,
                'created_by' => $user->id,
                'version' => 1
            ]);

            // Generate AI explanations
            if (class_exists(RuleExplanationService::class)) {
                try {
                    app(RuleExplanationService::class)->generateExplanations($rule);
                } catch (\Exception $e) {
                    Log::warning("Failed to generate rule explanations", [
                        'rule_id' => $rule->rule_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Initialize metrics record
            $this->metricsService->updateMetrics($rule->rule_id, []);

            DB::commit();

            Log::info("Rule created", [
                'rule_id' => $rule->rule_id,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rule created successfully',
                'data' => $rule
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to create rule", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== GET ALL RULES ====================

    /**
     * Get all AI rules
     * 
     * @OA\Get(
     *     path="/api/v1.0/ai-rules",
     *     operationId="getAllRules",
     *     tags={"AI Rules"},
     *     summary="Get all AI rules",
     *     description="Retrieve all AI rules for the authenticated user's business",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"})
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority",
     *         @OA\Schema(type="string", enum={"critical", "high", "medium", "low"})
     *     ),
     *     @OA\Parameter(
     *         name="enabled",
     *         in="query",
     *         description="Filter by enabled status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="scope",
     *         in="query",
     *         description="Filter by scope",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rules retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AiRule")),
     *             @OA\Property(property="count", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getAllRules(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        $query = AiRule::where('business_id', $businessId);

        // Filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->has('enabled')) {
            $query->where('enabled', $request->boolean('enabled'));
        }
        if ($request->has('scope')) {
            $query->where('scope', $request->scope);
        }

        $rules = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
            'count' => $rules->count()
        ]);
    }

    // ==================== GET RULE BY ID ====================

    /**
     * Get single AI rule by ID
     * 
     * @OA\Get(
     *     path="/api/v1.0/ai-rules/{id}",
     *     operationId="getRuleById",
     *     tags={"AI Rules"},
     *     summary="Get AI rule by ID",
     *     description="Retrieve a single AI rule by its ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rule retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/AiRule")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rule not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getRuleById(Request $request, $id)
    {
        $user = $request->user();

        $rule = AiRule::where('id', $id)
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $rule
        ]);
    }

    // ==================== UPDATE RULE ====================

    /**
     * Update AI rule
     * 
     * @OA\Put(
     *     path="/api/v1.0/ai-rules/{id}",
     *     operationId="updateRule",
     *     tags={"AI Rules"},
     *     summary="Update AI rule",
     *     description="Update an existing AI rule",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="rule_name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=1000),
     *             @OA\Property(property="category", type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"}),
     *             @OA\Property(property="priority", type="string", enum={"critical", "high", "medium", "low"}),
     *             @OA\Property(property="conditions", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="actions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="enabled", type="boolean"),
     *             @OA\Property(property="multi_tag_detection", type="boolean"),
     *             @OA\Property(property="trigger_only_on_first_occurrence", type="boolean"),
     *             @OA\Property(property="run_frequency", type="string", enum={"real_time", "hourly", "daily", "weekly"}),
     *             @OA\Property(property="cooldown_days", type="integer"),
     *             @OA\Property(property="deduplication_scope", type="string"),
     *             @OA\Property(property="applies_to", type="string", enum={"new_reviews_only", "all_reviews"}),
     *             @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rule updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rule updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/AiRule")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rule not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateRule(Request $request, $id)
    {
        $user = $request->user();

        $rule = AiRule::where('id', $id)
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        $validated = $request->validate([
            'rule_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'sometimes|required|in:critical,high,medium,low',
            'conditions' => 'sometimes|required|array|min:1',
            'conditions.*.source' => 'required_with:conditions|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.*.type' => 'required_with:conditions|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,service_type,frequency,trend_direction',
            'conditions.*.operator' => 'required_with:conditions|in:equals,contains,greater_than,less_than,between,not_equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required_with:conditions',
            'conditions.*.logic' => 'nullable|in:AND,OR',
            'actions' => 'sometimes|required|array|min:1',
            'actions.*' => 'required_with:actions|in:flag_review,notify_manager,recommend_coaching,link_staff,escalate,notify_slack,notify_email',
            'enabled' => 'sometimes|boolean',
            'multi_tag_detection' => 'nullable|boolean',
            'trigger_only_on_first_occurrence' => 'nullable|boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews',
            'branch_ids' => 'nullable|array',
            'branch_ids.*' => 'exists:branches,id'
        ]);

        // Validate conditions if provided
        if (isset($validated['conditions'])) {
            $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid condition structure',
                    'errors' => $errors
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $rule->update($validated);
            $rule->version = $rule->version + 1;
            $rule->save();

            // Regenerate explanations if conditions changed
            if (isset($validated['conditions']) && class_exists(RuleExplanationService::class)) {
                try {
                    app(RuleExplanationService::class)->generateExplanations($rule);
                } catch (\Exception $e) {
                    Log::warning("Failed to regenerate rule explanations", [
                        'rule_id' => $rule->rule_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            Log::info("Rule updated", [
                'rule_id' => $rule->rule_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rule updated successfully',
                'data' => $rule->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to update rule", [
                'rule_id' => $rule->rule_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== DELETE RULE ====================

    /**
     * Delete AI rule
     * 
     * @OA\Delete(
     *     path="/api/v1.0/ai-rules/{id}",
     *     operationId="deleteRule",
     *     tags={"AI Rules"},
     *     summary="Delete AI rule",
     *     description="Delete an AI rule by ID",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Rule ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rule deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rule deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Rule not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function deleteRule(Request $request, $id)
    {
        $user = $request->user();

        $rule = AiRule::where('id', $id)
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $ruleId = $rule->rule_id;
            $ruleName = $rule->rule_name;

            $rule->delete();

            DB::commit();

            Log::info("Rule deleted", [
                'rule_id' => $ruleId,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "Rule '$ruleName' deleted successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to delete rule", [
                'rule_id' => $rule->rule_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete rule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== PREVIEW RULE ====================

    /**
     * Preview rule with "what-if" analysis
     * 
     * @OA\Post(
     *     path="/api/v1.0/ai-rules/preview",
     *     operationId="previewRule",
     *     tags={"AI Rules"},
     *     summary="Preview AI rule",
     *     description="Preview how a rule would perform with what-if analysis",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_id", "conditions", "actions", "category"},
     *             @OA\Property(property="business_id", type="integer", example=1),
     *             @OA\Property(property="conditions", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="actions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="category", type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preview generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="preview", type="object"),
     *             @OA\Property(property="message", type="string", example="Preview generated successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function previewRule(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'conditions' => 'required|array|min:1',
            'actions' => 'required|array|min:1',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality'
        ]);

        // Verify access
        if ($user->business_id != $validated['business_id'] && !$user->hasRole('superadmin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to business'
            ], 403);
        }

        $preview = $this->previewService->generatePreview($validated, $validated['business_id']);

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'message' => 'Preview generated successfully'
        ]);
    }
}
