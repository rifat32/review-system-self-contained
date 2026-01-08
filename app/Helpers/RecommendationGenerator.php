<?php
// app/Helpers/RecommendationGenerator.php

namespace App\Helpers;

use App\Models\InsightRecord;
use App\Models\Recommendation;
use Carbon\Carbon;

class RecommendationGenerator
{
    /**
     * Generate recommendations from insights
     */
    public static function generateFromInsights(int $businessId, int $days = 30): array
    {
        // Get recent insights
        $insights = InsightRecord::where('business_id', $businessId)
            ->where('time_window_end', '>=', Carbon::now()->subDays($days))
            ->where('mentions_count', '>=', 2) // Business rule
            ->get();
        
        $recommendations = [];
        
        foreach ($insights as $insight) {
            // Match rules to insight
            $matchedRules = RuleEngineHelper::matchRulesToInsight($insight);
            
            foreach ($matchedRules as $matched) {
                $rule = $matched['rule'];
                
                // Generate recommendation from rule
                $recData = RuleEngineHelper::generateRecommendation($rule, $insight);
                
                if (!empty($recData)) {
                    $recommendation = self::createRecommendation(
                        $businessId,
                        $insight,
                        $rule,
                        $recData
                    );
                    
                    if ($recommendation) {
                        $recommendations[] = $recommendation;
                    }
                }
            }
        }
        
        // Limit to top 5 recommendations by priority
        usort($recommendations, function($a, $b) {
            $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0);
        });
        
        return array_slice($recommendations, 0, 5);
    }
    
    /**
     * Create recommendation record
     */
    private static function createRecommendation(
        int $businessId,
        InsightRecord $insight,
        $rule,
        array $recData
    ): ?array {
        try {
            // Save to database
            $recommendation = Recommendation::create([
                'business_id' => $businessId,
                'insight_id' => $insight->id,
                'rule_id' => $rule->id,
                'type' => $recData['type'],
                'text' => $recData['text'],
                'confidence' => $recData['confidence'],
                'priority' => $recData['priority'],
                'evidence' => json_encode($recData['evidence'])
            ]);
            
            return [
                'id' => $recommendation->id,
                'type' => $recData['type'],
                'text' => $recData['text'],
                'confidence' => $recData['confidence'],
                'priority' => $recData['priority'],
                'evidence' => $recData['evidence']
            ];
            
        } catch (\Exception $e) {
            \Log::error('Failed to create recommendation', [
                'business_id' => $businessId,
                'insight_id' => $insight->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get actionable recommendations for dashboard
     */
    public static function getDashboardRecommendations(int $businessId, int $limit = 3): array
    {
        $recommendations = Recommendation::where('business_id', $businessId)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
        
        return $recommendations->map(function ($rec) {
            return [
                'id' => $rec->id,
                'type' => $rec->type,
                'text' => $rec->text,
                'confidence' => $rec->confidence,
                'priority' => $rec->priority,
                'evidence' => json_decode($rec->evidence, true),
                'created_at' => $rec->created_at->diffForHumans()
            ];
        })->toArray();
    }
}