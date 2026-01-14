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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Rating',
                            'type' => 'rating',
                            'operator' => 'less_than',
                            'value' => 3
                        ]
                    ]
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ],
                        [
                            'source' => 'Staff',
                            'type' => 'staff_mention',
                            'operator' => 'equals',
                            'value' => null // Any staff mention
                        ]
                    ]
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
                'ai_when_it_triggers' => 'Triggers when AI detects negative sentiment specifically mentioning staff or service.'
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Comment',
                            'type' => 'keyword',
                            'operator' => 'contains',
                            'value' => 'clean'
                        ],
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ]
                    ]
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
                'ai_when_it_triggers' => 'Triggers when customers mention words like "clean" with negative sentiment in their reviews.'
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ]
                    ],
                    'repeat_occurrence' => [
                        'count' => 3,
                        'within_days' => 7
                    ]
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Rating',
                            'type' => 'rating',
                            'operator' => 'greater_than',
                            'value' => 3
                        ],
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ]
                    ]
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Comment',
                            'type' => 'keyword',
                            'operator' => 'contains',
                            'value' => 'wait'
                        ],
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ]
                    ]
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
                'ai_when_it_triggers' => 'Triggers whenever customers mention "wait" with negative sentiment.'
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Rating',
                            'type' => 'rating',
                            'operator' => 'less_than',
                            'value' => 3
                        ]
                    ],
                    'peak_period_only' => true
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
                'ai_when_it_triggers' => 'Triggers if the rating is low during designated peak hours.'
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Staff',
                            'type' => 'staff_mention',
                            'operator' => 'equals',
                            'value' => null
                        ],
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'positive'
                        ]
                    ]
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
                'ai_when_it_triggers' => 'Triggers when a review mentions a staff category with a positive sentiment.'
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
                    'logic' => 'AND',
                    'conditions' => [
                        [
                            'source' => 'Comment',
                            'type' => 'keyword',
                            'operator' => 'contains',
                            'value' => 'price'
                        ],
                        [
                            'source' => 'Comment',
                            'type' => 'sentiment',
                            'operator' => 'equals',
                            'value' => 'negative'
                        ]
                    ]
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
                'ai_when_it_triggers' => 'Triggers when negative mentions of price or value are detected.'
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
