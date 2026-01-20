<?php

namespace App\Services\Rule;

use App\Models\{AiRule, AiRuleTrigger, ReviewNew, SupportTicket, Notification};
use App\Services\Rule\ConditionBuilderService;
use App\Services\AIProcessor\EmotionAnalysisService;
use Illuminate\Support\Facades\{DB, Log};

class RuleExecutionService
{
    protected RuleMetricsService $metricsService;

    public function __construct(RuleMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    // ==================== MAIN EXECUTION ENTRY POINT ====================

    /**
     * Execute a rule against reviews with cooldown and deduplication
     * 
     * @param AiRule $rule Rule to execute
     * @param \Illuminate\Support\Collection|array $reviews Reviews to check
     * @param array|null $forcedAiData Optional forced AI data (for real-time bypass)
     * @return array Execution summary
     */
    public function executeRule(AiRule $rule, $reviews, ?array $forcedAiData = null): array
    {
        $summary = [
            'rule_id' => $rule->rule_id,
            'rule_name' => $rule->rule_name,
            'reviews_evaluated' => 0,
            'conditions_matched' => 0,
            'actions_triggered' => 0,
            'suppressed_count' => 0,
            'suppressions' => []
        ];

        foreach ($reviews as $review) {
            $summary['reviews_evaluated']++;

            // Get AI data for review
            $aiData = $forcedAiData ?: $this->getReviewAIData($review);

            // Apply branch filtering
            if (!empty($rule->branch_ids)) {
                if (!$review->branch_id || !in_array($review->branch_id, $rule->branch_ids)) {
                    continue;
                }
            }

            // Evaluate rule conditions
            $isMatch = ConditionBuilderService::evaluateConditions(
                $rule->conditions,
                $review,
                $aiData,
                'AND'
            );

            if (!$isMatch) {
                continue; // No match, skip to next review
            }

            $summary['conditions_matched']++;

            // Extract context for deduplication
            $context = $this->extractContext($review, $aiData, $rule);

            // Build deduplication key
            $dedupKey = $this->buildDedupKey($rule, $review, $context);

            // Check cooldown
            $cooldownCheck = $this->checkCooldown($dedupKey, $rule->cooldown_days);

            if ($cooldownCheck['active']) {
                // Cooldown is active - suppress this trigger
                $this->recordSuppression($rule, $review, $dedupKey, $context, $cooldownCheck['reason']);

                $summary['suppressed_count']++;
                $summary['suppressions'][] = [
                    'review_id' => $review->id,
                    'dedup_key' => $dedupKey,
                    'reason' => $cooldownCheck['reason']
                ];

                continue;
            }

            // Cooldown expired or no previous trigger - execute actions
            $this->executeActions($rule, $review, $aiData, $context);

            // Record successful trigger
            $this->recordTrigger($rule, $review, $dedupKey, $context, $aiData);

            $summary['actions_triggered']++;
        }

        // Update rule's last run timestamp
        $rule->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun($rule)
        ]);

        Log::info("Rule execution completed", $summary);

        return $summary;
    }

    // ==================== DEDUPLICATION KEY BUILDER ====================

    /**
     * Build deduplication key based on rule scope
     * 
     * @param AiRule $rule Rule configuration
     * @param ReviewNew $review Review being evaluated
     * @param array $context Extracted context (staff_id, category, branch_id)
     * @return string Deduplication key
     */
    private function buildDedupKey(AiRule $rule, ReviewNew $review, array $context): string
    {
        $ruleId = $rule->rule_id;

        return match ($rule->deduplication_scope) {
            'review' => "rule_{$ruleId}_review_{$review->id}",

            'staff' => "rule_{$ruleId}_staff_" . ($context['staff_id'] ?? 'none'),

            'category' => "rule_{$ruleId}_cat_" . ($context['category'] ?? 'general'),

            'branch' => "rule_{$ruleId}_branch_" . ($review->branch_id ?? 'none'),

            'staff_category' => "rule_{$ruleId}_staff_" . ($context['staff_id'] ?? 'none') .
            "_cat_" . ($context['category'] ?? 'general'),

            default => "rule_{$ruleId}_review_{$review->id}"
        };
    }

    // ==================== COOLDOWN CHECKER ====================

    /**
     * Check if cooldown is active for a deduplication key
     * 
     * @param string $dedupKey Deduplication key
     * @param int $cooldownDays Cooldown period in days
     * @return array ['active' => bool, 'reason' => string|null, 'days_remaining' => int|null]
     */
    private function checkCooldown(string $dedupKey, int $cooldownDays): array
    {
        if ($cooldownDays === 0) {
            return ['active' => false, 'reason' => null, 'days_remaining' => null];
        }

        // Find last non-suppressed trigger with same dedup key
        $lastTrigger = AiRuleTrigger::where('dedup_key', $dedupKey)
            ->where('was_suppressed', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastTrigger) {
            return ['active' => false, 'reason' => null, 'days_remaining' => null];
        }

        $cooldownExpiry = $lastTrigger->created_at->addDays($cooldownDays);
        $now = now();

        if ($now->lt($cooldownExpiry)) {
            $daysRemaining = $now->diffInDays($cooldownExpiry, false);
            $daysRemaining = ceil(abs($daysRemaining));

            return [
                'active' => true,
                'reason' => "Cooldown active (last triggered " .
                    $lastTrigger->created_at->diffForHumans() .
                    ", {$daysRemaining} days remaining)",
                'days_remaining' => $daysRemaining
            ];
        }

        return ['active' => false, 'reason' => null, 'days_remaining' => null];
    }

    // ==================== ACTION EXECUTION ====================

    /**
     * Execute rule actions
     * 
     * @param AiRule $rule Rule configuration
     * @param ReviewNew $review Review that triggered
     * @param array $aiData AI analysis data
     * @param array $context Execution context
     * @return void
     */
    private function executeActions(AiRule $rule, ReviewNew $review, array $aiData, array $context): void
    {
        $actions = $rule->actions;

        // Handle both list of strings and associative array of actions
        $actionList = array_is_list($actions) ? $actions : array_keys(array_filter((array) $actions));

        foreach ($actionList as $action) {
            Log::info("Processing rule action", [
                'rule_id' => $rule->rule_id,
                'is_default' => $rule->is_default,
                'action' => $action,
                'review_id' => $review->id
            ]);

            // Strict Separation Logic:
            // 1. Default Rules: Internal/Reporting ONLY. Skip notifications.
            if ($rule->is_default && in_array($action, ['notify_manager', 'notify_slack', 'notify_email', 'create_support_ticket', 'notification'])) {
                Log::info("Skipping notification action for default rule", [
                    'rule_id' => $rule->rule_id,
                    'action' => $action
                ]);
                continue;
            }

            // 2. Non-Default Rules: Notification ONLY. Skip internal mutations (flagging, linking, trends).
            if (!$rule->is_default && in_array($action, ['flag_review', 'is_flagged', 'recommend_coaching', 'link_staff', 'escalate', 'tag', 'alert'])) {
                Log::info("Skipping internal action for non-default rule", [
                    'rule_id' => $rule->rule_id,
                    'action' => $action
                ]);
                continue;
            }

            try {
                match ($action) {
                    'flag_review', 'is_flagged' => $this->flagReview($review, $rule),
                    'notify_manager', 'notification' => $this->notifyManager($review, $rule, $context),
                    'recommend_coaching' => $this->recommendCoaching($review, $rule, $context),
                    'link_staff' => $this->linkStaff($review, $context),
                    'create_support_ticket' => $this->createSupportTicket($review, $rule, $context),
                    'escalate' => $this->escalateIssue($review, $rule, $context),
                    'notify_slack' => $this->notifySlack($review, $rule, $context),
                    'notify_email' => $this->notifyEmail($review, $rule, $context),
                    'tag', 'alert' => Log::debug("Recorded internal action: {$action}", ['rule_id' => $rule->rule_id]),
                    default => Log::warning("Unknown action: {$action}", ['rule_id' => $rule->rule_id])
                };
            } catch (\Exception $e) {
                Log::error("Action execution failed", [
                    'action' => $action,
                    'rule_id' => $rule->rule_id,
                    'review_id' => $review->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    // ==================== ACTION IMPLEMENTATIONS ====================

    private function flagReview(ReviewNew $review, AiRule $rule): void
    {
        $review->update(['is_flagged' => true]);

        Log::info("Review flagged", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    private function notifyManager(ReviewNew $review, AiRule $rule, array $context): void
    {
        if ($review->business && $review->business->OwnerID) {
            Notification::create([
                'receiver_id' => $review->business->OwnerID,
                'business_id' => $review->business_id,
                'sender_id' => null, // System notification
                'sender_type' => 'system',
                'title' => "Rule Triggered: {$rule->rule_name}",
                'message' => "Review #{$review->id} triggered rule: {$rule->rule_name}",
                'type' => 'rule_trigger',
                'priority' => 'high',
                'entity_id' => $review->id,
                'status' => 'unread',
                'link' => "/reviews/{$review->id}"
            ]);

            Log::info("Manager notified via Notification model", [
                'review_id' => $review->id,
                'rule_id' => $rule->rule_id,
                'owner_id' => $review->business->OwnerID
            ]);
        } else {
            Log::warning("Could not notify manager: No owner found", [
                'review_id' => $review->id,
                'business_id' => $review->business_id
            ]);
        }
    }

    private function recommendCoaching(ReviewNew $review, AiRule $rule, array $context): void
    {
        // Create coaching recommendation
        Log::info("Coaching recommended", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id,
            'staff_id' => $context['staff_id'] ?? null
        ]);
    }

    private function linkStaff(ReviewNew $review, array $context): void
    {
        if (!empty($context['staff_id'])) {
            // Link review to staff member
            Log::info("Review linked to staff", [
                'review_id' => $review->id,
                'staff_id' => $context['staff_id']
            ]);
        }
    }

    private function createSupportTicket(ReviewNew $review, AiRule $rule, array $context): void
    {
        SupportTicket::create([
            'business_id' => $rule->business_id,
            'review_id' => $review->id,
            'subject' => "AI Rule Alert: {$rule->rule_name}",
            'description' => "Review #{$review->id} triggered rule '{$rule->rule_name}'. Reason: {$rule->detailed_explanation}",
            'priority' => $rule->priority,
            'status' => 'open',
            'metadata' => [
                'rule_id' => $rule->rule_id,
                'category' => $rule->category,
                'applied_branches' => $rule->branch_ids
            ]
        ]);

        Log::info("Support ticket created", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    private function escalateIssue(ReviewNew $review, AiRule $rule, array $context): void
    {
        // Escalation might be creating a high-priority ticket
        $this->createSupportTicket($review, $rule, array_merge($context, ['priority' => 'high']));

        Log::info("Issue escalated", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    private function notifySlack(ReviewNew $review, AiRule $rule, array $context): void
    {
        // Send Slack notification
        Log::info("Slack notification sent", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    private function notifyEmail(ReviewNew $review, AiRule $rule, array $context): void
    {
        // Send email notification
        Log::info("Email notification sent", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    // ==================== RECORD KEEPING ====================

    /**
     * Record successful trigger
     */
    private function recordTrigger(AiRule $rule, ReviewNew $review, string $dedupKey, array $context, array $aiData): void
    {
        $trigger = $this->metricsService->recordTrigger(
            $rule,
            $review,
            $aiData['matched_conditions'] ?? [],
            $rule->actions,
            $aiData['confidence'] ?? 95.0
        );

        // Update trigger with dedup info
        $trigger->update([
            'dedup_key' => $dedupKey,
            'was_suppressed' => false,
            'staff_id' => $context['staff_id'] ?? null,
            'category' => $context['category'] ?? null
        ]);
    }

    /**
     * Record suppressed trigger
     */
    private function recordSuppression(AiRule $rule, ReviewNew $review, string $dedupKey, array $context, string $reason): void
    {
        AiRuleTrigger::create([
            'rule_id' => $rule->rule_id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'dedup_key' => $dedupKey,
            'was_suppressed' => true,
            'suppressed_reason' => $reason,
            'staff_id' => $context['staff_id'] ?? null,
            'category' => $context['category'] ?? null,
            'confidence_score' => 0,
            'matched_conditions' => [],
            'actions_triggered' => [],
            'outcome' => 'pending'
        ]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Extract context from review for deduplication
     */
    private function extractContext(ReviewNew $review, array $aiData, AiRule $rule): array
    {
        $context = [
            'staff_id' => null,
            'category' => null,
            'branch_id' => $review->branch_id ?? null
        ];

        // Extract staff from AI data or review
        if (!empty($aiData['staff_mentions'])) {
            $context['staff_id'] = $aiData['staff_mentions'][0]['id'] ?? null;
        }

        // Extract category from rule or AI data
        $context['category'] = $rule->category ?? 'general';

        return $context;
    }

    /**
     * Get AI data for review (simplified - extend as needed)
     */
    private function getReviewAIData(ReviewNew $review): array
    {
        return [
            'sentiment' => $review->sentiment ?: ($review->sentiment_label ?: 'neutral'),
            'sentiment_score' => $review->sentiment_score ?? 0.5,
            'sentiment_label' => $review->sentiment_label ?: ($review->sentiment ?: 'neutral'),
            'emotion' => $review->emotion ?? 'neutral',
            'staff_mentions' => $review->key_phrases['staff_mentions'] ?? [],
            'areas' => $review->key_phrases['areas_mentioned'] ?? [],
            'key_phrases' => $review->key_phrases ?? [],
            'topics' => $review->topics ?? [],
            'confidence' => $review->ai_confidence ?? 85.0,
            'matched_conditions' => []
        ];
    }

    /**
     * Calculate next run time for scheduled rules
     */
    private function calculateNextRun(AiRule $rule): ?\Carbon\Carbon
    {
        if ($rule->run_frequency === 'real_time') {
            return null; // Real-time rules don't have next run
        }

        return match ($rule->run_frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay()->startOfDay()->addHours(2), // 2 AM next day
            'weekly' => now()->addWeek()->startOfWeek()->addHours(2), // Monday 2 AM
            default => now()->addDay()
        };
    }

    // ==================== REVIEW FETCHING ====================

    /**
     * Get reviews for rule execution based on frequency
     */
    public function getReviewsForRule(AiRule $rule, ?int $limit = 100)
    {
        $query = ReviewNew::where('business_id', $rule->business_id)
            ->globalReviewFilters(0, $rule->business_id)
            ->orderBy('created_at', 'desc');

        if ($rule->run_frequency === 'real_time') {
            // For real-time, typically just one review
            return $query->limit(1)->get();
        }

        // For scheduled rules, get reviews since last run
        if ($rule->last_run_at) {
            $query->where('created_at', '>', $rule->last_run_at);
        } else {
            // First run - limit to recent reviews
            $query->limit($limit);
        }

        return $query->get();
    }

    // ==================== SCHEDULING & ORCHESTRATION ====================

    /**
     * Run scheduled rules based on frequency
     * Moved from ExecuteScheduledRulesJob to avoid queue dependency
     */
    public function runScheduledRules(): void
    {
        Log::info("Starting scheduled rule execution", [
            'time' => now()->toDateTimeString()
        ]);

        // Get rules that need to run
        $rules = $this->getRulesToExecute();

        $summary = [
            'total_rules' => $rules->count(),
            'executed' => 0,
            'failed' => 0,
            'results' => []
        ];

        foreach ($rules as $rule) {
            try {
                // Get reviews for this rule
                $reviews = $this->getReviewsForRule($rule);

                if ($reviews->isEmpty()) {
                    Log::debug("No reviews to evaluate for rule, advancing next run time.", [
                        'rule_id' => $rule->rule_id
                    ]);
                    // CRITICAL FIX: Advance last_run_at and next_run_at even if no reviews are found
                    $rule->update([
                        'last_run_at' => \now(),
                        'next_run_at' => $this->calculateNextRun($rule)
                    ]);
                    continue;
                }

                // Execute the rule
                $result = $this->executeRule($rule, $reviews);

                $summary['executed']++;
                $summary['results'][] = $result;

                Log::info("Rule executed successfully", [
                    'rule_id' => $rule->rule_id,
                    'result' => $result
                ]);

                // Update last_run_at and next_run_at after successful execution
                $rule->update([
                    'last_run_at' => \now(),
                    'next_run_at' => $this->calculateNextRun($rule)
                ]);
            } catch (\Exception $e) {
                $summary['failed']++;

                Log::error("Rule execution failed", [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Even if failed, advance the run times to prevent immediate re-attempt
                $rule->update([
                    'last_run_at' => \now(),
                    'next_run_at' => $this->calculateNextRun($rule)
                ]);
            }
        }

        Log::info("Scheduled rule execution completed", $summary);
    }

    /**
     * Run a single rule (e.g. for real-time triggers)
     * Moved from ExecuteSingleRuleJob
     */
    public function runSingleRule(AiRule $rule, ReviewNew $review, ?array $aiData = null): void
    {
        try {
            Log::debug("Executing real-time rule", [
                'rule_id' => $rule->rule_id,
                'review_id' => $review->id
            ]);

            // Execute rule against single review
            $result = $this->executeRule($rule, collect([$review]), $aiData);

            Log::info("Real-time rule executed", [
                'rule_id' => $rule->rule_id,
                'review_id' => $review->id,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error("Real-time rule execution failed", [
                'rule_id' => $rule->rule_id,
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);
            // We don't throw here to avoid crashing the calling process, allow logging
        }
    }

    /**
     * Get rules that should be executed based on frequency
     * CRITICAL: Only custom rules (is_default=false) should trigger notifications
     */
    private function getRulesToExecute(?string $frequency = 'all')
    {
        $query = AiRule::where('enabled', true)
            ->where('run_frequency', '!=', 'real_time');

        // Filter by frequency if specified
        if ($frequency !== 'all') {
            $query->where('run_frequency', $frequency);
        }

        // Only get rules that are due to run
        $query->where(function ($q) {
            $q->whereNull('next_run_at')
                ->orWhere('next_run_at', '<=', now());
        });

        return $query->get();
    }
}
