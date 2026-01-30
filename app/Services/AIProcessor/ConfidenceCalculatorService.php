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

        // 1. Mentions factor
        $mentionsScores = config('ai.insights.confidence.mentions_scores', []);
        foreach ($mentionsScores as $config) {
            if ($insight->mentions_count >= ($config['min'] ?? 0)) {
                $score += ($config['score'] ?? 0);
                break;
            }
        }

        // 2. Severity factor
        $severityScores = config('ai.insights.confidence.severity_scores', []);
        $score += $severityScores[$insight->severity] ?? 0;

        // 3. Trend factor
        $trendScores = config('ai.insights.confidence.trend_scores', []);
        $score += $trendScores[$insight->trend] ?? 0;

        // 4. Time factor
        // Recent insights get higher score
        $daysOld = $insight->time_window_end->diffInDays(now());
        $timeFactors = config('ai.insights.confidence.time_factors', []);
        foreach ($timeFactors as $factor) {
            if ($daysOld <= ($factor['max_days'] ?? 0)) {
                $score += ($factor['score'] ?? 0);
                break;
            }
        }

        return min(100, $score);
    }

    /**
     * Convert score to confidence level
     */
    private function getConfidenceLevel(int $score): string
    {
        $thresholds = config('ai.insights.confidence.thresholds', []);
        if ($score >= ($thresholds['high'] ?? 80))
            return 'high';
        if ($score >= ($thresholds['medium'] ?? 60))
            return 'medium';
        return 'low';
    }

    /**
     * Get factors contributing to confidence
     */
    private function getConfidenceFactors(InsightRecord $insight): array
    {
        $factors = [];

        if ($insight->mentions_count >= (config('ai.insights.trends.increasing.mentions') ?? 5)) {
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
        if ($daysOld <= (config('ai.insights.trends.emerging.days') ?? 7)) {
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

        if ($mentions >= (config('ai.insights.trends.increasing.mentions') ?? 5) && $severity === 'high') {
            return 'high';
        }

        if ($mentions >= (config('ai.insights.trends.emerging.mentions') ?? 3) || $severity === 'medium') {
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
