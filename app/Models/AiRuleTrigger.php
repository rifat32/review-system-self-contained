<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRuleTrigger extends Model
{
    protected $fillable = [
        'rule_id',
        'review_id',
        'business_id',
        'confidence_score',
        'matched_conditions',
        'actions_triggered',
        'outcome',
        'verified_by',
        'verified_at',
        'verification_notes',
        // Deduplication & suppression fields
        'dedup_key',
        'was_suppressed',
        'suppressed_reason',
        'staff_id',
        'category'
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'matched_conditions' => 'array',
        'actions_triggered' => 'array',
        'verified_at' => 'datetime',
        'was_suppressed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the rule that triggered
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AiRule::class, 'rule_id', 'rule_id');
    }

    /**
     * Get the review that triggered this rule
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ReviewNew::class, 'review_id');
    }

    /**
     * Get the business this trigger belongs to
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who verified this trigger
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get pending verification triggers
     */
    public function scopePendingVerification($query)
    {
        return $query->where('outcome', 'pending');
    }

    /**
     * Scope to get verified triggers
     */
    public function scopeVerified($query)
    {
        return $query->whereIn('outcome', ['true_positive', 'false_positive']);
    }

    /**
     * Scope to get triggers for specific rule
     */
    public function scopeForRule($query, string $ruleId)
    {
        return $query->where('rule_id', $ruleId);
    }

    /**
     * Scope to get triggers within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get high confidence triggers
     */
    public function scopeHighConfidence($query, float $threshold = 80.0)
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Verify trigger as true positive
     */
    public function verifyAsTruePositive(int $userId, ?string $notes = null): void
    {
        $this->update([
            'outcome' => 'true_positive',
            'verified_by' => $userId,
            'verified_at' => now(),
            'verification_notes' => $notes
        ]);
    }

    /**
     * Verify trigger as false positive
     */
    public function verifyAsFalsePositive(int $userId, ?string $notes = null): void
    {
        $this->update([
            'outcome' => 'false_positive',
            'verified_by' => $userId,
            'verified_at' => now(),
            'verification_notes' => $notes
        ]);
    }

    /**
     * Check if trigger is verified
     */
    public function isVerified(): bool
    {
        return $this->outcome !== 'pending';
    }

    /**
     * Get outcome color for UI
     */
    public function getOutcomeColor(): string
    {
        return match ($this->outcome) {
            'true_positive' => 'green',
            'false_positive' => 'red',
            'pending' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Get formatted actions list
     */
    public function getFormattedActions(): array
    {
        $actionLabels = [
            'flag_review' => 'Flag Review',
            'notify_manager' => 'Notify Manager',
            'recommend_coaching' => 'Recommend Coaching',
            'link_staff' => 'Link to Staff Profile',
            'escalate' => 'Escalate Issue',
            'notify_slack' => 'Slack Notification',
            'notify_email' => 'Email Notification'
        ];

        return array_map(function ($action) use ($actionLabels) {
            return $actionLabels[$action] ?? $action;
        }, $this->actions_triggered ?? []);
    }
}
