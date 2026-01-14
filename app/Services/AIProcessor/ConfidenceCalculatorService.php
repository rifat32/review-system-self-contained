<?php
// app/Services/AIProcessor/ConfidenceCalculatorService.php

namespace App\Services\AIProcessor;

use App\Models\InsightRecord;

class ConfidenceCalculatorService
{
    /**
     * Calculate confidence level for insight
     */
    public function calculateInsightConfidence(InsightRecord $insight): array
    {
        $score = $this->calculateScore($insight);

        return [
            'level' => $this->getConfidenceLevel($score),
            'score' => $score,
            'factors' => $this->getConfidenceFactors($insight)
        ];
    }

    /**
     * Calculate numeric confidence score (0-100)
     */
    private function calculateScore(InsightRecord $insight): int
    {
        $score = 0;

        // 1. Mentions factor (max 40 points)
        if ($insight->mentions_count >= 10) {
            $score += 40;
        } elseif ($insight->mentions_count >= 5) {
            $score += 30;
        } elseif ($insight->mentions_count >= 3) {
            $score += 20;
        } elseif ($insight->mentions_count >= 2) {
            $score += 10;
        }

        // 2. Severity factor (max 30 points)
        $severityScores = ['low' => 10, 'medium' => 20, 'high' => 30];
        $score += $severityScores[$insight->severity] ?? 0;

        // 3. Trend factor (max 20 points)
        $trendScores = ['stable' => 5, 'emerging' => 15, 'increasing' => 20];
        $score += $trendScores[$insight->trend] ?? 0;

        // 4. Time factor (max 10 points)
        // Recent insights get higher score
        $daysOld = $insight->time_window_end->diffInDays(now());
        if ($daysOld <= 7) {
            $score += 10;
        } elseif ($daysOld <= 14) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Convert score to confidence level
     */
    private function getConfidenceLevel(int $score): string
    {
        if ($score >= 80)
            return 'high';
        if ($score >= 60)
            return 'medium';
        return 'low';
    }

    /**
     * Get factors contributing to confidence
     */
    private function getConfidenceFactors(InsightRecord $insight): array
    {
        $factors = [];

        if ($insight->mentions_count >= 5) {
            $factors[] = "High mention count ({$insight->mentions_count} reviews)";
        }

        if ($insight->severity === 'high') {
            $factors[] = "High severity issue";
        }

        if ($insight->trend === 'increasing') {
            $factors[] = "Increasing trend detected";
        }

        if ($insight->trend === 'emerging') {
            $factors[] = "Emerging issue";
        }

        $daysOld = $insight->time_window_end->diffInDays(now());
        if ($daysOld <= 7) {
            $factors[] = "Recent feedback (last {$daysOld} days)";
        }

        return $factors;
    }

    /**
     * Calculate recommendation confidence
     */
    public function calculateRecommendationConfidence(array $recommendationData): string
    {
        // Business rules for recommendation confidence
        $mentions = $recommendationData['evidence']['mentions'] ?? 0;
        $severity = $recommendationData['evidence']['severity'] ?? 'low';

        if ($mentions >= 5 && $severity === 'high') {
            return 'high';
        }

        if ($mentions >= 3 || $severity === 'medium') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get confidence badge for display
     */
    public function getConfidenceBadge(string $level): array
    {
        $badges = [
            'high' => [
                'label' => 'High Confidence',
                'color' => 'green',
                'icon' => '✓'
            ],
            'medium' => [
                'label' => 'Medium Confidence',
                'color' => 'yellow',
                'icon' => '⚠'
            ],
            'low' => [
                'label' => 'Low Confidence',
                'color' => 'gray',
                'icon' => 'ℹ'
            ]
        ];

        return $badges[$level] ?? $badges['low'];
    }
}
