<?php

namespace App\Services\Review;

use App\Models\ReviewNew;
use App\Services\AIProcessor\AIProcessorService;

class ReviewFeedService
{
    // ==================== REVIEW FEED ====================

    /**
     * Get review feed
     */
    public static function getReviewFeed(
        $businessId,
        $dateRange = null,
        $limit = 10,
        $user = null
    ) {
        $userBranchId = ($user && ($user->hasRole('branch_manager') || $user->hasRole('business_owner')))
            ? $user->default_branch_id
            : null;

        $query = ReviewNew::with(['user', 'guest_user', 'staff', 'value.tags', 'value'])
            ->where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->globalFilters(0, $businessId)
            ->limit($limit)
            ->withCalculatedRating();

        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        if ($userBranchId) {
            $query->where('branch_id', $userBranchId);
        }

        $reviews = $query->get();

        return $reviews->map(function ($review) {
            $calculatedRating = (float) $review->calculated_rating;
            $user = $review->user;

            return [
                'id' => $review->id,
                'responded_at' => $review->responded_at,
                'rating' => ($calculatedRating ?? 0) . '/5',
                'calculated_rating' => $calculatedRating,
                'author' => $review->user?->name ?? $review->guest_user?->full_name ?? 'Anonymous',
                'author_image' => $review->user?->image ?? null,
                'time_ago' => $review->created_at->diffForHumans(),
                'comment' => $review->comment,
                'staff_name' => $review->staff?->name,
                'tags' => $review->value->flatMap(function ($value) {
                    return $value->tags->pluck('tag')->all();
                })->filter()->unique()->values()->toArray(),
                'is_voice' => $review->is_voice_review,
                'sentiment' => AIProcessorService::getSentimentLabel($review->sentiment_score),
                'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
            ];
        });
    }
}
