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

        // Get date range from reviews
        $startDate = $reviews->min('created_at');
        $endDate = $reviews->max('created_at');

        // Method 0: Check InsightRecord for AI-driven top topic (Most Accurate)
        $aiTopic = \App\Models\InsightRecord::where('business_id', $businessId)
            ->whereBetween('time_window_start', [$startDate, $endDate])
            ->orderByDesc('mentions_count')
            ->first();

        if ($aiTopic) {
            return [
                'name' => $aiTopic->main_category,
                'count' => $aiTopic->mentions_count
            ];
        }

        // Method 1: Check tags from review values (Accurate fallback)
        $topTag = Tag::where('business_id', $businessId)
            ->whereHas('review_values', function ($query) use ($businessId, $startDate, $endDate) {
                $query->whereHas('review', function ($q) use ($businessId, $startDate, $endDate) {
                    $q->where('business_id', $businessId)
                        ->whereBetween('created_at', [$startDate, $endDate])
                        ->globalReviewFilters(0, $businessId);
                })
                    ->whereBetween('review_value_news.created_at', [$startDate, $endDate]);
            })
            ->withCount([
                'review_values' => function ($query) use ($businessId, $startDate, $endDate) {
                    $query->whereHas('review', function ($q) use ($businessId, $startDate, $endDate) {
                        $q->where('business_id', $businessId)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->globalReviewFilters(0, $businessId);
                    })
                        ->whereBetween('review_value_news.created_at', [$startDate, $endDate]);
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
            ->globalReviewFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return $this->getTopTopicSummary($reviews);
    }
}
