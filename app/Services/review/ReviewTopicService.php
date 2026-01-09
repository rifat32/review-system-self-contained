<?php

namespace App\Services\review;

/**
 * ReviewTopicService - Extract and analyze topics from reviews
 * Platform-agnostic service for topic detection and categorization
 */
class ReviewTopicService
{
    /**
     * Enhanced topic extraction with better accuracy
     * Returns multiple topics with detailed statistics
     * 
     * @param mixed $reviews Collection or array of ReviewNew models
     * @param int $limit Maximum number of topics to return
     * @return array Topic analysis results
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

        // Expanded topic vocabulary with word boundaries for better matching
        $topicKeywords = [
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

        foreach ($reviews as $review) {
            // Extract from AI-generated topics
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $normalizedTopic = ucwords(strtolower(trim($topic)));
                    $aiTopicCounts[$normalizedTopic] = ($aiTopicCounts[$normalizedTopic] ?? 0) + 1;
                }
            }

            // Extract from comment keywords with word boundary matching
            if ($review->comment) {
                $comment = strtolower($review->comment);

                foreach ($topicKeywords as $topicName => $keywords) {
                    $matched = false;
                    foreach ($keywords as $keyword) {
                        // Use word boundaries for more accurate matching
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

        // Merge both sources with weighted preference for AI topics
        $mergedTopics = [];

        // Add AI topics with higher weight
        foreach ($aiTopicCounts as $topic => $count) {
            $mergedTopics[$topic] = $count * 1.5; // Give AI topics 1.5x weight
        }

        // Add keyword topics
        foreach ($keywordTopicCounts as $topic => $count) {
            $mergedTopics[$topic] = ($mergedTopics[$topic] ?? 0) + $count;
        }

        // Sort by count descending
        arsort($mergedTopics);

        // Build result array
        $allTopics = [];
        foreach (array_slice($mergedTopics, 0, $limit, true) as $topicName => $weightedCount) {
            // Get actual count (not weighted)
            $actualCount = ($aiTopicCounts[$topicName] ?? 0) + ($keywordTopicCounts[$topicName] ?? 0);

            $allTopics[] = [
                'name' => $topicName,
                'count' => round($actualCount),
                'percentage' => round(($actualCount / $totalReviews) * 100, 1),
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
     * Get topic keywords for a specific category
     * 
     * @param string $category Topic category name
     * @return array Keywords for the category
     */
    public static function getTopicKeywords(string $category): array
    {
        $allKeywords = [
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

        return $allKeywords[$category] ?? [];
    }

    /**
     * Get all available topic categories
     * 
     * @return array List of topic category names
     */
    public static function getAvailableCategories(): array
    {
        return [
            'Service',
            'Staff',
            'Wait Time',
            'Quality',
            'Pricing',
            'Cleanliness',
            'Product',
            'Location',
            'Atmosphere',
            'Food Quality',
            'Friendliness',
            'Speed',
            'Professionalism'
        ];
    }
}
