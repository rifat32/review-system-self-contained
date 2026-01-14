<?php

namespace App\Http\Controllers;

use App\Models\{AiRule, ReviewNew, Branch};
use App\Services\Rule\ConditionBuilderService;
use App\Services\Rule\{RuleExplanationService, RuleMetricsService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Log};

class RuleWizardController extends Controller
{
    protected RuleMetricsService $metricsService;

    public function __construct(RuleMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    // ==================== STEP 1: NAME & DESCRIPTION ====================

    /**
     * Store rule metadata (Step 1)
     * POST /api/v1.0/rule-wizard/step1
     * 
     * @OA\Post(
     *     path="/api/v1.0/rule-wizard/step1",
     *     tags={"AI Rules - Wizard"},
     *     summary="Step 1: Store rule name and description",
     *     @OA\Response(response=200, description="Step 1 completed")
     * )
     */
    public function storeStep1(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'rule_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|in:sentiment,staff,area,rating_mismatch,trend,quality',
            'priority' => 'required|in:critical,high,medium,low'
        ]);

        // Verify user has access to business
        if ($user->business_id != $validated['business_id'] && !$user->hasRole('superadmin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to business'
            ], 403);
        }

        // Store in session
        session(['rule_wizard' => $validated]);

        return response()->json([
            'success' => true,
            'data' => $validated,
            'next_step' => 'select_data_source',
            'message' => 'Step 1 completed successfully'
        ]);
    }

    // ==================== STEP 2: DATA SOURCE SELECTION ====================

    /**
     * Select data source (Step 2)
     * POST /api/v1.0/rule-wizard/step2
     */
    public function storeStep2(Request $request)
    {
        $validated = $request->validate([
            'data_source' => 'required|in:comments,ratings,both',
            'applies_to' => 'required|in:all_reviews,star_ratings,specific_questions,specific_branches'
        ]);

        $wizardData = session('rule_wizard', []);

        if (empty($wizardData)) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete Step 1 first'
            ], 400);
        }

        $wizardData['data_source'] = $validated;
        session(['rule_wizard' => $wizardData]);

        return response()->json([
            'success' => true,
            'data' => $validated,
            'next_step' => 'build_conditions',
            'message' => 'Step 2 completed successfully'
        ]);
    }

    // ==================== STEP 3: BUILD CONDITIONS ====================

    /**
     * Build conditions (Step 3)
     * POST /api/v1.0/rule-wizard/step3
     */
    public function storeStep3(Request $request)
    {
        $validated = $request->validate([
            'conditions' => 'required|array|min:1',
            'conditions.*.source' => 'required|in:Comment,Rating,Staff,Area,Emotion',
            'conditions.*.type' => 'required|in:sentiment,rating,keyword,staff_mention,area_mention,emotion,service_type',
            'conditions.*.operator' => 'required|in:equals,contains,greater_than,less_than,between,not_equals,starts_with,ends_with,regex',
            'conditions.*.value' => 'required',
            'conditions.*.logic' => 'nullable|in:AND,OR',
            'conditions.*.analyse_comment_for' => 'nullable|string', // Additional context for UI
            'actions' => 'required|array|min:1',
            'actions.*' => 'required|in:flag_review,notify_manager,recommend_coaching,link_staff,escalate,notify_slack,notify_email',
            'sensitivity' => 'nullable|numeric|min:0|max:100'
        ]);

        $wizardData = session('rule_wizard', []);

        if (empty($wizardData)) {
            return response()->json([
                'success' => false,
                'message' => 'Please complete previous steps first'
            ], 400);
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

        $wizardData['conditions'] = $validated['conditions'];
        $wizardData['actions'] = $validated['actions'];
        $wizardData['sensitivity'] = $validated['sensitivity'] ?? 70;

        session(['rule_wizard' => $wizardData]);

        return response()->json([
            'success' => true,
            'data' => [
                'conditions' => $validated['conditions'],
                'actions' => $validated['actions'],
                'sensitivity' => $wizardData['sensitivity']
            ],
            'next_step' => 'preview_and_activate',
            'message' => 'Step 3 completed successfully'
        ]);
    }

    // ==================== STEP 4: PREVIEW & ACTIVATE ====================

    /**
     * Preview rule with "what-if" analysis (Step 4)
     * POST /api/v1.0/rule-wizard/step4/preview
     */
    public function previewRule(Request $request)
    {
        $wizardData = session('rule_wizard');

        if (empty($wizardData)) {
            return response()->json([
                'success' => false,
                'message' => 'No wizard session found. Please start from Step 1'
            ], 400);
        }

        $businessId = $wizardData['business_id'];

        // Get recent reviews for simulation (last 50)
        $recentReviews = ReviewNew::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->with(['reviewValueNews', 'tags'])
            ->limit(50)
            ->get();

        $matchedReviews = [];
        $estimatedTriggers = 0;

        foreach ($recentReviews as $review) {
            // Simulate rule matching
            $aiData = $this->getReviewAIData($review);

            $isMatch = ConditionBuilderService::evaluateConditions(
                $wizardData['conditions'],
                $review,
                $aiData,
                'AND' // default logic
            );

            if ($isMatch) {
                $estimatedTriggers++;

                if (count($matchedReviews) < 5) {
                    $matchedReviews[] = [
                        'review_id' => $review->id,
                        'comment' => substr($review->comment, 0, 150) . '...',
                        'rating' => $review->rating,
                        'created_at' => $review->created_at->diffForHumans(),
                        'sentiment' => $aiData['sentiment'] ?? 'unknown'
                    ];
                }
            }
        }

        // Calculate estimated precision and impact
        $precisionEstimate = $this->estimatePrecision($wizardData, $estimatedTriggers);
        $impactSummary = $this->generateImpactSummary($wizardData, $estimatedTriggers);

        return response()->json([
            'success' => true,
            'preview' => [
                'estimated_triggers_past_50_reviews' => $estimatedTriggers,
                'estimated_monthly_triggers' => $this->projectMonthlyTriggers($estimatedTriggers, $recentReviews),
                'sample_matches' => $matchedReviews,
                'precision_estimate' => $precisionEstimate,
                'confidence_level' => $this->calculateConfidenceLevel($estimatedTriggers),
                'impact_summary' => $impactSummary,
                'visual_summary' => $this->generateVisualSummary($wizardData)
            ],
            'message' => 'Preview generated successfully'
        ]);
    }

    /**
     * Finalize and create rule (Step 4)
     * POST /api/v1.0/rule-wizard/step4/activate
     */
    public function activateRule(Request $request)
    {
        $wizardData = session('rule_wizard');

        if (empty($wizardData)) {
            return response()->json([
                'success' => false,
                'message' => 'No wizard session found'
            ], 400);
        }

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'auto_activate' => 'nullable|boolean',
            'multi_tag_detection' => 'boolean',
            'trigger_only_on_first_occurrence' => 'boolean',
            'run_frequency' => 'nullable|in:real_time,hourly,daily,weekly',
            'cooldown_days' => 'nullable|integer|min:0',
            'deduplication_scope' => 'nullable|in:review,staff,category,branch,staff_category',
            'applies_to' => 'nullable|in:new_reviews_only,all_reviews'
        ]);

        DB::beginTransaction();
        try {
            // Create the actual AI rule
            $rule = AiRule::create([
                'rule_id' => 'custom_' . uniqid(),
                'rule_name' => $wizardData['rule_name'],
                'description' => $wizardData['description'] ?? '',
                'scope' => 'business',
                'business_id' => $wizardData['business_id'],
                'category' => $wizardData['category'],
                'priority' => $wizardData['priority'],
                'enabled' => $validated['enabled'],
                'conditions' => $wizardData['conditions'],
                'actions' => $wizardData['actions'],
                'multi_tag_detection' => $validated['multi_tag_detection'] ?? $wizardData['multi_tag_detection'] ?? false,
                'trigger_only_on_first_occurrence' => $validated['trigger_only_on_first_occurrence'] ?? $wizardData['trigger_only_on_first_occurrence'] ?? false,
                'run_frequency' => $validated['run_frequency'] ?? $wizardData['run_frequency'] ?? 'daily',
                'cooldown_days' => $validated['cooldown_days'] ?? $wizardData['cooldown_days'] ?? 7,
                'deduplication_scope' => $validated['deduplication_scope'] ?? $wizardData['deduplication_scope'] ?? 'staff',
                'applies_to' => $validated['applies_to'] ?? $wizardData['applies_to'] ?? 'new_reviews_only',
                'created_by' => $request->user()->id,
                'version' => 1
            ]);

            // Generate AI explanations if RuleExplanationHelper exists
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

            // Clear session
            session()->forget('rule_wizard');

            Log::info("Rule created via wizard", [
                'rule_id' => $rule->rule_id,
                'created_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rule created successfully',
                'data' => [
                    'rule_id' => $rule->rule_id,
                    'rule_name' => $rule->rule_name,
                    'enabled' => $rule->enabled,
                    'category' => $rule->category,
                    'priority' => $rule->priority
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Failed to create rule via wizard", [
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

    // ==================== WIZARD SESSION MANAGEMENT ====================

    /**
     * Get current wizard session
     * GET /api/v1.0/rule-wizard/session
     */
    public function getWizardSession(Request $request)
    {
        $wizardData = session('rule_wizard', []);

        return response()->json([
            'success' => true,
            'has_session' => !empty($wizardData),
            'data' => $wizardData,
            'current_step' => $this->determineCurrentStep($wizardData)
        ]);
    }

    /**
     * Clear wizard session
     * DELETE /api/v1.0/rule-wizard/session
     */
    public function clearWizardSession(Request $request)
    {
        session()->forget('rule_wizard');

        return response()->json([
            'success' => true,
            'message' => 'Wizard session cleared'
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get AI data for review (simplified)
     */
    private function getReviewAIData(ReviewNew $review): array
    {
        // This would typically call AIProcessor, but we'll simulate for preview
        return [
            'sentiment' => $review->sentiment ?? 'neutral',
            'sentiment_score' => 0.5,
            'staff_mentions' => [],
            'areas' => [],
            'emotions' => []
        ];
    }

    /**
     * Estimate precision rate
     */
    private function estimatePrecision(array $ruleData, int $triggers): float
    {
        $baseAccuracy = 85.0;

        // Adjust based on number of conditions
        $conditionCount = count($ruleData['conditions'] ?? []);
        if ($conditionCount > 3) {
            $baseAccuracy -= 5.0;
        }

        // Adjust based on trigger volume
        if ($triggers < 5) {
            $baseAccuracy -= 10.0; // Low confidence with few triggers
        }

        return min(95.0, max(70.0, $baseAccuracy));
    }

    /**
     * Generate impact summary
     */
    private function generateImpactSummary(array $ruleData, int $triggers): string
    {
        $actions = $ruleData['actions'] ?? [];
        $summaryParts = [];

        if (in_array('flag_review', $actions)) {
            $summaryParts[] = "$triggers reviews would be flagged for review";
        }
        if (in_array('notify_manager', $actions)) {
            $summaryParts[] = "Managers would receive $triggers notifications";
        }
        if (in_array('recommend_coaching', $actions)) {
            $summaryParts[] = "$triggers coaching recommendations would be generated";
        }
        if (in_array('escalate', $actions)) {
            $summaryParts[] = "$triggers issues would be escalated";
        }

        return implode('; ', $summaryParts) ?: 'No significant impact detected';
    }

    /**
     * Project monthly triggers
     */
    private function projectMonthlyTriggers(int $triggers, $reviews): int
    {
        $reviewCount = is_countable($reviews) ? count($reviews) : $reviews->count();

        if ($reviewCount === 0) {
            return 0;
        }

        // Assume 50 reviews represent ~1 week of data
        $weeksOfData = 1;
        $monthlyMultiplier = 4; // 4 weeks per month

        return (int) round(($triggers / $weeksOfData) * $monthlyMultiplier);
    }

    /**
     * Calculate confidence level
     */
    private function calculateConfidenceLevel(int $triggers): string
    {
        if ($triggers >= 10)
            return 'high';
        if ($triggers >= 5)
            return 'medium';
        return 'low';
    }

    /**
     * Generate visual summary for UI
     */
    private function generateVisualSummary(array $ruleData): array
    {
        return [
            'rule_name' => $ruleData['rule_name'],
            'category' => $ruleData['category'],
            'priority' => $ruleData['priority'],
            'condition_count' => count($ruleData['conditions'] ?? []),
            'action_count' => count($ruleData['actions'] ?? []),
            'formatted_conditions' => array_map(function ($condition) {
                return ConditionBuilderService::formatCondition($condition);
            }, $ruleData['conditions'] ?? [])
        ];
    }

    /**
     * Determine current step from session data
     */
    private function determineCurrentStep(array $wizardData): int
    {
        if (empty($wizardData))
            return 0;
        if (!isset($wizardData['data_source']))
            return 1;
        if (!isset($wizardData['conditions']))
            return 2;
        if (!isset($wizardData['actions']))
            return 3;
        return 4;
    }
}
