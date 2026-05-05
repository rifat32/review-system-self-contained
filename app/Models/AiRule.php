<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRule extends Model
{
    protected $fillable = [
        'rule_id', 'rule_name', 'description', 'key_name', 'value', 'scope',
        'business_type', 'business_id', 'category', 'priority', 'enabled',
        'conditions', 'actions', 'explainability', 'short_explanation',
        'detailed_explanation', 'why_it_matters', 'explanation_generated_at',
        'created_by', 'version', 'run_frequency', 'cooldown_days',
        'deduplication_scope', 'last_run_at', 'next_run_at', 'multi_tag_detection',
        'trigger_only_on_first_occurrence', 'applies_to', 'precision_rate',
        'lifetime_triggers', 'branch_ids', 'is_default', 'recipient'
    ];

    protected $casts = [
        'conditions' => 'array', 'actions' => 'array', 'explainability' => 'array',
        'enabled' => 'boolean', 'multi_tag_detection' => 'boolean',
        'trigger_only_on_first_occurrence' => 'boolean', 'precision_rate' => 'float',
        'lifetime_triggers' => 'integer', 'branch_ids' => 'array',
        'explanation_generated_at' => 'datetime', 'last_run_at' => 'datetime',
        'next_run_at' => 'datetime', 'is_default' => 'boolean'
    ];

    public static $bypassDefaultGuard = false;

    protected static function booted()
    {
        static::updating(function ($rule) {
            if (self::$bypassDefaultGuard) return;
            if ($rule->isDirty('is_default') && $rule->getOriginal('is_default') === true) {
                throw new \Exception('Cannot change is_default from true to false for default rules');
            }
            if ($rule->is_default && ($rule->isDirty('rule_id') || $rule->isDirty('rule_name'))) {
                throw new \Exception('Cannot modify rule_id or rule_name for default rules');
            }
        });

        static::deleting(function ($rule) {
            if (self::$bypassDefaultGuard) return;
            if ($rule->is_default) throw new \Exception('Cannot delete default rules');
        });
    }

    public function business(): BelongsTo { return $this->belongsTo(Business::class); }
}
