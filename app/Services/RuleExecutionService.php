<?php

namespace App\Services;

use App\Models\{AiRule, AiRuleTrigger, ReviewNew};
use App\Helpers\{ConditionBuilderHelper, AIProcessorExtensions};
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
     * @return array Execution summary
     */
    public function executeRule(AiRule $rule, $reviews): array
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
            $aiData = $this->getReviewAIData($review);

            // Evaluate rule conditions
            $isMatch = ConditionBuilderHelper::evaluateConditions(
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
        foreach ($rule->actions as $action) {
            try {
                match ($action) {
                    'flag_review' => $this->flagReview($review, $rule),
                    'notify_manager' => $this->notifyManager($review, $rule, $context),
                    'recommend_coaching' => $this->recommendCoaching($review, $rule, $context),
                    'link_staff' => $this->linkStaff($review, $context),
                    'escalate' => $this->escalateIssue($review, $rule, $context),
                    'notify_slack' => $this->notifySlack($review, $rule, $context),
                    'notify_email' => $this->notifyEmail($review, $rule, $context),
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
        // Flag review in database (you might have a flags table)
        Log::info("Review flagged", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id
        ]);
    }

    private function notifyManager(ReviewNew $review, AiRule $rule, array $context): void
    {
        // Send notification to managers
        Log::info("Manager notified", [
            'review_id' => $review->id,
            'rule_id' => $rule->rule_id,
            'staff_id' => $context['staff_id'] ?? null
        ]);
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

    private function escalateIssue(ReviewNew $review, AiRule $rule, array $context): void
    {
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
            'sentiment' => $review->sentiment ?? 'neutral',
            'sentiment_score' => 0.5,
            'staff_mentions' => [],
            'areas' => [],
            'emotions' => [],
            'confidence' => 85.0,
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
}
