<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use Carbon\Carbon;

class RecentReviewService
{
    /**
     * Get recent reviews
     * 
     * @param mixed $reviewsOrBusinessId Reviews collection or business ID
     * @param array|null $dateRange Date range filters
     * @param int $limit Number of reviews to return
     * @return array
     */
    public function getRecentReviews($reviewsOrBusinessId, $dateRange = null, $limit = 5)
    {
        $reviews = $reviewsOrBusinessId;

        if (is_numeric($reviewsOrBusinessId)) {
            $businessId = $reviewsOrBusinessId;
            $query = ReviewNew::where('business_id', $businessId)
                ->globalReviewFilters(0)
                ->withCalculatedRating();

            if ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            }

            $reviews = $query->with('rule_outcomes')->latest()->get();
        }

        // If second parameter was passed as limit (numeric and no third param), handle legacy calls
        if (is_numeric($dateRange) && func_num_args() <= 2) {
            $limit = $dateRange;
        }

        return $reviews->loadMissing('rule_outcomes')->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $rating = $review->calculated_rating ?? 0;

                return [
                    'id' => $review->id,
                    'rating' => $rating,
                    'stars' => str_repeat('★', (int) floor($rating)) . str_repeat('☆', 5 - (int) floor($rating)),
                    'review_text' => $review->comment ?? $review->raw_text ?? 'No comment',
                    'staff_name' => $review->staff ? $review->staff->name : 'Not assigned',
                    'staff_id' => $review->staff_id,
                    'sentiment' => $this->getSentimentLabel($review->sentiment_score),
                    'date' => $review->created_at ? $review->created_at->diffForHumans() : 'Unknown',
                    'exact_date' => $review->created_at ? $review->created_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $review->created_at ? $review->created_at->format('d-m-Y H:i:s') : null,
                    'is_flagged' => $review->is_flagged,
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
    private function getSentimentLabel($score)
    {
        if ($score >= 0.7)
            return 'Positive';
        if ($score >= 0.4)
            return 'Neutral';
        return 'Negative';
    }
}
