<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiRule;

class DynamicAiRulesSeeder extends Seeder
{
    public function run()
    {
        $rules = [
            // ==================== RECOMMENDATION TEMPLATES ====================
            [
                'category' => 'branch_comparison_templates',
                'rule_id' => 'BRANCH_COMP_TEMPLATE_1',
                'rule_name' => 'Default Branch Comparison',
                'description' => 'Default template for branch comparison insights',
                'value' => json_encode([
                    'overview' => 'Branch comparison insights generated.',
                    'findings' => []
                ]),
                'conditions' => ['default' => true],
                'priority' => 'medium',
            ],
            // ... (Add other templates as needed or leave separate if they use key_name)

            // ==================== COMMON TOPIC STOPWORDS ====================
            [
                'category' => 'common_topics',
                'rule_id' => 'STOP_WORDS_EN',
                'rule_name' => 'English Stop Words',
                'description' => 'List of stop words to exclude from topic extraction',
                'value' => json_encode(['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those', 'it', 'its', 'they', 'their', 'them', 'very', 'good', 'bad', 'great', 'nice']),
                'conditions' => ['lang' => 'en'],
                'priority' => 'low',
            ],

            // ==================== PERFORMANCE LABELS ====================
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_EXCELLENT',
                'rule_name' => 'Performance Label: Excellent',
                'description' => 'Label for excellent performance',
                'value' => 'Excellent',
                'conditions' => ['min' => 4.5, 'max' => 5.0],
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_VERY_GOOD',
                'rule_name' => 'Performance Label: Very Good',
                'description' => 'Label for very good performance',
                'value' => 'Very Good',
                'conditions' => ['min' => 4.0, 'max' => 4.49],
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_GOOD',
                'rule_name' => 'Performance Label: Good',
                'description' => 'Label for good performance',
                'value' => 'Good',
                'conditions' => ['min' => 3.5, 'max' => 3.99],
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_AVERAGE',
                'rule_name' => 'Performance Label: Average',
                'description' => 'Label for average performance',
                'value' => 'Average',
                'conditions' => ['min' => 3.0, 'max' => 3.49],
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_BELOW_AVG',
                'rule_name' => 'Performance Label: Below Average',
                'description' => 'Label for below average performance',
                'value' => 'Below Average',
                'conditions' => ['min' => 2.0, 'max' => 2.99],
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_POOR',
                'rule_name' => 'Performance Label: Poor',
                'description' => 'Label for poor performance',
                'value' => 'Poor',
                'conditions' => ['min' => 0.0, 'max' => 1.99],
                'priority' => 'medium',
            ],
        ];

        // Recommendation Templates with specific matching criteria
        $templateConfigs = [
            'FOOD_TEMP_IMPROVEMENT' => [
                'template' => 'Review kitchen processes to ensure {{main_category}} is served at correct temperature. Issue mentioned {{count}} times.',
                'conditions' => ['category_match' => ['main_category' => 'Food', 'match_type' => 'contains']]
            ],
            'SERVICE_SPEED_IMPROVEMENT' => [
                'template' => 'Optimize {{main_category}} flow during peak hours. {{count}} complaints about wait times.',
                'conditions' => ['category_match' => ['main_category' => 'Service', 'match_type' => 'contains']]
            ],
            'STAFF_TRAINING_GENERAL' => [
                'template' => 'Provide {{sub_category}} training for staff. Mentioned in {{count}} reviews.',
                'conditions' => ['category_match' => ['main_category' => 'Staff', 'match_type' => 'contains']]
            ],
            'CLEANLINESS_PROTOCOL' => [
                'template' => 'Implement regular {{main_category}} checks. {{count}} mentions of cleanliness issues.',
                'conditions' => ['category_match' => ['main_category' => 'Cleanliness', 'match_type' => 'contains']]
            ],
            'HOTEL_NOISE_CONTROL' => [
                'template' => 'Address noise concerns in rooms. {{count}} guests mentioned noise issues.',
                'conditions' => ['category_match' => ['main_category' => 'Hotel', 'match_type' => 'contains']]
            ],
            'RESTAURANT_FOOD_QUALITY' => [
                'template' => 'Review {{sub_category}} preparation standards. {{count}} quality complaints.',
                'conditions' => ['category_match' => ['main_category' => 'Food', 'match_type' => 'contains']]
            ],
            'CLINIC_WAIT_TIME' => [
                'template' => 'Reduce wait times for appointments. {{count}} patients reported long waits.',
                'conditions' => ['category_match' => ['main_category' => 'Wait', 'match_type' => 'contains']]
            ],
            'GENERIC_MAIN_CATEGORY' => [
                'template' => 'Address {{main_category}} issues reported by {{count}} customers.',
                'conditions' => ['category_match' => ['match_type' => 'contains']] // Matches anything if key exists
            ],
            'GENERIC_STAFF_ISSUE' => [
                'template' => 'Provide staff training for {{sub_category}} issues mentioned {{count}} times.',
                'conditions' => ['category_match' => ['main_category' => 'Staff', 'match_type' => 'contains']]
            ],
            'GENERIC_PROCESS_ISSUE' => [
                'template' => 'Review {{main_category}} processes. Issue mentioned {{count}} times.',
                'conditions' => ['category_match' => ['match_type' => 'contains']]
            ],
            'GENERAL' => [
                'template' => 'Improve {{main_category}} based on {{count}} customer mentions.',
                'conditions' => [] // Fallback
            ]
        ];

        foreach ($templateConfigs as $key => $config) {
            $rules[] = [
                'category' => 'recommendation_templates',
                'rule_id' => 'REC_TEMPLATE_' . $key,
                'rule_name' => 'Recommendation Template: ' . $key,
                'description' => 'Template for ' . str_replace('_', ' ', strtolower($key)),
                'key_name' => $key,
                'value' => $config['template'],
                'conditions' => $config['conditions'],
                'actions' => [
                    'suggest_action' => [
                        'type' => 'business',
                        'template_id' => $key
                    ]
                ],
                'priority' => 'low',
            ];
        }

        foreach ($rules as $rule) {
            AiRule::updateOrCreate(
                ['rule_id' => $rule['rule_id']],
                array_merge([
                    'actions' => [],
                    'conditions' => []
                ], $rule, [
                    'scope' => 'system',
                    'enabled' => true,
                    'created_by' => 'system_seeder',
                    'version' => 1
                ])
            );
        }
    }
}
