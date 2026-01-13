<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRuleMetric extends Model
{
    protected $fillable = [
        'rule_id',
        'lifetime_triggers',
        'true_positives',
        'false_positives',
        'pending_verification',
        'precision_rate',
        'reviews_flagged',
        'coaching_actions',
        'escalations',
        'notifications_sent',
        'last_triggered_at'
    ];

    protected $casts = [
        'precision_rate' => 'float',
        'last_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the rule these metrics belong to
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AiRule::class, 'rule_id', 'rule_id');
    }

    // ==================== HELPER METHODS ====================

    /**
     * Increment lifetime triggers
     */
    public function incrementTriggers(): void
    {
        $this->increment('lifetime_triggers');
        $this->increment('pending_verification');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Record action execution
     */
    public function recordAction(string $action): void
    {
        $actionField = match ($action) {
            'flag_review' => 'reviews_flagged',
            'recommend_coaching' => 'coaching_actions',
            'escalate' => 'escalations',
            'notify_manager', 'notify_slack', 'notify_email' => 'notifications_sent',
            default => null
        };

        if ($actionField) {
            $this->increment($actionField);
        }
    }

    /**
     * Record verification outcome
     */
    public function recordVerification(string $outcome): void
    {
        if ($outcome === 'true_positive') {
            $this->increment('true_positives');
        } elseif ($outcome === 'false_positive') {
            $this->increment('false_positives');
        }

        if ($this->pending_verification > 0) {
            $this->decrement('pending_verification');
        }

        $this->recalculatePrecisionRate();
    }

    /**
     * Recalculate precision rate based on verified outcomes
     */
    public function recalculatePrecisionRate(): void
    {
        $total = $this->true_positives + $this->false_positives;

        if ($total > 0) {
            $this->precision_rate = ($this->true_positives / $total) * 100;
            $this->save();
        }
    }

    /**
     * Get formatted precision rate
     */
    public function getFormattedPrecisionRate(): string
    {
        if ($this->precision_rate === null) {
            return 'N/A';
        }

        return number_format($this->precision_rate, 1) . '%';
    }

    /**
     * Get performance grade
     */
    public function getPerformanceGrade(): string
    {
        if ($this->precision_rate === null) {
            return 'ungraded';
        }

        return match (true) {
            $this->precision_rate >= 90 => 'excellent',
            $this->precision_rate >= 80 => 'good',
            $this->precision_rate >= 70 => 'fair',
            default => 'poor'
        };
    }

    /**
     * Get total impact count
     */
    public function getTotalImpact(): int
    {
        return $this->reviews_flagged
            + $this->coaching_actions
            + $this->escalations;
    }

    /**
     * Check if rule has enough data for reliable metrics
     */
    public function hasReliableMetrics(int $minimumTriggers = 10): bool
    {
        return $this->lifetime_triggers >= $minimumTriggers
            && ($this->true_positives + $this->false_positives) >= ($minimumTriggers / 2);
    }

    /**
     * Get metrics summary
     */
    public function getSummary(): array
    {
        return [
            'lifetime_triggers' => $this->lifetime_triggers,
            'precision_rate' => $this->getFormattedPrecisionRate(),
            'performance_grade' => $this->getPerformanceGrade(),
            'total_impact' => $this->getTotalImpact(),
            'pending_verification' => $this->pending_verification,
            'verified_count' => $this->true_positives + $this->false_positives,
            'is_reliable' => $this->hasReliableMetrics(),
            'last_triggered' => $this->last_triggered_at?->diffForHumans()
        ];
    }
}
