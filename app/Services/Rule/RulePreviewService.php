<?php

namespace App\Services\Rule;

use App\Models\ReviewNew;
use App\Models\AiRule;
use App\Services\Rule\ConditionBuilderService;
use Illuminate\Support\Collection;

class RulePreviewService
{
    /**
     * Generate a detailed preview for a rule
     */
    public function generatePreview(array $ruleData, int $businessId): array
    {
        // Get recent reviews for simulation
        $recentReviews = ReviewNew::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->globaReviewlFilters(0, $businessId)
            ->limit(50)
            ->get();

        $previewResults = [];
        $totalMatches = 0;

        foreach ($recentReviews as $review) {
            $aiData = $this->getReviewAIData($review);

            $logicTrace = $this->getLogicTrace($ruleData['conditions'] ?? [], $review, $aiData);
            $isMatch = $logicTrace['is_match'];

            if ($isMatch) {
                $totalMatches++;
                if (count($previewResults) < 5) {
                    $previewResults[] = [
                        'review_id' => $review->id,
                        'text' => $review->comment,
                        'highlighted_text' => $this->highlightMatches($review->comment, $ruleData['conditions'] ?? []),
                        'rating' => $review->rating,
                        'created_at' => $review->created_at->diffForHumans(),
                        'logic_breakdown' => $logicTrace['breakdown']
                    ];
                }
            }
        }

        return [
            'estimated_triggers_past_50' => $totalMatches,
            'precision_estimate' => $this->calculatePrecision($totalMatches, count($ruleData['conditions'] ?? [])),
            'confidence_level' => $this->getConfidenceLevel($totalMatches),
            'sample_matches' => $previewResults,
            'behaviour_simulation' => $this->simulateBehaviour($totalMatches, $ruleData)
        ];
    }

    /**
     * Trace which conditions passed/failed
     */
    private function getLogicTrace(array $conditions, ReviewNew $review, array $aiData): array
    {
        $breakdown = [];
        $isMatch = true;

        foreach ($conditions as $condition) {
            $passed = ConditionBuilderService::evaluateConditions([$condition], $review, $aiData);
            $breakdown[] = [
                'description' => ConditionBuilderService::formatCondition($condition),
                'passed' => $passed
            ];

            if (!$passed) {
                $isMatch = false;
            }
        }

        return [
            'is_match' => $isMatch,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Highlight keywords that triggered the rule
     */
    private function highlightMatches(string $text, array $conditions): string
    {
        $highlighted = $text;
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') === 'keyword' && !empty($condition['value'])) {
                $value = $condition['value'];
                $highlighted = preg_replace("/($value)/i", '<span class="highlight">$1</span>', $highlighted);
            }
        }
        return $highlighted;
    }

    /**
     * Simplified AI data fetch for preview
     */
    private function getReviewAIData(ReviewNew $review): array
    {
        return [
            'sentiment' => $review->sentiment ?? 'neutral',
            'emotions' => [], // In real app, fetch from emotions table
            'staff_mentions' => [],
            'areas' => [],
            'trend_data' => [
                'frequency' => 12.5, // Mocked for preview
                'direction' => 'up'
            ]
        ];
    }

    private function calculatePrecision(int $matches, int $conditionCount): float
    {
        $base = 85.0;
        if ($conditionCount > 3)
            $base += 5.0;
        if ($matches < 2)
            $base -= 10.0;
        return min(98.0, $base);
    }

    private function getConfidenceLevel(int $matches): string
    {
        if ($matches > 10)
            return 'High';
        if ($matches > 3)
            return 'Medium';
        return 'Low';
    }

    private function simulateBehaviour(int $matches, array $ruleData): array
    {
        return [
            [
                'event' => 'Review Received (Match)',
                'outcome' => 'Rule Triggered',
                'action' => 'Actions Executed'
            ],
            [
                'event' => 'Second Review (Match)',
                'outcome' => $ruleData['trigger_only_on_first_occurrence'] ? 'Suppressed' : 'Triggered',
                'action' => $ruleData['trigger_only_on_first_occurrence'] ? 'Cooldown Active' : 'Actions Executed'
            ]
        ];
    }
}
