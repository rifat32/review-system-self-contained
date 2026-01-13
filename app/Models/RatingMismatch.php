<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingMismatch extends Model
{
    protected $fillable = [
        'review_id',
        'business_id',
        'mismatch_type',
        'severity',
        'rating',
        'detected_sentiment',
        'sentiment_score',
        'explanation',
        'status',
        'reviewed_by',
        'reviewed_at',
        'reviewer_notes'
    ];

    protected $casts = [
        'rating' => 'float',
        'sentiment_score' => 'float',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the review that has this mismatch
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ReviewNew::class, 'review_id');
    }

    /**
     * Get the business this mismatch belongs to
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who reviewed this mismatch
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get pending mismatches
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get high severity mismatches
     */
    public function scopeHighSeverity($query)
    {
        return $query->where('severity', 'high');
    }

    /**
     * Scope to get mismatches for specific business
     */
    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to get mismatches by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('mismatch_type', $type);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Mark mismatch as reviewed
     */
    public function markAsReviewed(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'reviewed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes
        ]);
    }

    /**
     * Resolve the mismatch
     */
    public function resolve(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'resolved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes
        ]);
    }

    /**
     * Dismiss the mismatch
     */
    public function dismiss(int $userId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'dismissed',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'reviewer_notes' => $notes
        ]);
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            'high' => 'red',
            'medium' => 'orange',
            'low' => 'yellow',
            default => 'gray'
        };
    }

    /**
     * Get human-readable mismatch type
     */
    public function getMismatchTypeLabel(): string
    {
        return match ($this->mismatch_type) {
            'high_rating_negative_comment' => 'High Rating with Negative Comment',
            'low_rating_positive_comment' => 'Low Rating with Positive Comment',
            'neutral_rating_extreme_sentiment' => 'Neutral Rating with Extreme Sentiment',
            default => 'Unknown Mismatch'
        };
    }
}
