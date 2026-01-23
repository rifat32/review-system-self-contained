<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    public function run()
    {
        $modules = [
            [
                'name' => 'language_translation',
                'description' => 'Automatically translate reviews to the business language.',
            ],
            [
                'name' => 'sentiment_analysis',
                'description' => 'Analyze the sentiment of a review (positive, negative, neutral).',
            ],
            [
                'name' => 'emotion_detection',
                'description' => 'Detect the primary emotion in a review.',
            ],
            [
                'name' => 'abuse_detection',
                'description' => 'Identify abusive or inappropriate content.',
            ],
            [
                'name' => 'explainability',
                'description' => 'Provide a text explanation for the AI analysis.',
            ],
            [
                'name' => 'category_analysis',
                'description' => 'Categorize reviews into topics like service, atmosphere, or food.',
            ],
            [
                'name' => 'staff_intelligence',
                'description' => 'Identify staff mentions and analyze staff performance.',
            ],
            [
                'name' => 'service_unit_intelligence',
                'description' => 'Identify mentions of specific service units.',
            ],
            [
                'name' => 'business_recommendations',
                'description' => 'Generate actionable recommendations based on reviews.',
            ],
            [
                'name' => 'alerts',
                'description' => 'Send alerts for critical or highly negative reviews.',
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(['name' => $module['name']], $module);
        }
    }
}
