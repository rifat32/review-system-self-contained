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
                'conditions' => json_encode(['default' => true]),
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
                'conditions' => json_encode(['lang' => 'en']),
                'priority' => 'low',
            ],

            // ==================== PERFORMANCE LABELS ====================
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_EXCELLENT',
                'rule_name' => 'Performance Label: Excellent',
                'description' => 'Label for excellent performance',
                'value' => 'Excellent',
                'conditions' => json_encode(['min' => 4.5, 'max' => 5.0]),
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_VERY_GOOD',
                'rule_name' => 'Performance Label: Very Good',
                'description' => 'Label for very good performance',
                'value' => 'Very Good',
                'conditions' => json_encode(['min' => 4.0, 'max' => 4.49]),
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_GOOD',
                'rule_name' => 'Performance Label: Good',
                'description' => 'Label for good performance',
                'value' => 'Good',
                'conditions' => json_encode(['min' => 3.5, 'max' => 3.99]),
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_AVERAGE',
                'rule_name' => 'Performance Label: Average',
                'description' => 'Label for average performance',
                'value' => 'Average',
                'conditions' => json_encode(['min' => 3.0, 'max' => 3.49]),
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_BELOW_AVG',
                'rule_name' => 'Performance Label: Below Average',
                'description' => 'Label for below average performance',
                'value' => 'Below Average',
                'conditions' => json_encode(['min' => 2.0, 'max' => 2.99]),
                'priority' => 'medium',
            ],
            [
                'category' => 'performance_labels',
                'rule_id' => 'PERF_LABEL_POOR',
                'rule_name' => 'Performance Label: Poor',
                'description' => 'Label for poor performance',
                'value' => 'Poor',
                'conditions' => json_encode(['min' => 0.0, 'max' => 1.99]),
                'priority' => 'medium',
            ],
        ];

        // Recommendation Templates (using key_name for lookup)
        $templates = [
            'FOOD_TEMP_IMPROVEMENT' => 'Review kitchen processes to ensure {{main_category}} is served at correct temperature. Issue mentioned {{count}} times.',
            'SERVICE_SPEED_IMPROVEMENT' => 'Optimize {{main_category}} flow during peak hours. {{count}} complaints about wait times.',
            'STAFF_TRAINING_GENERAL' => 'Provide {{sub_category}} training for staff. Mentioned in {{count}} reviews.',
            'CLEANLINESS_PROTOCOL' => 'Implement regular {{main_category}} checks. {{count}} mentions of cleanliness issues.',
            'HOTEL_NOISE_CONTROL' => 'Address noise concerns in rooms. {{count}} guests mentioned noise issues.',
            'RESTAURANT_FOOD_QUALITY' => 'Review {{sub_category}} preparation standards. {{count}} quality complaints.',
            'CLINIC_WAIT_TIME' => 'Reduce wait times for appointments. {{count}} patients reported long waits.',
            'GENERIC_MAIN_CATEGORY' => 'Address {{main_category}} issues reported by {{count}} customers.',
            'GENERIC_STAFF_ISSUE' => 'Provide staff training for {{sub_category}} issues mentioned {{count}} times.',
            'GENERIC_PROCESS_ISSUE' => 'Review {{main_category}} processes. Issue mentioned {{count}} times.',
            'GENERAL' => 'Improve {{main_category}} based on {{count}} customer mentions.'
        ];

        foreach ($templates as $key => $template) {
            $rules[] = [
                'category' => 'recommendation_templates',
                'rule_id' => 'REC_TEMPLATE_' . $key,
                'rule_name' => 'Recommendation Template: ' . $key,
                'description' => 'Template for ' . str_replace('_', ' ', strtolower($key)),
                'key_name' => $key,
                'value' => $template,
                'conditions' => json_encode([]),
                'priority' => 'low',
            ];
        }

        foreach ($rules as $rule) {
            AiRule::updateOrCreate(
                ['rule_id' => $rule['rule_id']],
                array_merge($rule, [
                    'scope' => 'system',
                    'enabled' => true,
                    'actions' => json_encode([]),
                    'created_by' => 'system_seeder',
                    'version' => 1
                ])
            );
        }
    }
}
