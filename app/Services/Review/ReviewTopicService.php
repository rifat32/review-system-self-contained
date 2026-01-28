<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use App\Models\Tag;

class ReviewTopicService
{
    // ==================== TOP TOPIC ANALYSIS ====================

    /**
     * Get top topic summary from reviews collection
     */
    public function getTopTopicSummary($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                'name' => 'No Data',
                'count' => 0
            ];
        }

        // Get business ID from first review
        $businessId = $reviews->first()->business_id ?? null;

        if (!$businessId) {
            return [
                'name' => 'Unknown',
                'count' => 0
            ];
        }

        // Get review IDs
        $reviewIds = $reviews->pluck('id')->toArray();

        // Method 0: Check InsightRecord for AI-driven top topic (Most Accurate)
        $reviewIds = array_unique(array_map('intval', $reviewIds));
        $idsList = implode(',', $reviewIds);

        // Dynamic count calculation in SQL to ensure we order by "contextual" records
        $dynamicMentionsSql = "(SELECT COUNT(*) FROM JSON_TABLE(insight_records.review_ids, '$[*]' COLUMNS (val INT PATH '$')) as jt WHERE jt.val IN ({$idsList}))";

        $aiTopic = \App\Models\InsightRecord::where('business_id', $businessId)
            ->select('*')
            ->selectRaw("{$dynamicMentionsSql} as dynamic_mentions")
            ->where(function ($query) use ($reviewIds) {
                // Optimization: narrowing down candidates
                foreach (array_chunk($reviewIds, 50) as $chunk) {
                    $query->orWhere(function ($sub) use ($chunk) {
                        foreach ($chunk as $id) {
                            $sub->orWhereJsonContains('review_ids', (int) $id);
                        }
                    });
                }
            })
            ->orderByDesc('dynamic_mentions')
            ->first();

        if ($aiTopic && $aiTopic->dynamic_mentions > 0) {
            return [
                'name' => $aiTopic->main_category,
                'count' => (int) $aiTopic->dynamic_mentions
            ];
        }

        // Method 1: Check tags from review values (Accurate fallback)
        $topTag = Tag::where('business_id', $businessId)
            ->whereHas('review_values', function ($query) use ($reviewIds) {
                $query->whereIn('review_id', $reviewIds);
            })
            ->withCount([
                'review_values' => function ($query) use ($reviewIds) {
                    $query->whereIn('review_id', $reviewIds);
                }
            ])
            ->orderByDesc('review_values_count')
            ->first();

        if ($topTag && $topTag->review_values_count > 0) {
            return [
                'name' => $topTag->name ?? $topTag->tag,
                'count' => $topTag->review_values_count
            ];
        }

        // Method 2: Fallback to AI-generated topics from reviews
        $topicCounts = $reviews
            ->whereNotNull('topics')
            ->pluck('topics')
            ->flatten()
            ->filter()
            ->countBy()
            ->sortDesc();

        if ($topicCounts->isNotEmpty()) {
            return [
                'name' => $topicCounts->keys()->first() ?? 'Service',
                'count' => $topicCounts->first()
            ];
        }

        return [
            'name' => 'Service',
            'count' => 0
        ];
    }



    /**
     * Get top topic using business ID and date range (for backward compatibility)
     */
    public function getTopTopic($businessId, $startDate, $endDate)
    {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->globalReviewFilters(0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return $this->getTopTopicSummary($reviews);
    }
}
