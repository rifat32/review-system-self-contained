<?php
// app/Helpers/AutoRuleCreator.php

namespace App\Services\Rule;

use App\Models\{AiRule, ReviewNew, InsightRecord};
use App\Services\Rule\RuleExplanationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AutoRuleCreatorService
{
    /**
     * Check and create rules for new patterns in review
     */
    public static function checkAndCreateRules(ReviewNew $review, array $openaiData): void
    {
        $categories = $openaiData['category_analysis'] ?? [];

        foreach ($categories as $category) {
            $mainCategory = $category['main_category'] ?? null;
            $subCategory = $category['sub_category'] ?? null;
            $severity = $category['severity'] ?? 'medium';

            if (!$mainCategory)
                continue;

            // Check if this category pattern is recurring
            $patternFrequency = self::checkPatternFrequency(
                $review->business_id,
                $mainCategory,
                $subCategory
            );

            // Auto-create rule if pattern appears enough times
            if ($patternFrequency >= self::getThresholdForSeverity($severity)) {
                self::createRuleFromPattern(
                    $review->business_id,
                    $mainCategory,
                    $subCategory,
                    $severity,
                    $patternFrequency
                );
            }
        }
    }

    /**
     * Check if this category pattern appears frequently
     */
    private static function checkPatternFrequency(int $businessId, string $mainCategory, ?string $subCategory): int
    {
        $last30Days = Carbon::now()->subDays(30);

        $query = ReviewNew::where('business_id', $businessId)
            ->where('is_ai_processed', true)
            ->where('created_at', '>=', $last30Days);

        // Count reviews mentioning this category pattern
        $count = $query->where(function ($q) use ($mainCategory, $subCategory) {
            $q->whereJsonContains('openai_raw_response->category_analysis', function ($query) use ($mainCategory, $subCategory) {
                $query->where('main_category', $mainCategory);
                if ($subCategory) {
                    $query->where('sub_category', $subCategory);
                }
            });
        })->count();

        return $count;
    }

    /**
     * Get frequency threshold based on severity
     */
    private static function getThresholdForSeverity(string $severity): int
    {
        return match ($severity) {
            'high' => 2,    // High severity issues: 2 mentions
            'medium' => 3,  // Medium severity: 3 mentions
            'low' => 4,     // Low severity: 4 mentions
            default => 3
        };
    }

    /**
     * Create a new rule from a recurring pattern
     */
    public static function createRuleFromPattern(
        int $businessId,
        string $mainCategory,
        ?string $subCategory,
        string $severity,
        int $frequency
    ): ?AiRule {
        // Check if similar rule already exists
        $existingRule = self::findSimilarRule($businessId, $mainCategory, $subCategory);
        if ($existingRule) {
            return null; // Rule already exists
        }

        // Determine rule parameters based on pattern
        $ruleConfig = self::generateRuleConfig($mainCategory, $subCategory, $severity, $frequency);

        // Generate AI explanations for the rule
        $explanations = self::generateRuleExplanations(
            $businessId,
            $mainCategory,
            $subCategory,
            $severity,
            $frequency,
            $ruleConfig
        );

        // Create the rule
        $rule = AiRule::create([
            'rule_id' => self::generateRuleId($businessId, $mainCategory, $subCategory),
            'rule_name' => self::generateRuleName($mainCategory, $subCategory, $frequency),
            'description' => self::generateRuleDescription($mainCategory, $subCategory, $frequency),
            'scope' => 'business',
            'business_id' => $businessId,
            'category' => self::determineRuleCategory($mainCategory, $subCategory),
            'priority' => self::determinePriority($severity, $frequency),
            'enabled' => true,
            'conditions' => json_encode($ruleConfig['conditions']),
            'actions' => json_encode($ruleConfig['actions']),
            'explainability' => json_encode($ruleConfig['explainability']),
            'short_explanation' => $explanations['short_explanation'],
            'detailed_explanation' => $explanations['detailed_explanation'],
            'why_it_matters' => $explanations['why_it_matters'],
            'explanation_generated_at' => now(),
            'created_by' => 'auto_creator',
            'version' => 1
        ]);

        \Log::info('Auto-created rule with AI explanations', [
            'rule_id' => $rule->rule_id,
            'business_id' => $businessId,
            'pattern' => "$mainCategory|$subCategory",
            'frequency' => $frequency,
            'has_explanations' => $rule->hasExplanations()
        ]);

        return $rule;
    }

    /**
     * Generate AI explanations for the rule
     */
    private static function generateRuleExplanations(
        int $businessId,
        string $mainCategory,
        ?string $subCategory,
        string $severity,
        int $frequency,
        array $ruleConfig
    ): array {
        // Get business type for context
        $business = \App\Models\Business::find($businessId);
        $businessType = $business ? ($business->business_type ?? 'Business') : 'Business';

        // Prepare rule data for explanation generation
        $ruleData = [
            'business_type' => $businessType,
            'rule_name' => self::generateRuleName($mainCategory, $subCategory, $frequency),
            'category' => self::determineRuleCategory($mainCategory, $subCategory),
            'priority' => self::determinePriority($severity, $frequency),
            'conditions' => $ruleConfig['conditions'],
            'actions' => $ruleConfig['actions'],
            'main_category' => $mainCategory,
            'sub_category' => $subCategory,
            'severity' => $severity,
            'frequency' => $frequency
        ];

        // Try to generate AI explanations using OpenAI
        $explanations = RuleExplanationService::generateExplanations($ruleData);

        // Fallback to template-based explanations if AI fails
        if (!$explanations) {
            \Log::warning('OpenAI explanation generation failed, using fallback', [
                'business_id' => $businessId,
                'main_category' => $mainCategory
            ]);

            $explanations = RuleExplanationService::generateFallbackExplanations($ruleData);
        }

        return $explanations;
    }

    /**
     * Find if similar rule already exists
     */
    private static function findSimilarRule(int $businessId, string $mainCategory, ?string $subCategory): ?AiRule
    {
        return AiRule::where('business_id', $businessId)
            ->where('scope', 'business')
            ->where(function ($query) use ($mainCategory, $subCategory) {
                $query->whereJsonContains('conditions->category_match->main_category', $mainCategory);

                if ($subCategory) {
                    $query->whereJsonContains('conditions->category_match->sub_category', $subCategory);
                }
            })
            ->first();
    }

    /**
     * Generate rule configuration
     */
    private static function generateRuleConfig(string $mainCategory, ?string $subCategory, string $severity, int $frequency): array
    {
        $minMentions = max(2, ceil($frequency * 0.7)); // 70% of observed frequency

        $conditions = [
            'category_match' => [
                'main_category' => $mainCategory,
                'match_type' => 'exact'
            ],
            'repeat_occurrence' => [
                'count' => $minMentions,
                'within_days' => 30
            ]
        ];

        if ($subCategory) {
            $conditions['category_match']['sub_category'] = $subCategory;
        }

        // Add severity condition for high/medium severity issues
        if (in_array($severity, ['high', 'medium'])) {
            $conditions['severity'] = $severity;
        }

        $templateId = self::determineTemplateId($mainCategory, $subCategory);

        return [
            'conditions' => $conditions,
            'actions' => [
                'suggest_action' => [
                    'type' => self::determineActionType($mainCategory, $subCategory),
                    'template_id' => $templateId
                ],
                'count_towards_trend' => true,
                'severity' => $severity
            ],
            'explainability' => [
                'show_to_user' => true,
                'reason_template' => "Auto-created based on {{count}} mentions of {{main_category}}",
                'confidence_visible' => true,
                'auto_generated' => true
            ]
        ];
    }

    /**
     * Determine rule category based on content
     */
    private static function determineRuleCategory(string $mainCategory, ?string $subCategory): string
    {
        $staffKeywords = ['staff', 'service', 'attitude', 'rude', 'friendly', 'helpful'];
        $categoryLower = strtolower($mainCategory . ' ' . $subCategory);

        foreach ($staffKeywords as $keyword) {
            if (str_contains($categoryLower, $keyword)) {
                return 'staff';
            }
        }

        if (str_contains($categoryLower, 'clean') || str_contains($categoryLower, 'dirty')) {
            return 'area';
        }

        return 'trend';
    }

    /**
     * Determine action type
     */
    private static function determineActionType(string $mainCategory, ?string $subCategory): string
    {
        $categoryLower = strtolower($mainCategory . ' ' . $subCategory);

        if (
            str_contains($categoryLower, 'staff') ||
            str_contains($categoryLower, 'service') ||
            str_contains($categoryLower, 'attitude')
        ) {
            return 'staff';
        }

        if (
            str_contains($categoryLower, 'clean') ||
            str_contains($categoryLower, 'maintenance') ||
            str_contains($categoryLower, 'facility')
        ) {
            return 'area';
        }

        return 'business';
    }

    /**
     * Determine template ID based on category
     */
    private static function determineTemplateId(string $mainCategory, ?string $subCategory): string
    {
        $mainLower = strtolower($mainCategory);
        $subLower = strtolower($subCategory ?? '');

        // Map categories to template IDs
        $templateMap = [
            'food' => 'FOOD_TEMP_IMPROVEMENT',
            'service' => 'SERVICE_SPEED_IMPROVEMENT',
            'clean' => 'CLEANLINESS_PROTOCOL',
            'staff' => 'STAFF_TRAINING_GENERAL',
            'wait' => 'CLINIC_WAIT_TIME'
        ];

        foreach ($templateMap as $keyword => $templateId) {
            if (str_contains($mainLower, $keyword) || str_contains($subLower, $keyword)) {
                return $templateId;
            }
        }

        // Check for generic patterns
        if (str_contains($mainLower, 'room') || str_contains($subLower, 'noise')) {
            return 'HOTEL_NOISE_CONTROL';
        }

        if (str_contains($mainLower, 'quality') || str_contains($subLower, 'quality')) {
            return 'RESTAURANT_FOOD_QUALITY';
        }

        return 'GENERAL';
    }

    /**
     * Determine priority based on severity and frequency
     */
    private static function determinePriority(string $severity, int $frequency): string
    {
        if ($severity === 'high' && $frequency >= 3) {
            return 'critical';
        }

        if ($severity === 'high' || ($severity === 'medium' && $frequency >= 4)) {
            return 'high';
        }

        if ($severity === 'medium' || $frequency >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generate unique rule ID
     */
    private static function generateRuleId(int $businessId, string $mainCategory, ?string $subCategory): string
    {
        $timestamp = time();
        $mainSlug = substr(preg_replace('/[^A-Z]/', '', strtoupper($mainCategory)), 0, 10);
        $subSlug = $subCategory ? substr(preg_replace('/[^A-Z]/', '', strtoupper($subCategory)), 0, 5) : 'GEN';

        return sprintf('AUTO_%s_%s_%s_%d', $mainSlug, $subSlug, $businessId, $timestamp);
    }

    /**
     * Generate rule name
     */
    private static function generateRuleName(string $mainCategory, ?string $subCategory, int $frequency): string
    {
        $name = "Auto: $mainCategory";
        if ($subCategory) {
            $name .= " - $subCategory";
        }
        $name .= " ($frequency mentions)";

        return $name;
    }

    /**
     * Generate rule description
     */
    private static function generateRuleDescription(string $mainCategory, ?string $subCategory, int $frequency): string
    {
        return sprintf(
            "Automatically created rule for %s issues. Pattern detected %d times in customer feedback.",
            $subCategory ? "$mainCategory: $subCategory" : $mainCategory,
            $frequency
        );
    }

    /**
     * Clean up old auto-created rules that haven't triggered
     */
    public static function cleanupInactiveRules(int $daysInactive = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($daysInactive);

        $deleted = AiRule::where('created_by', 'auto_creator')
            ->where('created_at', '<', $cutoffDate)
            ->whereDoesntHave('evaluations', function ($query) use ($cutoffDate) {
                $query->where('triggered', true)
                    ->where('created_at', '>=', $cutoffDate->subDays(30));
            })
            ->delete();

        return $deleted;
    }

    /**
     * Generate rules from existing insights (for migration or bulk creation)
     */
    public static function createRulesFromExistingInsights(int $businessId, int $minMentions = 3): array
    {
        $insights = InsightRecord::where('business_id', $businessId)
            ->where('mentions_count', '>=', $minMentions)
            ->where('time_window_end', '>=', Carbon::now()->subDays(60))
            ->get();

        $createdRules = [];

        foreach ($insights as $insight) {
            $rule = self::createRuleFromPattern(
                $businessId,
                $insight->main_category,
                $insight->sub_category,
                $insight->severity ?? 'medium',
                $insight->mentions_count
            );

            if ($rule) {
                $createdRules[] = $rule;
            }
        }

        return $createdRules;
    }
}