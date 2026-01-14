<?php
// app/Services/AIProcessor/RecommendationGeneratorService.php

namespace App\Services\AIProcessor;

use App\Models\InsightRecord;
use App\Models\Recommendation;
use App\Services\Rule\RuleEngineService;
use Carbon\Carbon;

class RecommendationGeneratorService
{
    private RuleEngineService $ruleEngineService;

    public function __construct(RuleEngineService $ruleEngineService)
    {
        $this->ruleEngineService = $ruleEngineService;
    }

    /**
     * Generate recommendations from insights
     */
    public function generateFromInsights(int $businessId, int $days = 30): array
    {
        // Get recent insights
        $insights = InsightRecord::where('business_id', $businessId)
            ->where('time_window_end', '>=', Carbon::now()->subDays($days))
            ->where('mentions_count', '>=', 2) // Business rule
            ->get();

        $recommendations = [];

        foreach ($insights as $insight) {

            // Match rules to insight
            $matchedRules = $this->ruleEngineService->matchRulesToInsight($insight);

            foreach ($matchedRules as $matched) {
                $rule = $matched['rule'];

                // Generate recommendation from rule
                $recData = $this->ruleEngineService->generateRecommendation($rule, $insight);

                if (!empty($recData)) {
                    $recommendation = $this->createRecommendation(
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
        usort($recommendations, function ($a, $b) {
            $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0);
        });

        return array_slice($recommendations, 0, 5);
    }

    /**
     * Create recommendation record
     */
    private function createRecommendation(
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
    public function getDashboardRecommendations(int $businessId, int $limit = 3): array
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
