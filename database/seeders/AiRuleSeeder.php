<?php

namespace Database\Seeders;

use App\Models\AiRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean start - remove all existing rules to align with UI mockups
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        AiRule::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $rules = [
            [
                'rule_id' => 'SENTIMENT_ANALYSIS',
                'rule_name' => 'Sentiment Analysis',
                'description' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'scope' => 'system',
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'precision_rate' => 96.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'positive'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'neutral'],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'sentiment_categorized'],
                'ai_explanation_title' => 'Sentiment Analysis',
                'ai_plain_explanation' => 'Automatically categorize feedback into positive, neutral, or negative sentiment buckets.',
                'ai_why_it_matters' => 'Understanding the general mood of customer feedback helps in broad quality assessment.',
                'ai_when_it_triggers' => 'Triggers on every review to assign a sentiment category.'
            ],
            [
                'rule_id' => 'EMOTION_INTENSITY',
                'rule_name' => 'Emotion Intensity Detection',
                'description' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'scope' => 'system',
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'precision_rate' => 91.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Emotion', 'type' => 'intensity', 'operator' => 'greater_than', 'value' => 0.7]
                    ]
                ],
                'actions' => ['tag' => 'high_emotion_intensity'],
                'ai_explanation_title' => 'Emotion Intensity Detection',
                'ai_plain_explanation' => 'Identify the strength of emotions like joy, frustration, or anger within text reviews.',
                'ai_why_it_matters' => 'High intensity emotions often signal critical issues or exceptional praise.',
                'ai_when_it_triggers' => 'Triggers when strong emotions are detected in review text.'
            ],
            [
                'rule_id' => 'RATING_COMMENT_MISMATCH',
                'rule_name' => 'Rating & Comment Mismatch',
                'description' => 'Detect when a high numerical rating is paired with a negative written review.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'high',
                'enabled' => true,
                'precision_rate' => 88.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'greater_than', 'value' => 3],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'mismatch_detected', 'alert' => true],
                'ai_explanation_title' => 'Rating & Comment Mismatch',
                'ai_plain_explanation' => 'Detect when a high numerical rating is paired with a negative written review.',
                'ai_why_it_matters' => 'Identifies hidden dissatisfaction where customers are polite with stars but critical in text.',
                'ai_when_it_triggers' => 'Triggers when stars are high (4+) but comment sentiment is negative.'
            ],
            [
                'rule_id' => 'CATEGORY_ISSUE_DETECTION',
                'rule_name' => 'Category Issue Detection',
                'description' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
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
                'ai_explanation_title' => 'Category Issue Detection',
                'ai_plain_explanation' => 'Sort feedback into predefined categories like Pricing, Quality, or Delivery.',
                'ai_why_it_matters' => 'Enables granular analysis of specific business problems.',
                'ai_when_it_triggers' => 'Triggers when keywords related to pricing, quality, or delivery are found.'
            ],
            [
                'rule_id' => 'SERVICE_TYPE_DETECTION',
                'rule_name' => 'Service Type Detection',
                'description' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'scope' => 'system',
                'category' => 'area',
                'priority' => 'low',
                'enabled' => true,
                'precision_rate' => 93.00,
                'conditions' => [
                    'logic' => 'OR',
                    'conditions' => [
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'installation'],
                        ['source' => 'Comment', 'type' => 'keyword', 'operator' => 'contains', 'value' => 'maintenance']
                    ]
                ],
                'actions' => ['tag' => 'service_type_identified'],
                'ai_explanation_title' => 'Service Type Detection',
                'ai_plain_explanation' => 'Identify the specific type of service mentioned (e.g., Installation vs Maintenance).',
                'ai_why_it_matters' => 'Helps routes feedback to the correct department.',
                'ai_when_it_triggers' => 'Triggers when specific service terms are mentioned.'
            ],
            [
                'rule_id' => 'BUSINESS_AREA_DETECTION',
                'rule_name' => 'Business Area Detection',
                'description' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'scope' => 'system',
                'category' => 'area',
                'priority' => 'medium',
                'enabled' => true,
                'precision_rate' => 89.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Area', 'type' => 'area_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'area_identified'],
                'ai_explanation_title' => 'Business Area Detection',
                'ai_plain_explanation' => 'Pinpoint which business unit or physical location the feedback refers to.',
                'ai_why_it_matters' => 'Identifies exactly where in the business an issue or win occurred.',
                'ai_when_it_triggers' => 'Triggers when AI detects a physical area mention in the review.'
            ],
            [
                'rule_id' => 'STAFF_MENTION_DETECTION',
                'rule_name' => 'Staff Mention Detection',
                'description' => 'Extract employee names or roles from comments to track individual mentions.',
                'scope' => 'system',
                'category' => 'staff',
                'priority' => 'low',
                'enabled' => true,
                'precision_rate' => 95.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true]
                    ]
                ],
                'actions' => ['tag' => 'staff_identified'],
                'ai_explanation_title' => 'Staff Mention Detection',
                'ai_plain_explanation' => 'Extract employee names or roles from comments to track individual mentions.',
                'ai_why_it_matters' => 'Enables staff-level performance tracking and recognition.',
                'ai_when_it_triggers' => 'Triggers when a staff member or role is explicitly mentioned.'
            ],
            [
                'rule_id' => 'STAFF_PERFORMANCE_RISK',
                'rule_name' => 'Staff Performance Risk',
                'description' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'scope' => 'system',
                'category' => 'staff',
                'priority' => 'critical',
                'enabled' => true,
                'precision_rate' => 82.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Staff', 'type' => 'staff_mention', 'operator' => 'exists', 'value' => true],
                        ['source' => 'Comment', 'type' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']
                    ]
                ],
                'actions' => ['tag' => 'staff_risk_flagged', 'alert' => true],
                'ai_explanation_title' => 'Staff Performance Risk',
                'ai_plain_explanation' => 'Flag recurring negative mentions or behavioral issues linked to specific personnel.',
                'ai_why_it_matters' => 'Protects brand reputation by identifying problematic staff behavior early.',
                'ai_when_it_triggers' => 'Triggers when staff are mentioned in a negative context.'
            ],
            [
                'rule_id' => 'FLAG_AND_ALERT',
                'rule_name' => 'Flag and Alert Detection',
                'description' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'scope' => 'system',
                'category' => 'quality',
                'priority' => 'critical',
                'enabled' => true,
                'precision_rate' => 97.00,
                'conditions' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['source' => 'Rating', 'type' => 'rating', 'operator' => 'less_than', 'value' => 2]
                    ]
                ],
                'actions' => ['alert' => true, 'notification' => 'emergency'],
                'ai_explanation_title' => 'Flag and Alert Detection',
                'ai_plain_explanation' => 'Trigger immediate notifications for critical keywords or severe dissatisfaction.',
                'ai_why_it_matters' => 'Ensures immediate action on the most sensitive customer issues.',
                'ai_when_it_triggers' => 'Triggers on very low ratings or critical emergency keywords.'
            ]
        ];

        foreach ($rules as $ruleData) {
            AiRule::updateOrCreate(
                ['rule_id' => $ruleData['rule_id']],
                [
                    'rule_name' => $ruleData['rule_name'],
                    'description' => $ruleData['description'],
                    'scope' => $ruleData['scope'],
                    'category' => $ruleData['category'],
                    'priority' => $ruleData['priority'],
                    'enabled' => $ruleData['enabled'],
                    'precision_rate' => $ruleData['precision_rate'],
                    'conditions' => json_encode($ruleData['conditions']),
                    'actions' => json_encode($ruleData['actions']),
                    'ai_explanation_title' => $ruleData['ai_explanation_title'],
                    'ai_plain_explanation' => $ruleData['ai_plain_explanation'],
                    'ai_why_it_matters' => $ruleData['ai_why_it_matters'],
                    'ai_when_it_triggers' => $ruleData['ai_when_it_triggers'],
                    'ai_generated_at' => now(),
                    'created_by' => 'system_seeder',
                    'version' => 1
                ]
            );
        }
    }
}
