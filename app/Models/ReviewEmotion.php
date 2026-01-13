<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewEmotion extends Model
{
    protected $fillable = [
        'review_id',
        'emotion',
        'intensity_score',
        'intensity_level',
        'confidence',
        'keywords_matched'
    ];

    protected $casts = [
        'intensity_score' => 'float',
        'confidence' => 'float',
        'keywords_matched' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the review that owns this emotion
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ReviewNew::class, 'review_id');
    }

    // ==================== SCOPES ====================

    /**
     * Scope to get emotions by type
     */
    public function scopeByEmotion($query, string $emotion)
    {
        return $query->where('emotion', $emotion);
    }

    /**
     * Scope to get high intensity emotions only
     */
    public function scopeHighIntensity($query)
    {
        return $query->where('intensity_level', 'high');
    }

    /**
     * Scope to get emotions within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if emotion is negative
     */
    public function isNegative(): bool
    {
        return in_array($this->emotion, ['anger', 'frustration', 'disappointment']);
    }

    /**
     * Check if emotion is positive
     */
    public function isPositive(): bool
    {
        return in_array($this->emotion, ['joy', 'satisfaction']);
    }

    /**
     * Get emotion icon for UI
     */
    public function getEmotionIcon(): string
    {
        return match ($this->emotion) {
            'joy' => '😊',
            'anger' => '😠',
            'frustration' => '😤',
            'satisfaction' => '😌',
            'disappointment' => '😞',
            default => '😐'
        };
    }
}
