<?php

namespace App\Services\Review;

/**
 * ReviewTopicService - Extract and analyze topics from reviews
 * Platform-agnostic service for topic detection and categorization
 */
class ReviewTopicService
{
    /**
     * Get top topic summary with minimal data
     * Returns only what's needed: counts and top topic name
     * 
     * @param mixed $reviews Collection or array of ReviewNew models
     * @return array Minimal topic summary
     */
    public static function getTopTopicSummary($reviews): array
    {
        $totalReviews = is_countable($reviews) ? count($reviews) : $reviews->count();
        $staffCount = 0;

        // Count unique staff
        $staffIds = [];
        foreach ($reviews as $review) {
            if ($review->staff_id && !in_array($review->staff_id, $staffIds)) {
                $staffIds[] = $review->staff_id;
            }
        }
        $staffCount = count($staffIds);

        if ($totalReviews === 0) {
            return [
                'review_count' => 0,
                'staff_count' => 0,
                'top_topic_count' => 0,
                'top_topic' => null
            ];
        }

        // Get all topics with counts
        $topicCounts = self::extractTopicCounts($reviews);

        if (empty($topicCounts)) {
            return [
                'review_count' => $totalReviews,
                'staff_count' => $staffCount,
                'top_topic_count' => 0,
                'top_topic' => 'General'
            ];
        }

        // Get top topic
        arsort($topicCounts);
        $topTopicName = array_key_first($topicCounts);
        $topTopicCount = $topicCounts[$topTopicName];

        return [
            'review_count' => $totalReviews,
            'staff_count' => $staffCount,
            'top_topic_count' => round($topTopicCount),
            'top_topic' => $topTopicName
        ];
    }

    /**
     * Extract topic counts from reviews (internal helper)
     * Combines AI topics and keyword matching
     * 
     * @param mixed $reviews Collection or array of reviews
     * @return array Topic name => weighted count
     */
    private static function extractTopicCounts($reviews): array
    {
        $aiTopicCounts = [];
        $keywordTopicCounts = [];

        $topicKeywords = self::getTopicKeywordsMap();

        foreach ($reviews as $review) {
            // Extract from AI-generated topics
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $normalizedTopic = ucwords(strtolower(trim($topic)));
                    $aiTopicCounts[$normalizedTopic] = ($aiTopicCounts[$normalizedTopic] ?? 0) + 1;
                }
            }

            // Extract from comment keywords
            if ($review->comment) {
                $comment = strtolower($review->comment);

                foreach ($topicKeywords as $topicName => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $comment)) {
                            $keywordTopicCounts[$topicName] = ($keywordTopicCounts[$topicName] ?? 0) + 1;
                            break; // Count once per topic per review
                        }
                    }
                }
            }
        }

        // Merge with AI topics weighted higher
        $mergedTopics = [];

        foreach ($aiTopicCounts as $topic => $count) {
            $mergedTopics[$topic] = ($aiTopicCounts[$topic] ?? 0) + ($keywordTopicCounts[$topic] ?? 0);
        }

        foreach ($keywordTopicCounts as $topic => $count) {
            if (!isset($mergedTopics[$topic])) {
                $mergedTopics[$topic] = $count;
            }
        }

        return $mergedTopics;
    }

    /**
     * Enhanced topic extraction with detailed statistics
     * Use this when you need full topic breakdown
     * 
     * @param mixed $reviews Collection or array of ReviewNew models
     * @param int $limit Maximum number of topics to return
     * @return array Detailed topic analysis
     */
    public static function extractTopTopics($reviews, int $limit = 5): array
    {
        $totalReviews = is_countable($reviews) ? count($reviews) : $reviews->count();

        if ($totalReviews === 0) {
            return [
                'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
                'all_topics' => [],
                'sources' => ['ai_topics' => 0, 'keyword_matches' => 0]
            ];
        }

        $aiTopicCounts = [];
        $keywordTopicCounts = [];
        $topicKeywords = self::getTopicKeywordsMap();

        foreach ($reviews as $review) {
            // AI topics
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $normalizedTopic = ucwords(strtolower(trim($topic)));
                    $aiTopicCounts[$normalizedTopic] = ($aiTopicCounts[$normalizedTopic] ?? 0) + 1;
                }
            }

            // Keyword topics
            if ($review->comment) {
                $comment = strtolower($review->comment);

                foreach ($topicKeywords as $topicName => $keywords) {
                    $matched = false;
                    foreach ($keywords as $keyword) {
                        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $comment)) {
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        $keywordTopicCounts[$topicName] = ($keywordTopicCounts[$topicName] ?? 0) + 1;
                    }
                }
            }
        }

        // Merge topics (actual count, not weighted)
        $mergedTopics = [];
        foreach ($aiTopicCounts as $topic => $count) {
            $mergedTopics[$topic] = ($aiTopicCounts[$topic] ?? 0) + ($keywordTopicCounts[$topic] ?? 0);
        }
        foreach ($keywordTopicCounts as $topic => $count) {
            if (!isset($mergedTopics[$topic])) {
                $mergedTopics[$topic] = $count;
            }
        }

        arsort($mergedTopics);

        // Build detailed result
        $allTopics = [];
        foreach (array_slice($mergedTopics, 0, $limit, true) as $topicName => $count) {
            $allTopics[] = [
                'name' => $topicName,
                'count' => $count,
                'percentage' => round(($count / $totalReviews) * 100, 1),
                'source' => isset($aiTopicCounts[$topicName]) && isset($keywordTopicCounts[$topicName])
                    ? 'both'
                    : (isset($aiTopicCounts[$topicName]) ? 'ai' : 'keyword')
            ];
        }

        $topTopic = !empty($allTopics)
            ? $allTopics[0]
            : ['name' => 'General', 'count' => 0, 'percentage' => 0, 'source' => 'default'];

        return [
            'top_topic' => $topTopic,
            'all_topics' => $allTopics,
            'sources' => [
                'ai_topics' => count($aiTopicCounts),
                'keyword_matches' => count($keywordTopicCounts),
                'total_reviews_analyzed' => $totalReviews
            ]
        ];
    }

    /**
     * Get topic keywords map (internal)
     * 
     * @return array Topic name => keywords array
     */
    private static function getTopicKeywordsMap(): array
    {
        return [
            'Service' => ['service', 'serving', 'served', 'help', 'assistance', 'support'],
            'Staff' => ['staff', 'employee', 'worker', 'team', 'crew', 'manager', 'waiter', 'server'],
            'Wait Time' => ['wait', 'waiting', 'queue', 'line', 'slow', 'delay', 'took long', 'minutes'],
            'Quality' => ['quality', 'standard', 'grade', 'level', 'excellence'],
            'Pricing' => ['price', 'pricing', 'cost', 'expensive', 'cheap', 'affordable', 'value', 'worth'],
            'Cleanliness' => ['clean', 'cleanliness', 'dirty', 'messy', 'hygiene', 'sanitary', 'tidy'],
            'Product' => ['product', 'item', 'goods', 'merchandise', 'selection', 'variety'],
            'Location' => ['location', 'place', 'venue', 'spot', 'area', 'accessibility', 'parking'],
            'Atmosphere' => ['atmosphere', 'ambiance', 'environment', 'vibe', 'mood', 'setting'],
            'Food Quality' => ['food', 'taste', 'flavor', 'fresh', 'delicious', 'yummy', 'bland', 'stale'],
            'Friendliness' => ['friendly', 'polite', 'courteous', 'welcoming', 'rude', 'attitude'],
            'Speed' => ['fast', 'quick', 'rapid', 'prompt', 'efficient', 'speedy'],
            'Professionalism' => ['professional', 'expert', 'skilled', 'competent', 'knowledgeable']
        ];
    }

    /**
     * Get topic keywords for a specific category
     * 
     * @param string $category Topic category name
     * @return array Keywords for the category
     */
    public static function getTopicKeywords(string $category): array
    {
        $allKeywords = self::getTopicKeywordsMap();
        return $allKeywords[$category] ?? [];
    }

    /**
     * Get all available topic categories
     * 
     * @return array List of topic category names
     */
    public static function getAvailableCategories(): array
    {
        return array_keys(self::getTopicKeywordsMap());
    }
}
