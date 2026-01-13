<?php

namespace App\Models\Traits;

/**
 * Trait for AiRuleTrigger suppression control
 * 
 * ADD THIS TO AiRuleTrigger.php:
 * use App\Models\Traits\AiRuleTriggerSuppressionTrait;
 * 
 * Then in the class:
 * use AiRuleTriggerSuppressionTrait;
 */
trait AiRuleTriggerSuppressionTrait
{
    /**
     * Scope to get active (non-suppressed) triggers
     */
    public function scopeActive($query)
    {
        return $query->where('was_suppressed', false);
    }

    /**
     * Scope to get suppressed triggers
     */
    public function scopeSuppressed($query)
    {
        return $query->where('was_suppressed', true);
    }

    /**
     * Scope to get triggers by dedup key
     */
    public function scopeByDedupKey($query, string $dedupKey)
    {
        return $query->where('dedup_key', $dedupKey);
    }

    /**
     * Get the staff member associated with this trigger
     */
    public function staff()
    {
        return $this->belongsTo(\App\Models\User::class, 'staff_id');
    }

    /**
     * Check if trigger was suppressed
     */
    public function isSuppressed(): bool
    {
        return $this->was_suppressed === true;
    }

    /**
     * Get human-readable suppression reason
     */
    public function getSuppressionReason(): ?string
    {
        return $this->suppressed_reason;
    }
}
