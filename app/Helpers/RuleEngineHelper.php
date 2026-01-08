<?php
namespace App\Helpers;

use App\Models\{AiRule, ReviewNew, Business, InsightRecord, AiRuleEvaluation, AiInsightsAggregate, Alert};


class RuleEngineHelper
{
    // PUBLIC METHODS
    
    public static function matchRulesToInsight(InsightRecord $insight): array
    {
        $rules = self::getApplicableRules($insight);
        $matchedRules = [];
        
        foreach ($rules as $rule) {
            if (self::ruleMatchesInsight($rule, $insight)) {
                $matchedRules[] = [
                    'rule' => $rule,
                    'confidence' => self::calculateRuleConfidence($rule, $insight),
                    'severity' => self::determineSeverity($rule, $insight),
                    'explanation' => self::generateRuleExplanation($rule, $insight)
                ];
            }
        }
        
        return $matchedRules;
    }
    
    public static function generateRecommendation(AiRule $rule, InsightRecord $insight): array
    {
        $actions = json_decode($rule->actions, true);
        
        if (!isset($actions['suggest_action'])) return [];
        
        $template = self::getRecommendationTemplate($actions['suggest_action']['template_id'] ?? 'GENERAL');
        $filledTemplate = self::fillTemplate($template, $insight->business_id, $rule, $insight);
        
        return [
            'type' => $actions['suggest_action']['type'] ?? 'business',
            'text' => $filledTemplate,
            'priority' => $rule->priority,
            'confidence' => self::calculateRuleConfidence($rule, $insight),
            'evidence' => [
                'mentions' => $insight->mentions_count,
                'severity' => $insight->severity,
                'trend' => $insight->trend,
                'review_ids' => array_slice($insight->review_ids ?? [], 0, 10)
            ],
            'explainability' => self::generateExplainability($rule, $insight)
        ];
    }
    
    public static function evaluateReviewRules(ReviewNew $review, array $openaiData): array
    {
        $rules = self::getApplicableRulesForReview($review->business_id);
        $results = [];
        
        foreach ($rules as $rule) {
            if (self::ruleMatchesReview($rule, $review, $openaiData)) {
                $results[] = self::createRuleEvaluation($rule, $review, $openaiData);
                self::triggerRuleActions($rule, $review, $openaiData);
            }
        }
        
        // Auto-create rules for new patterns
        AutoRuleCreator::checkAndCreateRules($review, $openaiData);
        
        return $results;
    }
    
    // PRIVATE METHODS
    
    private static function getApplicableRules(InsightRecord $insight)
    {
        $business = Business::find($insight->business_id);
        
        return AiRule::where(function($query) use ($insight, $business) {
            $query->where('scope', 'system')
                  ->orWhere(function($q) use ($business) {
                      if ($business?->type) {
                          $q->where('scope', 'business_type')
                            ->where('business_type', $business->type);
                      }
                  })
                  ->orWhere(function($q) use ($insight) {
                      $q->where('scope', 'business')
                        ->where('business_id', $insight->business_id);
                  });
        })->where('enabled', true)->get();
    }
    
    private static function ruleMatchesInsight(AiRule $rule, InsightRecord $insight): bool
    {
        $conditions = json_decode($rule->conditions, true);
        
        // Dynamic category matching
        if (isset($conditions['category_match'])) {
            if (!self::matchesCategoryPattern($conditions['category_match'], $insight)) {
                return false;
            }
        }
        
        // Staff condition matching
        if (isset($conditions['staff'])) {
            if (!self::matchesStaffCondition($conditions['staff'], $insight)) {
                return false;
            }
        }
        
        // Check all other conditions
        return self::checkAllConditions($conditions, $insight);
    }
    
    private static function matchesCategoryPattern(array $condition, InsightRecord $insight): bool
    {
        $mainCat = $condition['main_category'] ?? null;
        $subCat = $condition['sub_category'] ?? null;
        
        // 1. Exact match (Food|Temperature)
        if ($mainCat && $subCat) {
            return $insight->main_category === $mainCat && $insight->sub_category === $subCat;
        }
        
        // 2. Category-only match (Food|any)
        if ($mainCat && !$subCat) {
            return $insight->main_category === $mainCat;
        }
        
        // 3. Wildcard matching
        if (isset($condition['match_type'])) {
            return match($condition['match_type']) {
                'contains' => str_contains($insight->main_category, $condition['contains'] ?? ''),
                'starts_with' => str_starts_with($insight->main_category, $condition['starts_with'] ?? ''),
                'ends_with' => str_ends_with($insight->main_category, $condition['ends_with'] ?? ''),
                'regex' => preg_match($condition['pattern'], $insight->main_category),
                default => false
            };
        }
        
        return false;
    }
    
    private static function matchesStaffCondition(array $condition, InsightRecord $insight): bool
    {
        // Generic staff/process detection
        if (isset($condition['generic_type'])) {
            return match($condition['generic_type']) {
                'staff' => $insight->staff_blame_detected === true,
                'process' => $insight->staff_blame_detected === false,
                'any' => true,
                default => false
            };
        }
        
        // Specific staff conditions
        if (isset($condition['blame_detected']) && $condition['blame_detected'] !== $insight->staff_blame_detected) {
            return false;
        }
        
        return true;
    }
    
    private static function checkAllConditions(array $conditions, InsightRecord $insight): bool
    {
        // Mentions count
        if (isset($conditions['repeat_occurrence']['count'])) {
            if ($insight->mentions_count < $conditions['repeat_occurrence']['count']) {
                return false;
            }
        }
        
        // Severity
        if (isset($conditions['severity'])) {
            $severity = $conditions['severity'];
            if (is_array($severity) && !in_array($insight->severity, $severity)) {
                return false;
            } elseif (is_string($severity) && $insight->severity !== $severity) {
                return false;
            }
        }
        
        // Confidence level
        if (isset($conditions['confidence_min'])) {
            $confScore = ConfidenceCalculator::calculateInsightConfidence($insight)['score'];
            if ($confScore < $conditions['confidence_min']) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function calculateRuleConfidence(AiRule $rule, InsightRecord $insight): string
    {
        // Use ConfidenceCalculator for consistency
        $confidenceData = ConfidenceCalculator::calculateInsightConfidence($insight);
        
        // Rule-specific confidence adjustment
        $adjustment = match($rule->priority) {
            'critical' => 20,
            'high' => 10,
            default => 0
        };
        
        $adjustedScore = min(100, $confidenceData['score'] + $adjustment);
        
        return match(true) {
            $adjustedScore >= 80 => 'high',
            $adjustedScore >= 60 => 'medium',
            default => 'low'
        };
    }
    
    private static function generateExplainability(AiRule $rule, InsightRecord $insight): array
    {
        $conditions = json_decode($rule->conditions, true);
        
        return [
            'rule_used' => $rule->rule_name,
            'rule_id' => $rule->rule_id,
            'conditions_met' => [
                'category' => "{$insight->main_category} - {$insight->sub_category}",
                'mentions' => $insight->mentions_count,
                'severity' => $insight->severity,
                'trend' => $insight->trend,
                'staff_related' => $insight->staff_blame_detected
            ],
            'confidence_factors' => ConfidenceCalculator::calculateInsightConfidence($insight)['factors'],
            'time_period' => [
                'start' => $insight->time_window_start?->format('Y-m-d'),
                'end' => $insight->time_window_end?->format('Y-m-d')
            ]
        ];
    }
    
    private static function fillTemplate(string $template, int $businessId, AiRule $rule, InsightRecord $insight): string
    {
        $business = Business::find($businessId);
        $conditions = json_decode($rule->conditions, true);
        
        $replacements = [
            '{{main_category}}' => $insight->main_category,
            '{{sub_category}}' => $insight->sub_category,
            '{{count}}' => $insight->mentions_count,
            '{{business_name}}' => $business->name ?? 'your business',
            '{{severity}}' => $insight->severity,
            '{{trend}}' => $insight->trend,
            '{{period}}' => $insight->time_window_start?->format('M d') . ' - ' . $insight->time_window_end?->format('M d')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    private static function getRecommendationTemplate(string $templateId): string
    {
        $templates = [
            // System templates
            'FOOD_TEMP_IMPROVEMENT' => 'Review kitchen processes to ensure {{main_category}} is served at correct temperature. Issue mentioned {{count}} times.',
            'SERVICE_SPEED_IMPROVEMENT' => 'Optimize {{main_category}} flow during peak hours. {{count}} complaints about wait times.',
            'STAFF_TRAINING_GENERAL' => 'Provide {{sub_category}} training for staff. Mentioned in {{count}} reviews.',
            'CLEANLINESS_PROTOCOL' => 'Implement regular {{main_category}} checks. {{count}} mentions of cleanliness issues.',
            
            // Business type templates
            'HOTEL_NOISE_CONTROL' => 'Address noise concerns in rooms. {{count}} guests mentioned noise issues.',
            'RESTAURANT_FOOD_QUALITY' => 'Review {{sub_category}} preparation standards. {{count}} quality complaints.',
            'CLINIC_WAIT_TIME' => 'Reduce wait times for appointments. {{count}} patients reported long waits.',
            
            // Generic templates for unknown categories
            'GENERIC_MAIN_CATEGORY' => 'Address {{main_category}} issues reported by {{count}} customers.',
            'GENERIC_STAFF_ISSUE' => 'Provide staff training for {{sub_category}} issues mentioned {{count}} times.',
            'GENERIC_PROCESS_ISSUE' => 'Review {{main_category}} processes. Issue mentioned {{count}} times.',
            
            // Fallback
            'GENERAL' => 'Improve {{main_category}} based on {{count}} customer mentions.'
        ];
        
        return $templates[$templateId] ?? $templates['GENERAL'];
    }
    
    // REVIEW-LEVEL RULE MATCHING (for real-time evaluation)
    
    private static function getApplicableRulesForReview(int $businessId)
    {
        $business = Business::find($businessId);
        
        return AiRule::where(function($query) use ($businessId, $business) {
            $query->where('scope', 'system')
                  ->orWhere(function($q) use ($business) {
                      if ($business?->type) {
                          $q->where('scope', 'business_type')
                            ->where('business_type', $business->type);
                      }
                  })
                  ->orWhere(function($q) use ($businessId) {
                      $q->where('scope', 'business')
                        ->where('business_id', $businessId);
                  });
        })->where('enabled', true)->where('category', '!=', 'trend')->get(); // Exclude trend rules for single reviews
    }
    
    private static function ruleMatchesReview(AiRule $rule, ReviewNew $review, array $openaiData): bool
    {
        $conditions = json_decode($rule->conditions, true);
        
        // Check rating conditions
        if (isset($conditions['rating'])) {
            if (!self::checkRatingCondition($conditions['rating'], $review->rating ?? 0)) {
                return false;
            }
        }
        
        // Check sentiment from OpenAI
        if (isset($conditions['sentiment'])) {
            $sentiment = $openaiData['overall_sentiment'] ?? 'neutral';
            if ($sentiment !== $conditions['sentiment']) {
                return false;
            }
        }
        
        // Check category match in review
        if (isset($conditions['category_match'])) {
            if (!self::reviewContainsCategory($openaiData, $conditions['category_match'])) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function reviewContainsCategory(array $openaiData, array $condition): bool
    {
        $categories = $openaiData['category_analysis'] ?? [];
        
        foreach ($categories as $category) {
            $mainCat = $category['main_category'] ?? '';
            $subCat = $category['sub_category'] ?? '';
            
            if (isset($condition['main_category']) && isset($condition['sub_category'])) {
                if ($mainCat === $condition['main_category'] && $subCat === $condition['sub_category']) {
                    return true;
                }
            } elseif (isset($condition['main_category'])) {
                if ($mainCat === $condition['main_category']) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private static function createRuleEvaluation(AiRule $rule, ReviewNew $review, array $openaiData): array
    {
        return [
            'rule_id' => $rule->rule_id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'triggered' => true,
            'severity' => $rule->priority,
            'explanation' => "Rule '{$rule->rule_name}' triggered for review #{$review->id}",
            'confidence' => 0.8, // Default confidence for review-level matches
            'evaluation_data' => [
                'matched_conditions' => json_decode($rule->conditions, true),
                'review_categories' => $openaiData['category_analysis'] ?? [],
                'timestamp' => now()->toISOString()
            ]
        ];
    }
    
    private static function triggerRuleActions(AiRule $rule, ReviewNew $review, array $openaiData): void
    {
        $actions = json_decode($rule->actions, true);
        
        // Trigger alert
        if ($actions['trigger_alert'] ?? false) {
            Alert::create([
                'business_id' => $review->business_id,
                'type' => 'rule_triggered',
                'title' => $rule->rule_name,
                'message' => "Rule triggered for review #{$review->id}",
                'severity' => $actions['severity'] ?? 'medium',
                'metadata' => [
                    'rule_id' => $rule->rule_id,
                    'review_id' => $review->id,
                    'categories' => $openaiData['category_analysis'] ?? []
                ]
            ]);
        }
        
        // Increment trend counter
        if ($actions['count_towards_trend'] ?? false) {
            $conditions = json_decode($rule->conditions, true);
            $category = $conditions['category_match']['main_category'] ?? 'general';
            
            AiInsightsAggregate::updateOrCreate(
                [
                    'business_id' => $review->business_id,
                    'insight_type' => 'trend',
                    'key_name' => $category
                ],
                [
                    'count' => \DB::raw('count + 1'),
                    'last_seen' => now(),
                    'first_seen' => \DB::raw('COALESCE(first_seen, NOW())'),
                    'metadata' => [
                        'rule_id' => $rule->rule_id,
                        'last_review_id' => $review->id
                    ]
                ]
            );
        }
    }
    
    private static function checkRatingCondition(array $condition, float $rating): bool
    {
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? 0;
        
        return match($operator) {
            'lt' => $rating < $value,
            'lte' => $rating <= $value,
            'gt' => $rating > $value,
            'gte' => $rating >= $value,
            'eq' => $rating == $value,
            'between' => $rating >= ($condition['min'] ?? 0) && $rating <= ($condition['max'] ?? 5),
            default => false
        };
    }
    
    private static function generateRuleExplanation(AiRule $rule, InsightRecord $insight): string
    {
        $conditions = json_decode($rule->conditions, true);
        
        $parts = [];
        if (isset($conditions['category_match'])) {
            $parts[] = "Category: {$insight->main_category}";
            if ($insight->sub_category) {
                $parts[] = "Sub-category: {$insight->sub_category}";
            }
        }
        if (isset($conditions['repeat_occurrence']['count'])) {
            $parts[] = "Mentions: {$insight->mentions_count} (threshold: {$conditions['repeat_occurrence']['count']})";
        }
        if ($insight->staff_blame_detected) {
            $parts[] = "Staff-related issue";
        }
        
        return implode(', ', $parts);
    }
}