<?php

namespace App\Services\Rule;

use App\Models\{AiRule, AiRuleTrigger, ReviewNew, SupportTicket, Notification, ReviewRuleOutcome};
use Illuminate\Support\Facades\{DB, Log, Mail};

class RuleExecutionService
{
    protected RuleMetricsService $metricsService;

    public function __construct(RuleMetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Primary entry point for rule execution
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
            $aiData = $forcedAiData ?: $this->getReviewAIData($review);

            if (!empty($rule->branch_ids) && !in_array($review->branch_id, $rule->branch_ids)) continue;

            if (ConditionBuilderService::evaluateConditions($rule->conditions, $review, $aiData)) {
                $summary['conditions_matched']++;
                $context = $this->extractContext($review, $aiData, $rule);
                $dedupKey = $this->buildDedupKey($rule, $review, $context);
                
                $cooldownCheck = $this->checkCooldown($dedupKey, $rule->cooldown_days);
                if ($cooldownCheck['active']) {
                    $this->recordSuppression($rule, $review, $dedupKey, $context, $cooldownCheck['reason']);
                    $summary['suppressed_count']++;
                    continue;
                }

                $triggeredCount = $this->executeActions($rule, $review, $aiData, $context);
                if ($triggeredCount > 0) {
                    $summary['actions_triggered']++;
                    $this->recordTrigger($rule, $review, $dedupKey, $context, $aiData);
                }
            }
        }

        $rule->update([
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRun($rule)
        ]);

        return $summary;
    }

    protected function executeActions(AiRule $rule, ReviewNew $review, array $aiData, array $context): int
    {
        $actions = $rule->actions;
        $triggeredCount = 0;
        $actionList = array_is_list($actions) ? $actions : array_keys(array_filter((array) $actions));

        foreach ($actionList as $action) {
            if ($rule->is_default) {
                if (in_array($action, ['notify_manager', 'notify_slack', 'notify_email', 'create_support_ticket', 'notification', 'escalate', 'recommend_coaching'])) continue;
            } else {
                if ($action !== 'notify_email') {
                    Log::warning("Skipped non-email action for custom rule", ['rule_id' => $rule->rule_id, 'action' => $action]);
                    continue;
                }
            }

            try {
                $success = match ($action) {
                    'flag_review', 'is_flagged', 'alert', 'tag' => $this->flagReview($review, $rule),
                    'notify_email' => $this->notifyEmail($review, $rule, $context),
                    default => false
                };
                if ($success) $triggeredCount++;
            } catch (\Exception $e) {
                Log::error("Action execution failed", ['action' => $action, 'error' => $e->getMessage()]);
            }
        }

        if (!$rule->is_default && $triggeredCount > 0) {
            $this->trackCustomRuleTrigger($review, $rule);
        }

        return $triggeredCount;
    }

    private function flagReview(ReviewNew $review, AiRule $rule): bool
    {
        if (!$rule->is_default) return false;
        $column = $this->mapRuleToOutcomeColumn($rule->rule_id);
        if ($column) {
            ReviewRuleOutcome::updateOrCreate(
                ['review_id' => $review->id],
                ['business_id' => $review->business_id, 'is_flagged' => true, $column => true]
            );
            return true;
        }
        return false;
    }

    private function trackCustomRuleTrigger(ReviewNew $review, AiRule $rule): void
    {
        $outcome = ReviewRuleOutcome::firstOrNew(['review_id' => $review->id]);
        $outcome->business_id = $review->business_id;
        $outcome->is_custom_rule_triggered = true;
        $ids = $outcome->triggered_custom_rule_ids ?? [];
        if (!in_array($rule->rule_id, $ids)) $ids[] = $rule->rule_id;
        $outcome->triggered_custom_rule_ids = $ids;
        $outcome->save();
    }

    private function mapRuleToOutcomeColumn(string $ruleId): ?string
    {
        return match (true) {
            str_starts_with($ruleId, 'SENTIMENT_ANALYSIS') => 'is_sentiment_flagged',
            str_starts_with($ruleId, 'EMOTION_INTENSITY') => 'is_high_emotion',
            str_starts_with($ruleId, 'RATING_COMMENT_MISMATCH') => 'is_mismatch',
            str_starts_with($ruleId, 'CATEGORY_ISSUE_DETECTION') => 'is_category_detected',
            str_starts_with($ruleId, 'SERVICE_TYPE_DETECTION') => 'is_service_identified',
            str_starts_with($ruleId, 'BUSINESS_AREA_DETECTION') => 'is_area_detected',
            str_starts_with($ruleId, 'STAFF_MENTION_DETECTION') => 'is_staff_mentioned',
            str_starts_with($ruleId, 'STAFF_PERFORMANCE_RISK') => 'is_staff_risk',
            str_starts_with($ruleId, 'FLAG_AND_ALERT') => 'is_critical_alert',
            default => null
        };
    }

    private function notifyEmail(ReviewNew $review, AiRule $rule, array $context): bool
    {
        $recipient = $rule->recipient;
        if (empty($recipient)) return false;

        try {
            Mail::to($recipient)->queue(new \App\Mail\RuleAlertMail($rule, $review));
            return true;
        } catch (\Exception $e) {
            try {
                Mail::raw("Review #{$review->id} triggered rule: {$rule->rule_name}", function ($message) use ($recipient, $rule) {
                    $message->to($recipient)->subject("AI Rule Alert: {$rule->rule_name}");
                });
                return true;
            } catch (\Exception $e2) {
                Log::error("Email failed completely", ['error' => $e2->getMessage()]);
                return false;
            }
        }
    }

    private function getReviewAIData(ReviewNew $review): array
    {
        return [
            'sentiment' => ['label' => $review->sentiment_label ?: 'neutral', 'score' => $review->sentiment_score ?? 0.5],
            'overall_sentiment' => $review->sentiment_label ?: 'neutral',
            'emotion' => ['primary' => $review->emotion['primary'] ?? 'neutral', 'intensity' => $review->emotion['intensity'] ?? 'low'],
            'staff_mentions' => $review->key_phrases['staff_mentions'] ?? [],
            'areas' => $review->key_phrases['areas_mentioned'] ?? [],
            'key_phrases' => $review->key_phrases ?? [],
            'topics' => $review->topics ?? [],
            'confidence' => $review->ai_confidence ?? 85.0,
            'matched_conditions' => []
        ];
    }

    private function extractContext(ReviewNew $review, array $aiData, AiRule $rule): array
    {
        $context = ['staff_id' => null, 'category' => $rule->category ?? 'general', 'branch_id' => $review->branch_id ?? null];
        if (!empty($aiData['staff_mentions'])) $context['staff_id'] = $aiData['staff_mentions'][0]['id'] ?? null;
        return $context;
    }

    private function buildDedupKey(AiRule $rule, ReviewNew $review, array $context): string
    {
        $ruleId = $rule->rule_id;
        return match ($rule->deduplication_scope) {
            'review' => "rule_{$ruleId}_review_{$review->id}",
            'staff' => "rule_{$ruleId}_staff_" . ($context['staff_id'] ?? 'none'),
            'category' => "rule_{$ruleId}_cat_" . ($context['category'] ?? 'general'),
            'branch' => "rule_{$ruleId}_branch_" . ($review->branch_id ?? 'none'),
            'staff_category' => "rule_{$ruleId}_staff_" . ($context['staff_id'] ?? 'none') . "_cat_" . ($context['category'] ?? 'general'),
            default => "rule_{$ruleId}_review_{$review->id}"
        };
    }

    private function checkCooldown(string $dedupKey, int $cooldownDays): array
    {
        if ($cooldownDays === 0) return ['active' => false, 'reason' => null];
        $last = AiRuleTrigger::where('dedup_key', $dedupKey)->where('was_suppressed', false)->latest()->first();
        if ($last && now()->lt($last->created_at->addDays($cooldownDays))) {
            return ['active' => true, 'reason' => "Cooldown active (last triggered " . $last->created_at->diffForHumans() . ")"];
        }
        return ['active' => false, 'reason' => null];
    }

    private function recordTrigger($rule, $review, $dedupKey, $context, $aiData): void
    {
        $trigger = $this->metricsService->recordTrigger($rule, $review, $aiData['matched_conditions'] ?? [], $rule->actions, 95.0);
        $trigger->update(['dedup_key' => $dedupKey, 'staff_id' => $context['staff_id'] ?? null]);
    }

    private function recordSuppression($rule, $review, $dedupKey, $context, $reason): void
    {
        AiRuleTrigger::create([
            'rule_id' => $rule->rule_id, 'review_id' => $review->id, 'business_id' => $review->business_id,
            'dedup_key' => $dedupKey, 'was_suppressed' => true, 'suppressed_reason' => $reason,
            'staff_id' => $context['staff_id'] ?? null, 'category' => $context['category'] ?? null,
            'confidence_score' => 0, 'matched_conditions' => [], 'actions_triggered' => [], 'outcome' => 'pending'
        ]);
    }

    public function calculateNextRun(AiRule $rule): ?\Carbon\Carbon
    {
        if ($rule->run_frequency === 'real_time') return null;
        return match ($rule->run_frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay()->startOfDay()->addHours(2),
            'weekly' => now()->addWeek()->startOfWeek()->addHours(2),
            default => now()->addDay()
        };
    }


    public function getReviewsForRule(AiRule $rule, ?int $limit = null)
    {
        $limit = $limit ?? 100;
        $query = ReviewNew::where('business_id', $rule->business_id)->globalReviewFilters(0)->orderBy('created_at', 'desc');
        if ($rule->run_frequency === 'real_time') return $query->limit(1)->get();
        if ($rule->last_run_at) $query->where('created_at', '>', $rule->last_run_at);
        else $query->limit($limit);
        return $query->get();
    }

    public function getRulesToExecute(?string $frequency = 'all')
    {
        $query = AiRule::where('enabled', true)->where('run_frequency', '!=', 'real_time');
        if ($frequency !== 'all') $query->where('run_frequency', $frequency);
        $query->where(function ($q) { $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()); });
        return $query->get();
    }
}
