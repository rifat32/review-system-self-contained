<?php

namespace App\Services\Rule;

use App\Models\AiRule;
use Illuminate\Support\Facades\Log;

class DefaultRuleSeederService
{
    /**
     * List of required default rules
     */
    private static array $requiredRules = [
        'SENTIMENT_ANALYSIS',
        'EMOTION_INTENSITY',
        'RATING_COMMENT_MISMATCH',
        'CATEGORY_ISSUE_DETECTION',
        'SERVICE_TYPE_DETECTION',
        'BUSINESS_AREA_DETECTION',
        'STAFF_MENTION_DETECTION',
        'STAFF_PERFORMANCE_RISK',
        'FLAG_AND_ALERT'
    ];


    /**
     * Recreate a specific default rule for a business
     */
    public static function recreateRule(string $ruleKey, int $businessId): AiRule
    {
        $ruleData = self::getRuleDefinition($ruleKey, $businessId);

        if (!$ruleData) {
            throw new \Exception("Unknown rule key: {$ruleKey}");
        }

        return AiRule::updateOrCreate(
            ['rule_id' => $ruleData['rule_id']],
            array_merge($ruleData, [
                'created_by' => 'system_auto_recovery',
                'version' => 1
            ])
        );
    }

    /**
     * Get rule definition for a specific rule key
     */
    private static function getRuleDefinition(string $ruleKey, int $businessId): ?array
    {
        $definitions = self::getAllRuleDefinitions($businessId);
        return $definitions[$ruleKey] ?? null;
    }

    /**
     * Get all 9 default rule definitions for a business
     */
    private static function getAllRuleDefinitions(int $businessId): array
    {
        return [
            'SENTIMENT_ANALYSIS' => [
                'rule_id' => 'SENTIMENT_ANALYSIS.' . $businessId,
                'rule_name' => 'Sentiment Analysis',
                'description' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 96.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'positive'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'neutral'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'sentiment_assigned'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Sentiment Analysis',
                'ai_plain_explanation' => 'Automatically categorize feedback into positive, neutral, or negative sentiment bucket.',
                'ai_why_it_matters' => 'Understanding overall customer satisfaction at scale.',
                'ai_when_it_triggers' => 'Triggers on every review to assign a sentiment category.'
            ],
            'EMOTION_INTENSITY' => [
                'rule_id' => 'EMOTION_INTENSITY.' . $businessId,
                'rule_name' => 'Emotion Intensity Detection',
                'description' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 91.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Emotion', 'type' => 'emotion_intensity', 'operator' => 'greater_than', 'value' => config('ai.insights.opportunities.seeding.emotion_intensity') ?? 0.7]
                    ]
                ],
                'actions' => ['tag' => 'high_emotion'],
                'ai_explanation_title' => 'Emotion Intensity Detection',
                'ai_plain_explanation' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'ai_why_it_matters' => 'High intensity emotions often signal critical issues or exceptional praise.',
                'ai_when_it_triggers' => 'Triggers when strong emotions are detected in review text.'
            ],
            'RATING_COMMENT_MISMATCH' => [
                'rule_id' => 'RATING_COMMENT_MISMATCH.' . $businessId,
                'rule_name' => 'Rating & Comment Mismatch',
                'description' => 'Detect when a high numerical rating is paired with a negative written review.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'trend',
                'priority' => 'high',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 88.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'greater_than', 'value' => config('ai.insights.opportunities.seeding.mismatch_rating_high') ?? 3],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'mismatch_detected', 'alert' => true],
                'ai_explanation_title' => 'Rating & Comment Mismatch',
                'ai_plain_explanation' => 'Detect when a high numerical rating is paired with a negative written review.',
                'ai_why_it_matters' => 'Identifies hidden dissatisfaction where customers are polite with stars but critical in text.',
                'ai_when_it_triggers' => 'Triggers when stars are high (4+) but comment sentiment is negative.'
            ],
            // Add remaining 6 rules with similar structure...
            'CATEGORY_ISSUE_DETECTION' => [
                'rule_id' => 'CATEGORY_ISSUE_DETECTION.' . $businessId,
                'rule_name' => 'Category Issue Detection',
                'description' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 85.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'price'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'quality'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'delivery']
                    ]
                ],
                'actions' => ['tag' => 'category_assigned'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Category Issue Detection',
                'ai_plain_explanation' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'ai_why_it_matters' => 'Enables granular analysis of specific business problems.',
                'ai_when_it_triggers' => 'Triggers when keywords related to pricing, quality, or delivery are found.'
            ],
            'SERVICE_TYPE_DETECTION' => [
                'rule_id' => 'SERVICE_TYPE_DETECTION.' . $businessId,
                'rule_name' => 'Service Type Detection',
                'description' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'area',
                'priority' => 'low',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 93.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'installation'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'maintenance']
                    ]
                ],
                'actions' => ['tag' => 'service_type_identified'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Service Type Detection',
                'ai_plain_explanation' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'ai_why_it_matters' => 'Helps routes feedback to the correct department.',
                'ai_when_it_triggers' => 'Triggers when specific service terms are mentioned.'
            ],
            'BUSINESS_AREA_DETECTION' => [
                'rule_id' => 'BUSINESS_AREA_DETECTION.' . $businessId,
                'rule_name' => 'Business Area Detection',
                'description' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'area',
                'priority' => 'medium',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 89.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Area', 'type' => 'area_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'area_identified'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Business Area Detection',
                'ai_plain_explanation' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'ai_why_it_matters' => 'Identifies exactly where in the business an issue or win occurred.',
                'ai_when_it_triggers' => 'Triggers when AI detects a physical area mention in the review.'
            ],
            'STAFF_MENTION_DETECTION' => [
                'rule_id' => 'STAFF_MENTION_DETECTION.' . $businessId,
                'rule_name' => 'Staff Mention Detection',
                'description' => 'Extract employee names or roles from comments to track individual mentions.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'staff',
                'priority' => 'low',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 95.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'staff_identified'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Staff Mention Detection',
                'ai_plain_explanation' => 'Extract employee names or roles from comments to track individual mentions.',
                'ai_why_it_matters' => 'Enables staff-level performance tracking and recognition.',
                'ai_when_it_triggers' => 'Triggers when a staff member or role is explicitly mentioned.'
            ],
            'STAFF_PERFORMANCE_RISK' => [
                'rule_id' => 'STAFF_PERFORMANCE_RISK.' . $businessId,
                'rule_name' => 'Staff Performance Risk',
                'description' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'staff',
                'priority' => 'critical',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 82.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'staff_risk_flagged', 'alert' => true],
                'cooldown_days' => 1,
                'deduplication_scope' => 'staff',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Staff Performance Risk',
                'ai_plain_explanation' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'ai_why_it_matters' => 'Protects brand reputation by identifying problematic staff behavior early.',
                'ai_when_it_triggers' => 'Triggers when staff are mentioned in a negative context.'
            ],
            'FLAG_AND_ALERT' => [
                'rule_id' => 'FLAG_AND_ALERT.' . $businessId,
                'rule_name' => 'Flag and Alert Detection',
                'description' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'scope' => 'business',
                'business_id' => $businessId,
                'category' => 'quality',
                'priority' => 'critical',
                'enabled' => true,
                'is_default' => true,
                'precision_rate' => 97.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'less_than', 'value' => config('ai.insights.opportunities.seeding.mismatch_rating_low') ?? 2]
                    ]
                ],
                'actions' => ['alert' => true, 'notification' => 'emergency'],
                'cooldown_days' => 0,
                'deduplication_scope' => 'review',
                'run_frequency' => 'real_time',
                'ai_explanation_title' => 'Flag and Alert Detection',
                'ai_plain_explanation' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'ai_why_it_matters' => 'Ensures immediate action on the most sensitive customer issues.',
                'ai_when_it_triggers' => 'Triggers on very low ratings or critical emergency keywords.'
            ]
        ];
    }

    /**
     * Get the list of required default rules
     */
    public static function getRequiredRuleKeys(): array
    {
        return self::$requiredRules;
    }
}
