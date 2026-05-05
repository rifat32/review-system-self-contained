<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewRuleOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'business_id',
        'is_flagged',
        'is_sentiment_flagged',
        'is_high_emotion',
        'is_mismatch',
        'is_category_detected',
        'is_service_identified',
        'is_area_detected',
        'is_staff_mentioned',
        'is_staff_risk',
        'is_critical_alert',
        'is_custom_rule_triggered',
        'triggered_custom_rule_ids',
        'highest_priority',
        'total_rules_matched',
        'execution_summary'
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'is_sentiment_flagged' => 'boolean',
        'is_high_emotion' => 'boolean',
        'is_mismatch' => 'boolean',
        'is_category_detected' => 'boolean',
        'is_service_identified' => 'boolean',
        'is_area_detected' => 'boolean',
        'is_staff_mentioned' => 'boolean',
        'is_staff_risk' => 'boolean',
        'is_critical_alert' => 'boolean',
        'is_custom_rule_triggered' => 'boolean',
        'triggered_custom_rule_ids' => 'array',
        'execution_summary' => 'array',
        'total_rules_matched' => 'integer'
    ];

    public function review()
    {
        return $this->belongsTo(ReviewNew::class, 'review_id');
    }
}
