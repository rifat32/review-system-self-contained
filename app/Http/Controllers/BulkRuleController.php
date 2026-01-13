<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, Branch};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};

class BulkRuleController extends Controller
{
    // ==================== BULK APPLY RULE ====================

    /**
     * Apply rule to multiple branches
     * POST /api/v1.0/ai-rules/bulk-apply
     * 
     * @OA\Post(
     *     path="/api/v1.0/ai-rules/bulk-apply",
     *     tags={"AI Rules - Bulk"},
     *     summary="Apply rule across multiple branches",
     *     @OA\Response(response=200, description="Bulk operation completed")
     * )
     */
    public function bulkApplyRule(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rule_id' => 'required|exists:ai_rules,rule_id',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
            'enabled' => 'nullable|boolean',
            'override_existing' => 'nullable|boolean'
        ]);

        $businessId = $user->business_id;

        // Get source rule
        $sourceRule = AiRule::where('rule_id', $validated['rule_id'])
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->orWhere('scope', 'system');
            })
            ->firstOrFail();

        // Get branches
        $branches = Branch::whereIn('id', $validated['branch_ids'])
            ->where('business_id', $businessId)
            ->get();

        $created = [];
        $skipped = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($branches as $branch) {
                // Check if rule already exists for this branch
                $conditions = $sourceRule->conditions;
                $conditions['branch_id'] = $branch->id;

                $existingRule = AiRule::where('rule_name', $sourceRule->rule_name)
                    ->where('business_id', $businessId)
                    ->whereJsonContains('conditions->branch_id', $branch->id)
                    ->first();

                if ($existingRule && !($validated['override_existing'] ?? false)) {
                    $skipped[] = [
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'reason' => 'Rule already exists for this branch'
                    ];
                    continue;
                }

                // If override, delete existing
                if ($existingRule && ($validated['override_existing'] ?? false)) {
                    $existingRule->delete();
                }

                // Clone rule for branch
                $newRule = AiRule::create([
                    'rule_id' => 'branch_' . $branch->id . '_' . uniqid(),
                    'rule_name' => $sourceRule->rule_name . " (Branch: {$branch->name})",
                    'description' => $sourceRule->description,
                    'scope' => 'business',
                    'business_type' => $sourceRule->business_type,
                    'business_id' => $businessId,
                    'category' => $sourceRule->category,
                    'priority' => $sourceRule->priority,
                    'enabled' => $validated['enabled'] ?? $sourceRule->enabled,
                    'conditions' => $conditions,
                    'actions' => $sourceRule->actions,
                    'explainability' => $sourceRule->explainability,
                    'short_explanation' => $sourceRule->short_explanation,
                    'detailed_explanation' => $sourceRule->detailed_explanation,
                    'why_it_matters' => $sourceRule->why_it_matters,
                    'created_by' => $user->id,
                    'version' => 1
                ]);

                $created[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'rule_id' => $newRule->rule_id,
                    'rule_name' => $newRule->rule_name
                ];
            }

            DB::commit();

            Log::info("Bulk rule application completed", [
                'source_rule_id' => $validated['rule_id'],
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk rule application completed',
                'summary' => [
                    'total_branches' => count($validated['branch_ids']),
                    'created' => count($created),
                    'skipped' => count($skipped),
                    'errors' => count($errors)
                ],
                'details' => [
                    'created' => $created,
                    'skipped' => $skipped,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Bulk rule application failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== GET CROSS-BRANCH RULES ====================

    /**
     * Get rules applicable across branches
     * GET /api/v1.0/ai-rules/cross-branch
     */
    public function getCrossBranchRules(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        // Get rules that can be applied across branches
        $rules = AiRule::where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->whereIn('scope', ['business', 'system']);
        })
            ->with('metrics')
            ->get();

        // Get active branches
        $branches = Branch::where('business_id', $businessId)
            ->where('is_active', true)
            ->select('id', 'name', 'location', 'is_active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'rules' => $rules->map(function ($rule) {
                    return [
                        'rule_id' => $rule->rule_id,
                        'rule_name' => $rule->rule_name,
                        'category' => $rule->category,
                        'priority' => $rule->priority,
                        'enabled' => $rule->enabled,
                        'scope' => $rule->scope,
                        'lifetime_triggers' => $rule->metrics?->lifetime_triggers ?? 0,
                        'precision_rate' => $rule->metrics?->getFormattedPrecisionRate() ?? 'N/A'
                    ];
                }),
                'branches' => $branches,
                'can_apply_to' => $branches->count()
            ]
        ]);
    }

    // ==================== BULK ENABLE/DISABLE ====================

    /**
     * Bulk enable rules
     * POST /api/v1.0/ai-rules/bulk-enable
     */
    public function bulkEnableRules(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => 'exists:ai_rules,rule_id'
        ]);

        $businessId = $user->business_id;

        $updated = AiRule::whereIn('rule_id', $validated['rule_ids'])
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->orWhere('scope', 'system');
            })
            ->update(['enabled' => true]);

        Log::info("Bulk enable rules", [
            'rule_ids' => $validated['rule_ids'],
            'updated_count' => $updated,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => "$updated rules enabled successfully",
            'updated_count' => $updated
        ]);
    }

    /**
     * Bulk disable rules
     * POST /api/v1.0/ai-rules/bulk-disable
     */
    public function bulkDisableRules(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => 'exists:ai_rules,rule_id'
        ]);

        $businessId = $user->business_id;

        $updated = AiRule::whereIn('rule_id', $validated['rule_ids'])
            ->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->orWhere('scope', 'system');
            })
            ->update(['enabled' => false]);

        Log::info("Bulk disable rules", [
            'rule_ids' => $validated['rule_ids'],
            'updated_count' => $updated,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => "$updated rules disabled successfully",
            'updated_count' => $updated
        ]);
    }

    // ==================== BULK DELETE ====================

    /**
     * Bulk delete rules
     * POST /api/v1.0/ai-rules/bulk-delete
     */
    public function bulkDeleteRules(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => 'exists:ai_rules,rule_id',
            'confirm' => 'required|boolean|accepted'
        ]);

        if (!$validated['confirm']) {
            return response()->json([
                'success' => false,
                'message' => 'Delete confirmation required'
            ], 400);
        }

        $businessId = $user->business_id;

        DB::beginTransaction();
        try {
            $deleted = AiRule::whereIn('rule_id', $validated['rule_ids'])
                ->where('business_id', $businessId) // Only allow deleting business-specific rules
                ->delete();

            DB::commit();

            Log::warning("Bulk delete rules", [
                'rule_ids' => $validated['rule_ids'],
                'deleted_count' => $deleted,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => "$deleted rules deleted successfully",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== BULK STATUS UPDATE ====================

    /**
     * Bulk update rule priority
     * POST /api/v1.0/ai-rules/bulk-priority
     */
    public function bulkUpdatePriority(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => 'exists:ai_rules,rule_id',
            'priority' => 'required|in:critical,high,medium,low'
        ]);

        $businessId = $user->business_id;

        $updated = AiRule::whereIn('rule_id', $validated['rule_ids'])
            ->where('business_id', $businessId)
            ->update(['priority' => $validated['priority']]);

        Log::info("Bulk update priority", [
            'rule_ids' => $validated['rule_ids'],
            'new_priority' => $validated['priority'],
            'updated_count' => $updated,
            'user_id' => $user->id
        ]);

        return response()->json([
            'success' => true,
            'message' => "$updated rules updated to {$validated['priority']} priority",
            'updated_count' => $updated
        ]);
    }
}
