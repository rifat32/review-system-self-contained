<?php
// app/Helpers/InsightAggregationHelper.php

namespace App\Services\AIProcessor;

use App\Models\ReviewNew;
use App\Models\InsightRecord;
use Carbon\Carbon;

class InsightAggregationService
{
    /**
     * Main aggregation business logic
     */
    public static function aggregateReviewsForBusiness(int $businessId, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        // Get AI-analyzed reviews
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('is_ai_processed', true)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Group reviews by category patterns
        $categoryPatterns = self::extractCategoryPatterns($reviews);

        // Create insight records
        $insightsCreated = 0;
        foreach ($categoryPatterns as $pattern) {
            $insight = self::createInsightFromPattern($businessId, $pattern, $startDate, $endDate);
            if ($insight) {
                $insightsCreated++;
            }
        }

        return [
            'business_id' => $businessId,
            'period_days' => $days,
            'reviews_analyzed' => $reviews->count(),
            'unique_patterns' => count($categoryPatterns),
            'insights_created' => $insightsCreated
        ];
    }

    /**
     * Extract patterns: Group reviews by (main_category + sub_category + staff_blame)
     */
    private static function extractCategoryPatterns($reviews): array
    {
        $patterns = [];

        foreach ($reviews as $review) {
            $openaiData = json_decode($review->openai_raw_response ?? '{}', true);
            $categories = $openaiData['category_analysis'] ?? [];

            foreach ($categories as $category) {
                $key = self::createPatternKey($category, $openaiData);

                if (!isset($patterns[$key])) {
                    $patterns[$key] = [
                        'main_category' => $category['main_category'] ?? 'General',
                        'sub_category' => $category['sub_category'] ?? 'General',
                        'staff_blame' => $openaiData['staff_intelligence']['blame_detected'] ?? false,
                        'review_ids' => [],
                        'sentiments' => [],
                        'severities' => [],
                        'first_seen' => $review->created_at,
                        'last_seen' => $review->created_at
                    ];
                }

                $patterns[$key]['review_ids'][] = $review->id;
                $patterns[$key]['sentiments'][] = $category['sentiment'] ?? 'neutral';
                $patterns[$key]['severities'][] = $category['severity'] ?? 'low';

                if ($review->created_at < $patterns[$key]['first_seen']) {
                    $patterns[$key]['first_seen'] = $review->created_at;
                }
                if ($review->created_at > $patterns[$key]['last_seen']) {
                    $patterns[$key]['last_seen'] = $review->created_at;
                }
            }
        }

        return array_values($patterns);
    }

    /**
     * Create unique pattern key
     */
    private static function createPatternKey(array $category, array $openaiData): string
    {
        $staffBlame = $openaiData['staff_intelligence']['blame_detected'] ?? false;
        return sprintf(
            '%s|%s|%s',
            $category['main_category'] ?? 'General',
            $category['sub_category'] ?? 'General',
            $staffBlame ? 'staff' : 'process'
        );
    }

    /**
     * Create insight record from pattern
     */
    private static function createInsightFromPattern(int $businessId, array $pattern, Carbon $startDate, Carbon $endDate): ?InsightRecord
    {
        $mentions = count($pattern['review_ids']);

        // Business rule: Minimum 2 mentions to create insight
        if ($mentions < 2) {
            return null;
        }

        // Calculate severity (most frequent)
        $severityCounts = array_count_values($pattern['severities']);
        arsort($severityCounts);
        $severity = key($severityCounts) ?: 'medium';

        // Calculate confidence (business rule)
        $confidence = self::calculateConfidence($mentions, $severity);

        // Check trend
        $trend = self::calculateTrend($pattern['first_seen'], $pattern['last_seen'], $mentions);

        // Find or create insight
        return InsightRecord::updateOrCreate(
            [
                'business_id' => $businessId,
                'main_category' => $pattern['main_category'],
                'sub_category' => $pattern['sub_category'],
                'time_window_start' => $startDate,
                'time_window_end' => $endDate
            ],
            [
                'mentions_count' => $mentions,
                'severity' => $severity,
                'confidence_level' => $confidence,
                'trend' => $trend,
                'staff_blame_detected' => $pattern['staff_blame'],
                'review_ids' => array_slice($pattern['review_ids'], 0, 100) // Cap at 100 IDs
            ]
        );
    }

    /**
     * Confidence calculation business rules:
     * - High: ≥5 mentions AND high severity
     * - Medium: ≥3 mentions OR medium severity  
     * - Low: everything else
     */
    private static function calculateConfidence(int $mentions, string $severity): string
    {
        if ($mentions >= 5 && $severity === 'high') {
            return 'high';
        }
        if ($mentions >= 3 || $severity === 'medium') {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Trend calculation: If issue appeared recently and frequently
     */
    private static function calculateTrend(Carbon $firstSeen, Carbon $lastSeen, int $mentions): string
    {
        $daysDiff = $firstSeen->diffInDays($lastSeen);

        if ($daysDiff <= 7 && $mentions >= 3) {
            return 'emerging'; // Rapid appearance
        }

        if ($mentions >= 5 && $daysDiff <= 14) {
            return 'increasing'; // Frequent in short period
        }

        return 'stable'; // Normal pattern
    }

    /**
     * Get aggregated insights for dashboard
     */
    public static function getDashboardInsights(int $businessId, int $limit = 10): array
    {
        $insights = InsightRecord::where('business_id', $businessId)
            ->where('mentions_count', '>=', 2) // Business rule: show only significant
            ->orderBy('mentions_count', 'desc')
            ->limit($limit)
            ->get();

        return $insights->map(function ($insight) {
            return [
                'id' => $insight->id,
                'category' => $insight->main_category,
                'sub_category' => $insight->sub_category,
                'mentions' => $insight->mentions_count,
                'severity' => $insight->severity,
                'confidence' => $insight->confidence_level,
                'trend' => $insight->trend,
                'is_staff_issue' => $insight->staff_blame_detected,
                'period' => [
                    'start' => $insight->time_window_start->format('M d'),
                    'end' => $insight->time_window_end->format('M d')
                ]
            ];
        })->toArray();
    }
}