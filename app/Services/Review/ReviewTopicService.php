<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use App\Models\Tag;
use Illuminate\Support\Collection;

class ReviewTopicService
{
    // ==================== TOP TOPIC ANALYSIS ====================

    /**
     * Get top topic summary from reviews collection
     * 
     * @param Collection $reviews
     */
    public function getTopTopicSummary(Collection $reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                'name' => 'No Data',
                'count' => 0,
                'insight_id' => null,
            ];
        }

        // Get business ID from first review
        $businessId = $reviews->first()->business_id ?? null;

        if (!$businessId) {
            return [
                'name' => 'Unknown',
                'count' => 0,
                'insight_id' => null,
            ];
        }

        // Get review IDs
        $reviewIds = $reviews->pluck('id')->toArray();

        // Method 0: Check InsightRecord for AI-driven top topic (Most Accurate)
        $reviewIds = array_unique(array_map('intval', $reviewIds));
        $idsList = implode(',', $reviewIds);

        // Compatibility: MariaDB doesn't always support JSON_TABLE. 
        // We use a summation of JSON_CONTAINS for each ID to calculate intersection count.
        $mentionsParts = [];
        foreach ($reviewIds as $id) {
            $mentionsParts[] = "JSON_CONTAINS(insight_records.review_ids, '" . (int)$id . "')";
        }
        $dynamicMentionsSql = "(" . implode(' + ', $mentionsParts) . ")";

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
                'count' => (int) $aiTopic->dynamic_mentions,
                'insight_id' => $aiTopic->id,
            ];
        }
        return [
            'name' => 'No Data',
            'count' => 0,
            'insight_id' => null,
        ];
    }
}
