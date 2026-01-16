<?php
// app/Http/Controllers/AiRuleController.php

namespace App\Http\Controllers;

use App\Models\AiRule;
use App\Services\Rule\RuleExplanationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiRuleController extends Controller
{
    /**
     * Display a listing of rules with explanations
     */
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;

        $rules = AiRule::forBusiness($businessId)
            ->enabled()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Transform rules for display
        $rulesData = $rules->map(function ($rule) {
            return [
                'id' => $rule->id,
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'category' => $rule->category,
                'category_icon' => $rule->getCategoryIcon(),
                'priority' => $rule->priority,
                'priority_label' => $rule->getPriorityLabel(),
                'priority_color' => $rule->getPriorityColor(),
                'enabled' => $rule->enabled,
                'created_at' => $rule->created_at->diffForHumans(),

                // Explanation data
                'short_explanation' => $rule->short_explanation ?? 'No explanation available',
                'has_explanations' => $rule->hasExplanations(),
                'explanations_outdated' => $rule->explanationsOutdated()
            ];
        });

        return response()->json([
            'success' => true,
            'rules' => $rulesData,
            'pagination' => [
                'current_page' => $rules->currentPage(),
                'total_pages' => $rules->lastPage(),
                'per_page' => $rules->perPage(),
                'total' => $rules->total()
            ]
        ]);
    }

    /**
     * Display detailed rule information with full explanations
     */
    public function show(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->forBusiness($businessId)
            ->firstOrFail();

        $explanations = $rule->getFormattedExplanation();

        return response()->json([
            'success' => true,
            'rule' => [
                'id' => $rule->id,
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'description' => $rule->description,
                'category' => $rule->category,
                'category_icon' => $rule->getCategoryIcon(),
                'priority' => $rule->priority,
                'priority_label' => $rule->getPriorityLabel(),
                'priority_color' => $rule->getPriorityColor(),
                'enabled' => $rule->enabled,
                'scope' => $rule->scope,
                'created_by' => $rule->created_by,
                'version' => $rule->version,
                'created_at' => $rule->created_at->format('M d, Y H:i'),
                'updated_at' => $rule->updated_at->format('M d, Y H:i'),

                // Conditions and actions
                'conditions' => $rule->conditions,
                'actions' => $rule->actions,
                'explainability' => $rule->explainability,

                // Full explanation data
                'explanations' => $explanations
            ]
        ]);
    }

    /**
     * Create a new rule with AI-generated explanations
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rule_name' => 'required|string|max:255',
            'description' => 'required|string',
            'scope' => 'required|in:system,business_type,business',
            'business_type' => 'nullable|string|max:50',
            'business_id' => 'nullable|exists:businesses,id',
            'category' => 'required|string|max:50',
            'priority' => 'required|in:critical,high,medium,low',
            'enabled' => 'boolean',
            'conditions' => 'required|array',
            'conditions.*.source' => 'nullable|in:Comment,Rating,Staff,Area,Emotion',
            'actions' => 'required|array',
            'multi_tag_detection' => 'boolean',
            'trigger_only_on_first_occurrence' => 'boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews'
        ]);

        $businessId = $request->user()->business_id;
        $business = $request->user()->business;

        // Generate unique rule ID
        $ruleId = 'MANUAL_' . strtoupper(substr($validated['category'], 0, 4)) . '_' . time();

        // Prepare data for explanation generation
        $ruleData = [
            'business_type' => $business->business_type ?? 'Business',
            'rule_name' => $validated['rule_name'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'main_category' => $validated['conditions']['category_match']['main_category'] ?? '',
            'sub_category' => $validated['conditions']['category_match']['sub_category'] ?? ''
        ];

        // Generate AI explanations
        $explanations = RuleExplanationService::generateExplanations($ruleData);

        // Use fallback if AI generation fails
        if (!$explanations) {
            Log::warning('AI explanation generation failed, using fallback', [
                'business_id' => $businessId,
                'rule_name' => $validated['rule_name']
            ]);

            $explanations = RuleExplanationService::generateFallbackExplanations($ruleData);
        }

        // Create the rule
        $rule = AiRule::create([
            'rule_id' => $ruleId,
            'rule_name' => $validated['rule_name'],
            'description' => $validated['description'],
            'scope' => 'business',
            'business_id' => $businessId,
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'enabled' => $validated['enabled'] ?? true,
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'multi_tag_detection' => $validated['multi_tag_detection'] ?? false,
            'trigger_only_on_first_occurrence' => $validated['trigger_only_on_first_occurrence'] ?? false,
            'run_frequency' => $validated['run_frequency'] ?? 'daily',
            'cooldown_days' => $validated['cooldown_days'] ?? 7,
            'deduplication_scope' => $validated['deduplication_scope'] ?? 'staff',
            'applies_to' => $validated['applies_to'] ?? 'new_reviews_only',
            'short_explanation' => $explanations['short_explanation'],
            'detailed_explanation' => $explanations['detailed_explanation'],
            'why_it_matters' => $explanations['why_it_matters'],
            'explanation_generated_at' => now(),
            'created_by' => 'user_' . $request->user()->id,
            'version' => 1,
            'is_default' => false // CRITICAL: User-created rules are NEVER default
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rule created successfully with AI explanations',
            'rule' => [
                'id' => $rule->id,
                'rule_id' => $rule->rule_id,
                'rule_name' => $rule->rule_name,
                'category' => $rule->category,
                'priority' => $rule->priority,
                'explanations' => $rule->getFormattedExplanation()
            ]
        ], 201);
    }

    /**
     * Update an existing rule and regenerate explanations if needed
     */
    public function update(Request $request, $ruleId)
    {
        $validated = $request->validate([
            'rule_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|string|in:critical,high,medium,low',
            'conditions' => 'sometimes|array',
            'actions' => 'sometimes|array',
            'enabled' => 'boolean',
            'multi_tag_detection' => 'boolean',
            'trigger_only_on_first_occurrence' => 'boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews'
        ]);

        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        // Check if conditions or actions changed (logic change)
        $logicChanged = isset($validated['conditions']) || isset($validated['actions']);

        // Update rule
        $rule->update($validated);

        // Increment version
        $rule->increment('version');

        // Regenerate explanations synchronously if logic changed
        if ($logicChanged) {
            Log::info('Rule logic changed, regenerating explanations', [
                'rule_id' => $ruleId,
                'business_id' => $businessId
            ]);

            try {
                $explanations = RuleExplanationService::regenerateForRule($rule);

                if ($explanations) {
                    $rule->update([
                        'short_explanation' => $explanations['short_explanation'],
                        'detailed_explanation' => $explanations['detailed_explanation'],
                        'why_it_matters' => $explanations['why_it_matters'],
                        'explanation_generated_at' => now()
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Rule updated successfully with new explanations',
                        'logic_changed' => true,
                        'explanations_regenerated' => true,
                        'rule' => [
                            'id' => $rule->id,
                            'rule_id' => $rule->rule_id,
                            'version' => $rule->version,
                            'explanations' => $rule->getFormattedExplanation()
                        ]
                    ]);
                } else {
                    // Regeneration failed, but rule was updated
                    return response()->json([
                        'success' => true,
                        'message' => 'Rule updated successfully, but explanation regeneration failed. Please regenerate manually.',
                        'logic_changed' => true,
                        'explanations_regenerated' => false,
                        'rule' => [
                            'id' => $rule->id,
                            'rule_id' => $rule->rule_id,
                            'version' => $rule->version
                        ]
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to regenerate explanations after update', [
                    'rule_id' => $ruleId,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Rule updated successfully, but explanation regeneration failed. Please regenerate manually.',
                    'logic_changed' => true,
                    'explanations_regenerated' => false,
                    'error' => $e->getMessage(),
                    'rule' => [
                        'id' => $rule->id,
                        'rule_id' => $rule->rule_id,
                        'version' => $rule->version
                    ]
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rule updated successfully',
            'logic_changed' => false,
            'rule' => [
                'id' => $rule->id,
                'rule_id' => $rule->rule_id,
                'version' => $rule->version,
                'explanations' => $rule->getFormattedExplanation()
            ]
        ]);
    }

    /**
     * Manually regenerate explanations for a rule
     */
    public function regenerateExplanations(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        Log::info('Manual explanation regeneration requested', [
            'rule_id' => $ruleId,
            'user_id' => $request->user()->id
        ]);

        $explanations = RuleExplanationService::regenerateForRule($rule);

        if ($explanations) {
            $rule->update([
                'short_explanation' => $explanations['short_explanation'],
                'detailed_explanation' => $explanations['detailed_explanation'],
                'why_it_matters' => $explanations['why_it_matters'],
                'explanation_generated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Explanations regenerated successfully',
                'explanations' => $rule->getFormattedExplanation()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to regenerate explanations'
        ], 500);
    }

    /**
     * Get rules that need explanation generation
     */
    public function missingExplanations(Request $request)
    {
        $businessId = $request->user()->business_id;

        $rulesWithoutExplanations = AiRule::forBusiness($businessId)
            ->withoutExplanations()
            ->count();

        $rulesWithOutdatedExplanations = AiRule::forBusiness($businessId)
            ->withOutdatedExplanations()
            ->count();

        return response()->json([
            'success' => true,
            'stats' => [
                'missing_explanations' => $rulesWithoutExplanations,
                'outdated_explanations' => $rulesWithOutdatedExplanations,
                'total_needing_update' => $rulesWithoutExplanations + $rulesWithOutdatedExplanations
            ]
        ]);
    }

    /**
     * Batch regenerate explanations for all rules missing them
     */
    public function batchRegenerateExplanations(Request $request)
    {
        $businessId = $request->user()->business_id;

        // Get rules needing explanations
        $rules = AiRule::forBusiness($businessId)
            ->where(function ($query) {
                $query->whereNull('short_explanation')
                    ->orWhereNull('detailed_explanation')
                    ->orWhereNull('why_it_matters');
            })
            ->limit(50) // Process max 50 at a time
            ->get();

        // Also get outdated ones
        $outdatedRules = AiRule::forBusiness($businessId)
            ->withOutdatedExplanations()
            ->limit(20)
            ->get();

        $allRules = $rules->merge($outdatedRules)->unique('id');

        if ($allRules->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No rules need explanation generation',
                'processed' => 0
            ]);
        }

        Log::info('Batch explanation regeneration started', [
            'business_id' => $businessId,
            'rule_count' => $allRules->count(),
            'user_id' => $request->user()->id
        ]);

        $results = RuleExplanationService::batchGenerateExplanations($allRules->all());

        return response()->json([
            'success' => true,
            'message' => 'Batch regeneration completed',
            'results' => $results
        ]);
    }

    /**
     * Toggle rule enabled status
     */
    public function toggleEnabled(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        $rule->update(['enabled' => !$rule->enabled]);

        return response()->json([
            'success' => true,
            'message' => $rule->enabled ? 'Rule enabled' : 'Rule disabled',
            'enabled' => $rule->enabled
        ]);
    }

    public function destroy(Request $request, $ruleId)
    {
        $businessId = $request->user()->business_id;

        $rule = AiRule::where('rule_id', $ruleId)
            ->where('business_id', $businessId)
            ->firstOrFail();

        // CRITICAL: Prevent deletion of default rules
        if ($rule->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete default system rules. These rules are required for dashboard and report functionality.'
            ], 403);
        }

        // Additional check using created_by for backwards compatibility
        if ($rule->created_by === 'system' || str_starts_with($rule->created_by, 'system_')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system-created rules.'
            ], 403);
        }

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rule deleted successfully'
        ]);
    }
}
