<?php
// app/Models/AiRule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRule extends Model
{
    protected $fillable = [
        'rule_id',
        'rule_name',
        'description',
        'scope',
        'business_type',
        'business_id',
        'category',
        'priority',
        'enabled',
        'conditions',
        'actions',
        'explainability',
        'short_explanation',
        'detailed_explanation',
        'why_it_matters',
        'explanation_generated_at',
        'created_by',
        'version',
        // Execution control fields
        'run_frequency',
        'cooldown_days',
        'deduplication_scope',
        'last_run_at',
        'next_run_at',
        // Proposal fields
        'ai_explanation_title',
        'ai_plain_explanation',
        'ai_why_it_matters',
        'ai_when_it_triggers',
        'ai_manager_tip',
        'ai_generated_at',
        // UI fields
        'multi_tag_detection',
        'trigger_only_on_first_occurrence',
        'applies_to',
        'precision_rate',
        'lifetime_triggers'
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'explainability' => 'array',
        'enabled' => 'boolean',
        'multi_tag_detection' => 'boolean',
        'trigger_only_on_first_occurrence' => 'boolean',
        'precision_rate' => 'float',
        'lifetime_triggers' => 'integer',
        'explanation_generated_at' => 'datetime',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'ai_generated_at' => 'datetime'
    ];

    /**
     * Get the business that owns the rule
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get evaluations for this rule
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(RuleEvaluation::class, 'rule_id', 'rule_id');
    }

    /**
     * Get recommendations generated from this rule
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /**
     * Check if rule has complete explanations
     */
    public function hasExplanations(): bool
    {
        return !empty($this->short_explanation)
            && !empty($this->detailed_explanation)
            && !empty($this->why_it_matters);
    }

    /**
     * Check if explanations are outdated
     */
    public function explanationsOutdated(): bool
    {
        if (!$this->hasExplanations()) {
            return true;
        }

        // Check if rule was updated after explanations were generated
        if (
            $this->explanation_generated_at &&
            $this->updated_at > $this->explanation_generated_at
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get formatted explanation for display
     */
    public function getFormattedExplanation(): array
    {
        return [
            'short' => $this->short_explanation ?? 'No explanation available',
            'detailed' => $this->detailed_explanation ?? 'No detailed explanation available',
            'why' => $this->why_it_matters ?? 'Business impact explanation not available',
            'generated_at' => $this->explanation_generated_at?->diffForHumans(),
            'is_complete' => $this->hasExplanations(),
            'is_outdated' => $this->explanationsOutdated()
        ];
    }

    /**
     * Scope to get rules without explanations
     */
    public function scopeWithoutExplanations($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('short_explanation')
                ->orWhereNull('detailed_explanation')
                ->orWhereNull('why_it_matters');
        });
    }

    /**
     * Scope to get rules with outdated explanations
     */
    public function scopeWithOutdatedExplanations($query)
    {
        return $query->whereNotNull('short_explanation')
            ->whereNotNull('explanation_generated_at')
            ->whereColumn('updated_at', '>', 'explanation_generated_at');
    }

    /**
     * Scope to get enabled rules only
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to get rules by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get rules by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get rules for specific business
     */
    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->orWhere('scope', 'system');
        });
    }

    /**
     * Get human-readable priority label
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Unknown'
        };
    }

    /**
     * Get priority color for UI
     */
    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray'
        };
    }

    /**
     * Get category icon
     */
    public function getCategoryIcon(): string
    {
        return match ($this->category) {
            'staff' => '👤',
            'area' => '📍',
            'trend' => '📈',
            'quality' => '⭐',
            default => '📋'
        };
    }
}
