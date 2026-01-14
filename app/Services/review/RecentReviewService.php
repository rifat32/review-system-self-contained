<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use Carbon\Carbon;

class RecentReviewService
{
    /**
     * Get recent reviews
     * 
     * @param mixed $reviews Reviews collection or query
     * @param int $limit Number of reviews to return
     * @return array
     */
    public static function getRecentReviews($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $rating = $review->calculated_rating;

                return [
                    'id' => $review->id,
                    'rating' => $rating,
                    'stars' => str_repeat('★', floor($rating)) . str_repeat('☆', 5 - floor($rating)),
                    'review_text' => $review->comment ?? $review->raw_text ?? 'No comment',
                    'staff_name' => $review->staff ? $review->staff->name : 'Not assigned',
                    'staff_id' => $review->staff_id,
                    'sentiment' => static::getSentimentLabel($review->sentiment_score),
                    'date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('Y-m-d H:i:s'),
                    'is_flagged' => $review->status === 'flagged',
                    'has_actions' => true,
                    'user_type' => $review->user_id ? 'Registered' : ($review->guest_id ? 'Guest' : 'Anonymous')
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get sentiment label from score
     * 
     * @param float $score Sentiment score (0-1)
     * @return string
     */
    private static function getSentimentLabel($score)
    {
        if ($score >= 0.7)
            return 'Positive';
        if ($score >= 0.4)
            return 'Neutral';
        return 'Negative';
    }

    /**
     * Get recent submissions
     * 
     * @param mixed $reviews Reviews collection
     * @param int $limit Number of submissions to return
     * @return array
     */
    public static function getRecentSubmissions($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => round($review->calculated_rating ?? 0, 1),
                    'sentiment_score' => round(($review->sentiment_score ?? 0) * 100, 1),
                    'comment' => substr($review->comment ?? '', 0, 100) . (strlen($review->comment ?? '') > 100 ? '...' : ''),
                    'submitted_at' => $review->created_at?->diffForHumans(),
                    'customer' => $review->user?->name ?? $review->guest_user?->name ?? 'Anonymous'
                ];
            })
            ->values()
            ->toArray();
    }
}
