<?php
// app/Models/AiRule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="AiRule",
 *     type="object",
 *     title="AI Rule",
 *     description="AI Rule model for automated review analysis and actions",
 *     required={"id", "rule_id", "rule_name", "category", "priority", "enabled", "conditions", "actions"},
 *     @OA\Property(property="id", type="integer", example=1, description="Primary key"),
 *     @OA\Property(property="rule_id", type="string", example="custom_abc123", description="Unique rule identifier"),
 *     @OA\Property(property="rule_name", type="string", example="Low Rating Alert", description="Human-readable rule name"),
 *     @OA\Property(property="description", type="string", example="Alert managers when rating is below threshold", description="Rule description"),
 *     @OA\Property(property="scope", type="string", enum={"business", "system"}, example="business", description="Rule scope"),
 *     @OA\Property(property="business_id", type="integer", example=1, description="Business ID (null for system rules)"),
 *     @OA\Property(
 *         property="category",
 *         type="string",
 *         enum={"sentiment", "staff", "area", "rating_mismatch", "trend", "quality"},
 *         example="sentiment",
 *         description="Rule category"
 *     ),
 *  *     @OA\Property(
 *         property="priority",
 *         type="string",
 *         enum={"critical", "high", "medium", "low"},
 *         example="high",
 *         description="Priority level"
 *     ),
 *     @OA\Property(property="enabled", type="boolean", example=true, description="Whether rule is active"),
 *     @OA\Property(
 *         property="conditions",
 *         type="array",
 *         description="Array of conditions that must be met",
 *         @OA\Items(
 *             type="object",
 *             required={"source", "type", "operator", "value"},
 *             @OA\Property(property="source", type="string", enum={"Comment", "Rating", "Staff", "Area", "Emotion", "Trend"}, description="Data source"),
 *             @OA\Property(property="type", type="string", enum={"sentiment", "rating", "keyword", "staff_mention", "area_mention", "emotion", "service_type", "frequency", "trend_direction"}, description="Condition type"),
 *             @OA\Property(property="operator", type="string", enum={"equals", "contains", "greater_than", "less_than", "between", "not_equals", "starts_with", "ends_with", "regex"}, description="Comparison operator"),
 *             @OA\Property(property="value", type="string", description="Value to compare against"),
 *             @OA\Property(property="logic", type="string", enum={"AND", "OR"}, nullable=true, description="Logic operator to combine with next condition")
 *         )
 *     ),
 *     @OA\Property(
 *         property="actions",
 *         type="array",
 *         description="Actions to execute when rule matches",
 *         @OA\Items(type="string", enum={"flag_review", "notify_manager", "recommend_coaching", "link_staff", "escalate", "notify_slack", "notify_email"})
 *     ),
 *     @OA\Property(property="multi_tag_detection", type="boolean", example=false, description="Detect multiple matching tags"),
 *     @OA\Property(property="trigger_only_on_first_occurrence", type="boolean", example=false, description="Only trigger on first occurrence"),
 *    @OA\Property(property="run_frequency", type="string", enum={"real_time", "hourly", "daily", "weekly"}, example="daily", description="How often to run"),
 *     @OA\Property(property="cooldown_days", type="integer", example=7, description="Days to wait before triggering again"),
 *     @OA\Property(property="deduplication_scope", type="string", enum={"review", "staff", "category", "branch", "staff_category"}, example="staff", description="Deduplication scope"),
 *     @OA\Property(property="applies_to", type="string", enum={"new_reviews_only", "all_reviews"}, example="new_reviews_only", description="Which reviews to apply to"),
 *     @OA\Property(property="branch_ids", type="array", @OA\Items(type="integer"), nullable=true, description="Specific branches (null = all branches)"),
 *     @OA\Property(property="created_by", type="integer", example=1, description="User who created the rule"),
 *     @OA\Property(property="version", type="integer", example=1, description="Rule version number"),
 *     @OA\Property(property="ai_explanation_title", type="string", nullable=true, description="AI-generated short explanation"),
 *     @OA\Property(property="ai_plain_explanation", type="string", nullable=true, description="AI-generated detailed explanation"),
 *     @OA\Property(property="ai_why_it_matters", type="string", nullable=true, description="AI-generated business impact"),
 *     @OA\Property(property="ai_when_it_triggers", type="string", nullable=true, description="AI-generated trigger conditions"),
 *     @OA\Property(property="ai_generated_at", type="string", format="date-time", nullable=true, description="When AI explanations were generated"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-14T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-14T10:00:00Z")
 * )
 */
class AiRule extends Model
{
    protected $fillable = [
        'rule_id',
        'rule_name',
        'description',
        'key_name',
        'value',
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
        // UI fields
        'multi_tag_detection',
        'trigger_only_on_first_occurrence',
        'applies_to',
        'precision_rate',
        'lifetime_triggers',
        'branch_ids',
        // Default rule enforcement
        'is_default'
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
        'branch_ids' => 'array',
        'explanation_generated_at' => 'datetime',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'ai_generated_at' => 'datetime',
        'is_default' => 'boolean'
    ];

    /**
     * Boot the model and add protection logic
     */
    protected static function booted()
    {
        // Prevent changing is_default from true to false
        static::updating(function ($rule) {
            if ($rule->isDirty('is_default') && $rule->getOriginal('is_default') === true) {
                throw new \Exception('Cannot change is_default from true to false for default rules');
            }

            // Prevent changing rule_id for default rules
            if ($rule->is_default && $rule->isDirty('rule_id')) {
                throw new \Exception('Cannot change rule_id for default rules');
            }

            // Prevent changing rule_name for default rules
            if ($rule->is_default && $rule->isDirty('rule_name')) {
                throw new \Exception('Cannot change rule_name for default rules');
            }
        });

        // Prevent deletion of default rules
        static::deleting(function ($rule) {
            if ($rule->is_default) {
                throw new \Exception('Cannot delete default rules');
            }
        });
    }

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
     * Scope to get enabled rules only
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope to get default rules only
     */
    public function scopeDefaultRules($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get custom rules only (user-created, for notifications)
     */
    public function scopeCustomRules($query)
    {
        return $query->where('is_default', false);
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
