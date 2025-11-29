<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\User;

class CustomerService
{
    /**
     * Enrich customer object with additional data like reviews, complaints, etc.
     *
     * @param User $user
     * @param Business $business
     * @return User
     */
    public function enrichCustomerWithData($user, $business)
    {
        // Fetch positive reviews separately
        $positive_reviews = ReviewNew::where('review_news.business_id', $business->id)
            ->where('review_news.rate', '>=', 4)
            ->where('review_news.user_id', $user->id)
            ->filterByStaff()
            ->count();
        $user->positive_reviews = $positive_reviews;

        // Fetch negative reviews separately
        $negative_reviews = ReviewNew::where('review_news.business_id', $business->id)
            ->where('review_news.rate', '<=', 2)
            ->where('review_news.user_id', $user->id)
            ->filterByStaff()
            ->count();
        $user->negative_reviews = $negative_reviews;

        // Fetch common complaints separately
        $common_complaints = ReviewNew::selectRaw('COUNT(id) as complaint_count, SUBSTRING_INDEX(comment, " ", 3) as complaint_snippet')
            ->where('review_news.business_id', $business->id)
            ->where('review_news.user_id', $user->id)
            ->groupBy('complaint_snippet')
            ->havingRaw('complaint_count > 2')
            ->filterByStaff()
            ->get();
        $user->common_complaints = $common_complaints;

        // Fetch satisfaction scores separately
        $satisfaction_scores = ReviewNew::where('review_news.business_id', $business->id)
            ->where('review_news.user_id', $user->id)
            ->filterByStaff()
            ->avg('review_news.rate');
        $user->avg_satisfaction = $satisfaction_scores;

        // Fetch customer comments trends separately
        $customer_comments_trends = ReviewNew::selectRaw('comment, COUNT(*) as comment_count')
            ->where('review_news.business_id', $business->id)
            ->where('review_news.user_id', $user->id)
            ->groupBy('comment')
            ->orderByDesc('comment_count')
            ->filterByStaff()
            ->limit(5)
            ->get();
        $user->customer_comments_trends = $customer_comments_trends;

        return $user;
    }
}
