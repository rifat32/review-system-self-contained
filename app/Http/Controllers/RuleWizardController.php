<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, ReviewNew, Branch};
use App\Services\Rule\{ConditionBuilderService, RuleMetricsService, RulePreviewService};
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;


/**
 * RuleWizardController
 * 
 * Manages AI rule creation, updates, deletion, and preview functionality.
 * Rules are used to automatically analyze reviews and trigger actions based on conditions.
 * 
 * @see AiRule Model for schema definition
 */
class RuleWizardController extends Controller
{
    // ==================== DEPENDENCY INJECTION ====================

    protected RuleMetricsService $metricsService;      // Handles rule performance metrics
    protected RulePreviewService $previewService;      // Generates rule previews ("what-if" analysis)

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
     *     path="/v1.0/ai-rules",
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
        // GET AUTHENTICATED USER AND EXTRACT BUSINESS ID FROM TOKEN
        $user = $request->user();
        $businessId = $user->business_id;

        // VERIFY USER HAS BUSINESS ACCESS (Required for creating rules)
        if (!$businessId) {
            throw new AccessDeniedException('Business ID not found in token');
        }

        // VALIDATE REQUEST DATA
        // - Basic information: rule_name, description, category, priority
        // - Conditions: Array of condition objects with source, type, operator, value, logic
        // - Actions: Array of action strings
        // - Configuration: Optional settings for execution control
        $validated = $request->validate([
            // BASIC INFORMATION
            'rule_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'required|in:critical,high,medium,low',

            // CONDITIONS & ACTIONS
            'conditions' => 'required|array|min:1',
            'conditions.*.source' => 'required|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.*.type' => 'required|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,service_type,frequency,trend_direction',
            'conditions.*.operator' => 'required|in:equals,contains,greater_than,less_than,between,not_equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required',
            'conditions.*.logic' => 'nullable|in:AND,OR',
            'conditions.*.analyse_comment_for' => 'nullable|string',
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|in:flag_review,notify_manager,recommend_coaching,link_staff,escalate,notify_slack,notify_email',

            // CONFIGURATION (All optional with sensible defaults)
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


        // ADD BUSINESS ID TO VALIDATED DATA (From authenticated user token)
        $validated['business_id'] = $businessId;

        // VALIDATE CONDITION STRUCTURE (Ensures logical consistency of AND/OR operators)
        $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid condition structure',
                'errors' => $errors
            ], 422);
        }

        // BEGIN DATABASE TRANSACTION (Ensures atomicity - all or nothing)
        DB::beginTransaction();
        try {
            // CREATE THE AI RULE WITH DEFAULT VALUES FOR OPTIONAL FIELDS
            $rule = AiRule::create([
                'rule_id' => 'custom_' . uniqid(),  // Generate unique ID with "custom_" prefix
                'rule_name' => $validated['rule_name'],
                'description' => $validated['description'] ?? '',
                'scope' => 'business',  // User-created rules are always business-scoped
                'business_id' => $validated['business_id'],
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'enabled' => $validated['enabled'],
                'conditions' => $validated['conditions'],  // Stored as JSON
                'actions' => $validated['actions'],        // Stored as JSON array

                // EXECUTION CONTROL SETTINGS (With sensible defaults)
                'multi_tag_detection' => $validated['multi_tag_detection'] ?? false,
                'trigger_only_on_first_occurrence' => $validated['trigger_only_on_first_occurrence'] ?? false,
                'run_frequency' => $validated['run_frequency'] ?? 'daily',
                'cooldown_days' => $validated['cooldown_days'] ?? 7,
                'deduplication_scope' => $validated['deduplication_scope'] ?? 'staff',
                'applies_to' => $validated['applies_to'] ?? 'new_reviews_only',
                'branch_ids' => $validated['branch_ids'] ?? null,  // null = all branches

                // METADATA
                'created_by' => $user->id,
                'version' => 1  // Initial version
            ]);


            // INITIALIZE METRICS RECORD (For tracking rule performance)
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
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ==================== GET ALL RULES ====================

    /**
     * Get all AI rules
     * 
     * @OA\Get(
     *     path="/v1.0/ai-rules",
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

        $rules = retrieve_data($query);

        return response()->json([
            'success' => true,
            'message' => 'Rules retrieved successfully',
            'meta' => $rules['meta'],
            'data' => $rules['data'],
        ]);
    }

    // ==================== GET RULE BY ID ====================

    /**
     * Get single AI rule by ID
     * 
     * @OA\Get(
     *     path="/v1.0/ai-rules/{id}",
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
     *     path="/v1.0/ai-rules/{id}",
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


            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rule updated successfully',
                'data' => $rule->fresh()
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ==================== DELETE RULE ====================

    /**
     * Delete AI rule
     * 
     * @OA\Delete(
     *     path="/v1.0/ai-rules/{id}",
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


            return response()->json([
                'success' => true,
                'message' => "Rule '$ruleName' deleted successfully"
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ==================== PREVIEW RULE ====================

    /**
     * Preview rule with "what-if" analysis
     * 
     * @OA\Post(
     *     path="/v1.0/ai-rules/preview",
     *     operationId="previewRule",
     *     tags={"AI Rules"},
     *     summary="Preview AI rule",
     *     description="Preview how a rule would perform with what-if analysis",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"conditions", "actions", "category"},
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
        $businessId = $user->business_id;

        // VERIFY USER HAS BUSINESS ACCESS
        if (!$businessId) {
            throw new AccessDeniedException('Business ID not found in token');
        }

        $validated = $request->validate([
            'conditions' => 'required|array|min:1',
            'conditions.*.source' => 'required|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.*.type' => 'required|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,service_type,frequency,trend_direction',
            'conditions.*.operator' => 'required|in:equals,contains,greater_than,less_than,between,not_equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required',
            'conditions.*.logic' => 'nullable|in:AND,OR',
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|in:flag_review,notify_manager,recommend_coaching,link_staff,escalate,notify_slack,notify_email',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality',

            // OPTIONAL CONFIGURATION FIELDS
            'trigger_only_on_first_occurrence' => 'nullable|boolean',
            'multi_tag_detection' => 'nullable|boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews'
        ]);

        // ADD BUSINESS ID TO VALIDATED DATA
        $validated['business_id'] = $businessId;

        // ADD DEFAULT VALUES FOR OPTIONAL CONFIGURATION FIELDS
        $validated['trigger_only_on_first_occurrence'] = $validated['trigger_only_on_first_occurrence'] ?? false;
        $validated['multi_tag_detection'] = $validated['multi_tag_detection'] ?? false;
        $validated['run_frequency'] = $validated['run_frequency'] ?? 'daily';
        $validated['cooldown_days'] = $validated['cooldown_days'] ?? 7;
        $validated['deduplication_scope'] = $validated['deduplication_scope'] ?? 'review';
        $validated['applies_to'] = $validated['applies_to'] ?? 'new_reviews_only';

        $preview = $this->previewService->generatePreview($validated, $businessId);

        return response()->json([
            'success' => true,
            'preview' => $preview,
            'message' => 'Preview generated successfully'
        ]);
    }

    /**
     * Toggle rule enabled status
     * 
     * @OA\Patch(
     *     path="/v1.0/ai-rules/{id}/toggle",
     *     operationId="toggleRuleEnabled",
     *     tags={"AI Rules"},
     *     summary="Toggle rule status",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Status toggled")
     * )
     */
    public function toggleEnabled(Request $request, $id)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('id', $id)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $rule->update(['enabled' => !$rule->enabled]);

        return response()->json([
            'success' => true,
            'message' => $rule->enabled ? 'Rule enabled' : 'Rule disabled',
            'enabled' => $rule->enabled
        ]);
    }
}
