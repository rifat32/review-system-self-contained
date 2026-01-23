<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServicePlan;
use App\Models\Module;

class ServicePlanSeeder extends Seeder
{
    public function run()
    {
        $basicPlan = ServicePlan::updateOrCreate(
            ['name' => 'Basic'],
            [
                'description' => 'Essential AI features for small businesses.',
                'price' => 29.00,
                'duration_months' => 1,
                'openai_token_limit' => 50000,
                'is_active' => true,
            ]
        );

        $proPlan = ServicePlan::updateOrCreate(
            ['name' => 'Pro'],
            [
                'description' => 'Advanced AI insights for growing businesses.',
                'price' => 99.00,
                'duration_months' => 1,
                'openai_token_limit' => 250000,
                'is_active' => true,
            ]
        );

        $enterprisePlan = ServicePlan::updateOrCreate(
            ['name' => 'Enterprise'],
            [
                'description' => 'Full AI suite for large organizations.',
                'price' => 299.00,
                'duration_months' => 1,
                'openai_token_limit' => -1, // Unlimited
                'is_active' => true,
            ]
        );

        // Assign modules to Basic Plan
        $basicModuleNames = [
            'language_translation',
            'sentiment_analysis',
            'abuse_detection',
            'explainability'
        ];
        $basicModules = Module::whereIn('name', $basicModuleNames)->pluck('id');
        $basicPlan->modules()->sync($basicModules);

        // Assign modules to Pro Plan
        $proModuleNames = array_merge($basicModuleNames, [
            'emotion_detection',
            'category_analysis',
            'staff_intelligence'
        ]);
        $proModules = Module::whereIn('name', $proModuleNames)->pluck('id');
        $proPlan->modules()->sync($proModules);

        // Assign all modules to Enterprise Plan
        $enterpriseModules = Module::pluck('id');
        $enterprisePlan->modules()->sync($enterpriseModules);
    }
}
