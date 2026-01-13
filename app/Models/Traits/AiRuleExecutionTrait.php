<?php

namespace App\Models\Traits;

/**
 * Trait for AiRule model execution control
 * 
 * ADD THIS TO AiRule.php:
 * use App\Models\Traits\AiRuleExecutionTrait;
 * 
 * Then in the class:
 * use AiRuleExecutionTrait;
 */
trait AiRuleExecutionTrait
{
    /**
     * Boot the trait
     */
    public static function bootAiRuleExecutionTrait()
    {
        // Automatically calculate next_run_at when creating
        static::creating(function ($rule) {
            if ($rule->run_frequency && $rule->run_frequency !== 'real_time') {
                $rule->next_run_at = static::calculateNextRunStatic($rule->run_frequency);
            }
        });
    }

    /**
     * Scope to get scheduled rules (not real-time)
     */
    public function scopeScheduled($query)
    {
        return $query->where('run_frequency', '!=', 'real_time');
    }

    /**
     * Scope to get real-time rules
     */
    public function scopeRealTime($query)
    {
        return $query->where('run_frequency', 'real_time');
    }

    /**
     * Scope to get rules due to run
     */
    public function scopeDueToRun($query)
    {
        return $query->where('enabled', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }

    /**
     * Calculate next run time statically
     */
    protected static function calculateNextRunStatic(string $frequency): ?\Carbon\Carbon
    {
        return match ($frequency) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay()->startOfDay()->addHours(2),
            'weekly' => now()->addWeek()->startOfWeek()->addHours(2),
            default => now()->addDay()
        };
    }
}
