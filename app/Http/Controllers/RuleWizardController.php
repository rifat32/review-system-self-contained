<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, ReviewNew, Branch};
use App\Services\Rule\{ConditionBuilderService, RuleMetricsService};
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{DB, Log};
use Illuminate\Validation\Rule;

class RuleWizardController extends Controller
{
    protected RuleMetricsService $metricsService;

    public function __construct(RuleMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    // ==================== CREATE RULE ====================

    /**
     * @OA\Post(
     *     path="/v1.0/ai-rules",
     *     operationId="createAiRule",
     *     tags={"AI Rules"},
     *     summary="Create new AI rule",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"rule_name", "category", "priority", "conditions", "actions", "enabled"},
     *             @OA\Property(property="rule_name", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=1000),
     *             @OA\Property(property="category", type="string", enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"}),
     *             @OA\Property(property="priority", type="string", enum={"critical", "high", "medium", "low"}),
     *             @OA\Property(property="conditions", type="object"),
     *             @OA\Property(property="actions", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="enabled", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Rule created")
     * )
     */
    public function createRule(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'required|in:critical,high,medium,low',
            'conditions' => 'required|array',
            'conditions.logic' => 'required|in:AND,OR',
            'conditions.conditions' => 'required|array|min:1',
            'conditions.conditions.*.source' => 'required|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.conditions.*.type' => 'required|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,intensity,frequency,trend_direction',
            'conditions.conditions.*.operator' => 'required|in:equals,eq,contains,greater_than,gt,less_than,lt,between,not_equals,neq,starts_with,ends_with,regex,exists,greater_than_or_equal,gte,less_than_or_equal,lte',
            'conditions.conditions.*.value' => 'nullable',
            'conditions.conditions.*.analyse_comment_for' => 'nullable|string',
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|in:notify_email',
            'recipient' => [Rule::requiredIf(fn() => in_array('notify_email', (array)request()->input('actions', []))), 'nullable', 'email'],
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

        $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);
        if (!empty($errors)) {
            return response()->json(['success' => false, 'message' => 'Invalid condition structure', 'errors' => $errors], 422);
        }

        DB::beginTransaction();
        try {
            $rule = AiRule::create([
                'rule_id' => 'custom_' . Str::uuid(),
                'rule_name' => $validated['rule_name'],
                'description' => $validated['description'] ?? '',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => $validated['category'],
                'priority' => $validated['priority'],
                'enabled' => $validated['enabled'],
                'conditions' => $validated['conditions'],
                'actions' => $validated['actions'],
                'recipient' => $validated['recipient'] ?? null,
                'multi_tag_detection' => $validated['multi_tag_detection'] ?? false,
                'trigger_only_on_first_occurrence' => $validated['trigger_only_on_first_occurrence'] ?? false,
                'run_frequency' => $validated['run_frequency'] ?? 'daily',
                'cooldown_days' => $validated['cooldown_days'] ?? 7,
                'deduplication_scope' => $validated['deduplication_scope'] ?? 'staff',
                'applies_to' => $validated['applies_to'] ?? 'new_reviews_only',
                'branch_ids' => $validated['branch_ids'] ?? null,
                'precision_rate' => $validated['sensitivity'] ?? null,
                'created_by' => $user->id,
                'is_default' => false,
                'version' => 1
            ]);

            $this->metricsService->updateMetrics($rule->rule_id, []);
            DB::commit();

            Log::info("AI Rule Created", ['rule_id' => $rule->rule_id, 'user_id' => $user->id, 'business_id' => $businessId]);

            return response()->json(['success' => true, 'message' => 'Rule created successfully', 'data' => $rule], 201);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ==================== GET ALL RULES ====================

    public function getAllRules(Request $request)
    {
        $user = $request->user();
        $query = AiRule::where('business_id', $user->business_id);
        if ($request->has('category')) $query->where('category', $request->category);
        if ($request->has('priority')) $query->where('priority', $request->priority);
        if ($request->has('enabled')) $query->where('enabled', $request->boolean('enabled'));
        
        $rules = retrieve_data($query);
        return response()->json(['success' => true, 'data' => $rules['data'], 'meta' => $rules['meta']]);
    }

    // ==================== GET RULE BY ID ====================

    public function getRuleById(Request $request, $id)
    {
        $user = $request->user();
        $rule = AiRule::where('id', $id)->where('business_id', $user->business_id)->firstOrFail();
        return response()->json(['success' => true, 'data' => $rule]);
    }

    // ==================== UPDATE RULE ====================

    public function updateRule(Request $request, $id)
    {
        $user = $request->user();
        $rule = AiRule::where('id', $id)->where('business_id', $user->business_id)->firstOrFail();

        $validated = $request->validate([
            'rule_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'sometimes|required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'sometimes|required|in:critical,high,medium,low',
            'conditions' => 'sometimes|required|array',
            'conditions.logic' => 'required_with:conditions|in:AND,OR',
            'conditions.conditions' => 'required_with:conditions|array|min:1',
            'conditions.conditions.*.source' => 'required_with:conditions|in:Comment,Rating,Staff,Area,Emotion,Trend',
            'conditions.conditions.*.type' => 'required_with:conditions|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,intensity,frequency,trend_direction',
            'conditions.conditions.*.operator' => 'required_with:conditions|in:equals,eq,contains,greater_than,gt,less_than,lt,between,not_equals,neq,starts_with,ends_with,regex,exists,greater_than_or_equal,gte,less_than_or_equal,lte',
            'actions' => 'sometimes|required|array|min:1',
            'actions.*' => 'required_with:actions|in:notify_email',
            'recipient' => 'nullable|email',
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

        if (isset($validated['conditions'])) {
            $errors = ConditionBuilderService::validateConditionTree($validated['conditions']);
            if (!empty($errors)) return response()->json(['success' => false, 'message' => 'Invalid condition structure', 'errors' => $errors], 422);
        }

        DB::beginTransaction();
        try {
            $rule->update($validated);
            $rule->version = $rule->version + 1;
            $rule->save();
            DB::commit();

            Log::info("AI Rule Updated", ['rule_id' => $rule->rule_id, 'user_id' => $user->id, 'version' => $rule->version]);

            return response()->json(['success' => true, 'message' => 'Rule updated successfully', 'data' => $rule->fresh()]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ==================== DELETE RULE ====================

    public function deleteRule(Request $request, $id)
    {
        $user = $request->user();
        $rule = AiRule::where('id', $id)->where('business_id', $user->business_id)->firstOrFail();
        $ruleName = $rule->rule_name;
        $rule->delete();
        return response()->json(['success' => true, 'message' => "Rule '$ruleName' deleted successfully"]);
    }

    // ==================== TOGGLE ENABLED ====================

    public function toggleEnabled(Request $request, $id)
    {
        $rule = AiRule::where('id', $id)->where('business_id', $request->user()->business_id)->firstOrFail();
        $rule->update(['enabled' => !$rule->enabled]);
        return response()->json(['success' => true, 'message' => $rule->enabled ? 'Rule enabled' : 'Rule disabled', 'enabled' => $rule->enabled]);
    }
}
