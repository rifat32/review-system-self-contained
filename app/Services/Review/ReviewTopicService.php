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
                        ->globaReviewlFilters(0, $businessId);
                })
                    ->whereBetween('review_value_news.created_at', [$startDate, $endDate]);
            })
            ->withCount([
                'review_values' => function ($query) use ($businessId, $startDate, $endDate) {
                    $query->whereHas('review', function ($q) use ($businessId, $startDate, $endDate) {
                        $q->where('business_id', $businessId)
                            ->whereBetween('created_at', [$startDate, $endDate])
                            ->globaReviewlFilters(0, $businessId);
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

        // Method 3: Ultimate fallback - analyze comments for common keywords
        $commonWords = $this->extractCommonKeywords($reviews);

        if (!empty($commonWords)) {
            return [
                'name' => $commonWords[0]['word'],
                'count' => $commonWords[0]['count']
            ];
        }

        return [
            'name' => 'Service',
            'count' => 0
        ];
    }

    /**
     * Extract common keywords from review comments
     */
    private function extractCommonKeywords($reviews, $limit = 1)
    {
        $stopWords = \Illuminate\Support\Facades\Cache::remember('common_topic_stopwords', 3600, function () {
            $words = \App\Models\AiRule::where('category', 'common_topics')
                ->where('key_name', 'STOP_WORDS_EN')
                ->value('value');

            return $words ? json_decode($words, true) : ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those', 'it', 'its', 'they', 'their', 'them', 'very', 'good', 'bad', 'great', 'nice'];
        });

        $wordCounts = [];

        foreach ($reviews as $review) {
            if (!$review->comment) {
                continue;
            }

            $words = str_word_count(strtolower($review->comment), 1);

            foreach ($words as $word) {
                if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                    $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
                }
            }
        }

        arsort($wordCounts);

        $result = [];
        $count = 0;
        foreach ($wordCounts as $word => $frequency) {
            if ($count >= $limit) {
                break;
            }
            $result[] = [
                'word' => ucfirst($word),
                'count' => $frequency
            ];
            $count++;
        }

        return $result;
    }

    /**
     * Get top topic using business ID and date range (for backward compatibility)
     */
    public function getTopTopic($businessId, $startDate, $endDate)
    {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->globaReviewlFilters(0, $businessId)
            ->get();

        return $this->getTopTopicSummary($reviews);
    }
}
