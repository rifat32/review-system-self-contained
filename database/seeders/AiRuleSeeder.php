<?php

namespace Database\Seeders;

use App\Models\AiRule;
use Illuminate\Database\Seeder;

class AiRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rules = [
            [
                'rule_id' => 'SYS_LOW_RATING_ALERT',
                'rule_name' => 'Low Rating Alert',
                'description' => 'Triggers when a review has a star rating of 2 or less.',
                'scope' => 'system',
                'category' => 'quality',
                'priority' => 'high',
                'enabled' => true,
                'conditions' => [
                    'rating_below' => 3,
                    'type' => 'rating_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'RECOVER_CUSTOMER'
                    ],
                    'send_notification' => true
                ],
                'ai_explanation_title' => 'Low Rating Alert',
                'ai_plain_explanation' => 'This rule monitors star ratings and identifies customers who left a score of 2 or less.',
                'ai_why_it_matters' => 'Low ratings indicate immediate customer dissatisfaction that requires urgent attention to prevent churn.',
                'ai_when_it_triggers' => 'Triggers automatically whenever a new review with 1 or 2 stars is submitted.'
            ],
            [
                'rule_id' => 'SYS_CRITICAL_STAFF_ISSUE',
                'rule_name' => 'Critical Staff Issue',
                'description' => 'Detects serious complaints about staff behavior or service quality.',
                'scope' => 'system',
                'category' => 'staff',
                'priority' => 'critical',
                'enabled' => true,
                'conditions' => [
                    'category_match' => 'staff',
                    'severity' => 'high',
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'staff',
                        'template_id' => 'STAFF_COACHING'
                    ],
                    'send_notification' => true
                ],
                'ai_explanation_title' => 'Critical Staff Issue',
                'ai_plain_explanation' => 'This rule scans review text for serious complaints directed at staff members or service standards.',
                'ai_why_it_matters' => 'Recurring staff issues can severely damage your brand reputation and service consistency.',
                'ai_when_it_triggers' => 'Triggers when AI detects high-severity negative sentiment specifically mentioning staff or service.'
            ],
            [
                'rule_id' => 'SYS_CLEANLINESS_WARNING',
                'rule_name' => 'Cleanliness Warning',
                'description' => 'Monitors feedback for mentions of hygiene or cleanliness issues.',
                'scope' => 'system',
                'category' => 'area',
                'priority' => 'high',
                'enabled' => true,
                'conditions' => [
                    'category_match' => 'area',
                    'keywords' => ['clean', 'dirty', 'messy', 'hygiene', 'smell'],
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'area',
                        'template_id' => 'HYGIENE_CHECK'
                    ]
                ],
                'ai_explanation_title' => 'Cleanliness Warning',
                'ai_plain_explanation' => 'This rule keeps an eye on mentions of cleanliness, hygiene, or facility maintenance in your facility.',
                'ai_why_it_matters' => 'Cleanliness is a top factor for customer trust, especially in hospitality and service industries.',
                'ai_when_it_triggers' => 'Triggers when customers mention words like "dirty", "unhygienic", or "messy" in their reviews.'
            ],
            [
                'rule_id' => 'SYS_REPEAT_NEGATIVE_FEEDBACK',
                'rule_name' => 'Repeat Negative Feedback',
                'description' => 'Detects recurring complaints about the same issue within a short period.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'high',
                'enabled' => true,
                'conditions' => [
                    'repeat_occurrence' => [
                        'count' => 3,
                        'within_days' => 7
                    ],
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'PROCESS_REVIEW'
                    ]
                ],
                'ai_explanation_title' => 'Repeat Negative Feedback',
                'ai_plain_explanation' => 'This rule identifies when the same complaint appears multiple times in a short window of time.',
                'ai_why_it_matters' => 'A single complaint might be an outlier, but three in a week indicates a systemic issue that needs fixing.',
                'ai_when_it_triggers' => 'Triggers when 3 or more negative reviews mention the same core issue within any 7-day period.'
            ],
            [
                'rule_id' => 'SYS_HIDDEN_DISSATISFACTION',
                'rule_name' => 'Hidden Dissatisfaction',
                'description' => 'Detects reviews with high star ratings but negative text content.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
                'conditions' => [
                    'rating_above' => 3,
                    'text_sentiment' => 'negative',
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'FURTHER_INVESTIGATE'
                    ]
                ],
                'ai_explanation_title' => 'Hidden Dissatisfaction',
                'ai_plain_explanation' => 'This rule finds cases where a customer gives 4 or 5 stars but writes a complaining or negative comment.',
                'ai_why_it_matters' => 'These "polite" complainers often have valid points that get missed if you only look at star ratings.',
                'ai_when_it_triggers' => 'Triggers when star ratings are high (4+) but the AI detects significant negative sentiment in the written feedback.'
            ],
            [
                'rule_id' => 'SYS_SERVICE_SPEED_CONCERN',
                'rule_name' => 'Service Speed Concern',
                'description' => 'Monitors feedback for complaints about long wait times or slow service.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
                'conditions' => [
                    'category_match' => 'service',
                    'keywords' => ['wait', 'slow', 'delay', 'time', 'long'],
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'SPEED_OPTIMIZATION'
                    ]
                ],
                'ai_explanation_title' => 'Service Speed Concern',
                'ai_plain_explanation' => 'This rule tracks how often customers complain about waiting times or the speed of your service.',
                'ai_why_it_matters' => 'Slow service is one of the most common reasons for customers to not return, even if the quality is good.',
                'ai_when_it_triggers' => 'Triggers whenever more than 15% of your reviews in a month mention wait-related frustrations.'
            ],
            [
                'rule_id' => 'SYS_PEAK_HOUR_STRESS',
                'rule_name' => 'Peak Hour Stress',
                'description' => 'Identifies service drops during known busy periods.',
                'scope' => 'system',
                'category' => 'trend',
                'priority' => 'medium',
                'enabled' => true,
                'conditions' => [
                    'peak_period_drop' => true,
                    'type' => 'trend_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'RESOURCE_ALLOCATION'
                    ]
                ],
                'ai_explanation_title' => 'Peak Hour Stress',
                'ai_plain_explanation' => 'This rule analyzes if your service quality drops significantly during your busiest hours (e.g., weekends or lunch).',
                'ai_why_it_matters' => 'If complaints only happen during peak times, it suggests you might be understaffed during those periods.',
                'ai_when_it_triggers' => 'Triggers if the average rating during peak hours is at least 0.5 stars lower than during off-peak hours.'
            ],
            [
                'rule_id' => 'SYS_POSITIVE_STAFF_RECOGNITION',
                'rule_name' => 'Positive Staff Recognition',
                'description' => 'Detects reviews that specifically praise individual staff members.',
                'scope' => 'system',
                'category' => 'staff',
                'priority' => 'low',
                'enabled' => true,
                'conditions' => [
                    'category_match' => 'staff',
                    'sentiment' => 'positive',
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'staff',
                        'template_id' => 'STAFF_REWARD'
                    ]
                ],
                'ai_explanation_title' => 'Positive Staff Recognition',
                'ai_plain_explanation' => 'This rule identifies when customers go out of their way to praise a specific member of your team.',
                'ai_why_it_matters' => 'Recognizing top performers boosts morale and helps you understand what excellent service looks like to your customers.',
                'ai_when_it_triggers' => 'Triggers when a review mentions a staff category with a positive sentiment and high confidence.'
            ],
            [
                'rule_id' => 'SYS_VALUE_FOR_MONEY_TREND',
                'rule_name' => 'Value for Money Trend',
                'description' => 'Monitors perceptions of pricing and value.',
                'scope' => 'system',
                'category' => 'quality',
                'priority' => 'medium',
                'enabled' => true,
                'conditions' => [
                    'category_match' => 'value',
                    'keywords' => ['price', 'expensive', 'worth', 'value', 'cheap'],
                    'type' => 'comment_based'
                ],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => 'PRICING_REVIEW'
                    ]
                ],
                'ai_explanation_title' => 'Value for Money Trend',
                'ai_plain_explanation' => 'This rule tracks whether customers feel they are getting good value for the price they paid.',
                'ai_why_it_matters' => 'If "Value for Money" sentiment drops, you may need to adjust your pricing or improve the perceived quality of your service.',
                'ai_when_it_triggers' => 'Triggers when negative mentions of price or value increase by more than 20% compared to the previous month.'
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
                    'conditions' => $ruleData['conditions'],
                    'actions' => $ruleData['actions'],
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
