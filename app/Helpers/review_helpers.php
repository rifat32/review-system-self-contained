<?php

// AI Moderation

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\User;
use Carbon\Carbon;









if (!function_exists('getDateRangeByPeriod')) {
    function getDateRangeByPeriod($period)
    {
        $now = Carbon::now();

        return match ($period) {
            'last_7_days' => [
                'start' => $now->copy()->subDays(7)->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ],
            'this_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfDay()
            ],
            'last_month' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth()
            ],
            default => [ // last_30_days
                'start' => $now->copy()->subDays(30)->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ]
        };
    }
}




if (!function_exists('calculatePercentageChange')) {
    function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) return 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }
}

if (!function_exists('extractRatingBreakdown')) {
    function extractRatingBreakdown($reviews)
    {
        $breakdown = [
            'excellent' => 0, // 4.5-5.0
            'good' => 0,      // 3.5-4.49
            'average' => 0,   // 2.5-3.49
            'poor' => 0,      // 1.5-2.49
            'very_poor' => 0, // 0-1.49
            'exact_ratings' => [
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0,
                '1' => 0
            ]
        ];

        $totalRating = 0;
        $validReviews = 0;

        foreach ($reviews as $review) {
            $rating = $review->calculated_rating ?? 0;

            if ($rating > 0) {
                $totalRating += $rating;
                $validReviews++;

                // Detailed breakdown
                if ($rating >= 4.5) {
                    $breakdown['excellent']++;
                } elseif ($rating >= 3.5) {
                    $breakdown['good']++;
                } elseif ($rating >= 2.5) {
                    $breakdown['average']++;
                } elseif ($rating >= 1.5) {
                    $breakdown['poor']++;
                } else {
                    $breakdown['very_poor']++;
                }

                // Exact ratings
                $roundedRating = round($rating);
                if (isset($breakdown['exact_ratings'][$roundedRating])) {
                    $breakdown['exact_ratings'][$roundedRating]++;
                }
            }
        }

        return [
            'breakdown' => $breakdown,
            'average_rating' => $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0,
            'total_reviews' => $reviews->count(),
            'valid_reviews' => $validReviews
        ];
    }
}

if (!function_exists('getDistanceMeters')) {
    function getDistanceMeters($lat1, $lon1, $lat2, $lon2)
    {
        $earth_radius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c;
    }
}

if (!function_exists('calculateDashboardMetrics')) {
    function calculateDashboardMetrics($businessId, $dateRange)
    {
        // Get current period reviews WITH calculated rating
        $reviews = ReviewNew::globalFilters(1, $businessId)
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->withCalculatedRating()
            ->get();

        // Get previous period reviews WITH calculated rating
        $previousReviews = ReviewNew::globalFilters(1, $businessId)
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [
                $dateRange['start']->copy()->subDays(30),
                $dateRange['end']->copy()->subDays(30)
            ])
            ->withCalculatedRating()
            ->get();

        $total = $reviews->count();
        $previousTotal = $previousReviews->count();

        // Calculate current period ratings FROM calculated_rating field
        $currentAvgRating = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate previous period ratings FROM calculated_rating field
        $previousAvgRating = $previousReviews->isNotEmpty()
            ? round($previousReviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate sentiment scores (still from ReviewNew)
        $current_sentiment_score = $reviews->avg('sentiment_score') ?? 0;
        $previous_sentiment_score = $previousReviews->avg('sentiment_score') ?? 0;

        // Calculate positive/negative counts based on calculated_rating
        $positiveReviewsCount = $reviews->where('calculated_rating', '>=', 4)->count();
        $negativeReviewsCount = $reviews->where('calculated_rating', '<=', 2)->count();



        return [
            'avg_overall_rating' => [
                'value' => $currentAvgRating,
                'change' => calculatePercentageChange(
                    $currentAvgRating,
                    $previousAvgRating
                ),
                'previous_value' => $previousAvgRating,
                'calculated_from' => 'review_value_news (via calculated_rating)'
            ],
            'ai_sentiment_score' => [
                'value' => round($current_sentiment_score * 10, 1),
                'max' => 10,
                'change' => calculatePercentageChange(
                    $current_sentiment_score,
                    $previous_sentiment_score
                )
            ],
            'total_reviews' => [
                'value' => $total,
                'change' => calculatePercentageChange($total, $previousTotal)
            ],
            'positive_negative_ratio' => [
                'positive' => $total > 0 ? round(($positiveReviewsCount / $total) * 100) : 0,
                'negative' => $total > 0 ? round(($negativeReviewsCount / $total) * 100) : 0,
                'positive_count' => $positiveReviewsCount,
                'negative_count' => $negativeReviewsCount
            ],
            'staff_linked_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->whereNotNull('staff_id')->count() / $total) * 100) : 0,
                'count' => $reviews->whereNotNull('staff_id')->count(),
                'total' => $total
            ],
            'voice_reviews' => [
                'percentage' => $total > 0 ? round(($reviews->where('is_voice_review', true)->count() / $total) * 100) : 0,
                'count' => $reviews->where('is_voice_review', true)->count(),
                'total' => $total
            ],
            'rating_distribution' => [
                '5_star' => $reviews->where('calculated_rating', '>=', 4.5)->count(),
                '4_star' => $reviews->whereBetween('calculated_rating', [4.0, 4.49])->count(),
                '3_star' => $reviews->whereBetween('calculated_rating', [3.0, 3.99])->count(),
                '2_star' => $reviews->whereBetween('calculated_rating', [2.0, 2.99])->count(),
                '1_star' => $reviews->where('calculated_rating', '<', 2.0)->count()
            ]
        ];
    }
}









if (!function_exists('storeReviewValues')) {
    // ##################################################
    // Helper to store review values (question/star)
    function storeReviewValues($review, $values, $business)
    {

        $averageRating = collect($values)
            ->pluck('star_id')
            ->filter()
            ->avg();

        $averageRating = $averageRating ? round($averageRating, 1) : null;

        foreach ($values as $value) {
            $value['review_id'] = $review->id;
            ReviewValueNew::create($value);
        }

        if ($business && $review->guest_id) {
            if ($averageRating >= $business->threshold_rating) {
                $review->is_private = 0;
                $review->save();
            } else {
                $review->is_private = 1;
                $review->save();
            }
        }

        // NO LONGER CALCULATE AND STORE THE AVERAGE HERE
        // Ratings will be calculated on-the-fly from ReviewValue data
    }
}














if (!function_exists('getAvailableFilters')) {
    function getAvailableFilters($businessId)
    {
        return [
            'periods' => ['Last 30 Days', 'Last 7 Days', 'This Month', 'Last Month', 'Custom Range'],
            'staff' => array_merge(
                ['All Staff'],
                User::whereHas('staffReviews', fn($q) => $q->where('business_id', $businessId))
                    ->get()
                    ->map(fn($user) => $user->name)
                    ->toArray()
            ),
            'branches' => ['All Branches', 'Downtown', 'Uptown', 'Westside'],
            'review_types' => ['All Review Types', 'Text Only', 'Voice Only', 'Survey', 'Overall'],
            'ai_sentiment' => ['All Sentiments', 'Positive', 'Neutral', 'Negative', 'AI Flagged']
        ];
    }
}





if (!function_exists('calculateResponseRate')) {
    /**
     * Calculate response rate
     */
    function calculateResponseRate($reviews)
    {
        $total = $reviews->count();
        if ($total === 0) return 0;

        $responded = $reviews->whereNotNull('responded_at')->count();
        return round(($responded / $total) * 100, 1);
    }
}












if (!function_exists('calculateTenure')) {
    function calculateTenure($joinDate)
    {
        if (!$joinDate) {
            return 'Not specified';
        }

        $join = Carbon::parse($joinDate);
        $now = Carbon::now();

        $years = $now->diffInYears($join);
        $months = $now->diffInMonths($join) % 12;

        return "{$years} years {$months} months";
    }
}
if (!function_exists('getRatingTrendFromReviewValue')) {
    function getRatingTrendFromReviewValue($reviews)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        $monthlyReviews = $reviews->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy(function ($review) {
                return $review->created_at->format('Y-m');
            });

        $monthlyRatings = [];

        foreach ($monthlyReviews as $month => $monthReviews) {
            // Use calculated_rating field directly
            $monthlyRatings[$month] = $monthReviews->avg('calculated_rating') ?? 0;
        }

        ksort($monthlyRatings);

        return [
            'period' => 'last_6_months',
            'data' => $monthlyRatings,
            'trend_direction' => calculateTrendDirection($monthlyRatings)
        ];
    }
}

if (!function_exists('calculateTrendDirection')) {
    function calculateTrendDirection($monthlyRatings)
    {
        if (count($monthlyRatings) < 2) {
            return 'stable';
        }

        $values = array_values($monthlyRatings);
        $first = $values[0];
        $last = end($values);

        if ($last > $first + 0.3) {
            return 'improving';
        } elseif ($last < $first - 0.3) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
}







if (!function_exists('fillMissingPeriods')) {
    function fillMissingPeriods($data, $startDate, $endDate, $format)
    {
        $filledData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format($format);
            $filledData[$periodKey] = $data[$periodKey] ?? [
                'submissions_count' => 0,
                'average_rating' => 0,
                'sentiment_score' => 0
            ];

            if ($format === 'd-m-Y') {
                $current->addDay();
            } else {
                $current->addMonth();
            }
        }

        return $filledData;
    }
}



if (!function_exists('applyFilters')) {
    function applyFilters($query, $filters)
    {
        // Survey filter
        if (!empty($filters['survey_id'])) {
            $query->where('survey_id', $filters['survey_id']);
        }

        // Guest reviews filter
        if (isset($filters['is_guest_review']) && $filters['is_guest_review'] === 'true') {
            $query->whereNotNull('guest_id');
        }

        // User reviews filter
        if (isset($filters['is_user_review']) && $filters['is_user_review'] === 'true') {
            $query->whereNotNull('user_id');
        }

        // Overall reviews filter
        if (isset($filters['is_overall']) && $filters['is_overall'] === 'true') {
            $query->where('is_overall', 1);
        } elseif (isset($filters['is_overall']) && $filters['is_overall'] === 'false') {
            $query->where('is_overall', 0);
        }

        // Staff filter
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        // Score range filter
        if (!empty($filters['min_score'])) {
            $query->where('rate', '>=', $filters['min_score']);
        }
        if (!empty($filters['max_score'])) {
            $query->where('rate', '<=', $filters['max_score']);
        }

        // Labels filter (using sentiment field)
        if (!empty($filters['labels'])) {
            $labels = is_array($filters['labels']) ? $filters['labels'] : explode(',', $filters['labels']);
            $query->whereHas('value', function ($q) use ($labels) {
                $q->whereIn('review_value_news.tag_id', $labels);
            });
        }

        // Review type filter (using review_type field)
        if (!empty($filters['review_type'])) {
            $query->where('review_type', $filters['review_type']);
        }

        // With comment or without comment
        if (isset($filters['has_comment']) && $filters['has_comment'] === 'true') {
            $query->whereNotNull('comment')->where('comment', '!=', '');
        } elseif (isset($filters['has_comment']) && $filters['has_comment'] === 'false') {
            $query->where(function ($q) {
                $q->whereNull('comment')->orWhere('comment', '');
            });
        }

        // Replied - yes or no
        if (isset($filters['has_reply']) && $filters['has_reply'] === 'true') {
            $query->whereNotNull('responded_at');
        } elseif (isset($filters['has_reply']) && $filters['has_reply'] === 'false') {
            $query->whereNull('responded_at');
        }

        return $query;
    }
}




if (!function_exists('getTopStaffByRatingFromReviewValue')) {
    function getTopStaffByRatingFromReviewValue($reviews, $limit = 5)
    {
        $staffGroups = $reviews->groupBy('staff_id');

        $staffRatings = $staffGroups->map(function ($staffReviews, $staffId) {
            $staff = User::find($staffId);
            if (!$staff) return null;

            // Use calculated_rating field directly
            $avgRating = $staffReviews->avg('calculated_rating') ?? 0;

            return [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'position' => $staff->job_title ?? 'Staff',
                'avg_rating' => round($avgRating, 1),
                'total_reviews' => $staffReviews->count(),
                'sentiment_score' => getSentimentLabel($staffReviews->avg('sentiment_score')),
                'image' => $staff->image ?? null
            ];
        })
            ->filter(function ($staff) {
                return $staff && $staff['total_reviews'] >= 3;
            })
            ->sortByDesc('avg_rating')
            ->take($limit)
            ->values()
            ->toArray();

        return $staffRatings;
    }
}




if (!function_exists('getUserName')) {
    function getUserName($review)
    {
        if ($review->user) {
            return $review->user->name;
        } elseif ($review->guest_user) {
            return $review->guest_user->full_name;
        } else {
            return 'Anonymous User';
        }
    }
}



//   private function calculateRatingBreakdown($businessId, $dateRange)
//     {
//         // Get reviews WITH calculated_rating in one query
//         $reviews = ReviewNew::where('business_id', $businessId)
//             ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
//             ->globalFilters(1, $businessId)
//             ->withCalculatedRating()
//             ->get();

//         // Initialize counters
//         $excellent = 0;
//         $good = 0;
//         $average = 0;
//         $poor = 0;
//         $veryPoor = 0;
//         $totalRating = 0;
//         $validReviews = 0;

//         // Count ratings based on calculated_rating values
//         foreach ($reviews as $review) {
//             $rating = $review->calculated_rating;

//             // Only count reviews with valid ratings (> 0)
//             if ($rating > 0) {
//                 $totalRating += $rating;
//                 $validReviews++;

//                 switch (true) {
//                     case $rating >= 4.5:
//                         $excellent++;
//                         break;
//                     case $rating >= 3.5 && $rating < 4.5:
//                         $good++;
//                         break;
//                     case $rating >= 2.5 && $rating < 3.5:
//                         $average++;
//                         break;
//                     case $rating >= 1.5 && $rating < 2.5:
//                         $poor++;
//                         break;
//                     case $rating < 1.5:
//                         $veryPoor++;
//                         break;
//                 }
//             }
//         }

//         // Calculate average rating
//         $avgRating = $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0;
//         $totalReviews = $reviews->count();

//         return [
//             'excellent' => [
//                 'percentage' => $validReviews > 0 ? round(($excellent / $validReviews) * 100) : 0,
//                 'count' => $excellent,
//                 'range' => '4.5-5.0 stars'
//             ],
//             'good' => [
//                 'percentage' => $validReviews > 0 ? round(($good / $validReviews) * 100) : 0,
//                 'count' => $good,
//                 'range' => '3.5-4.4 stars'
//             ],
//             'average' => [
//                 'percentage' => $validReviews > 0 ? round(($average / $validReviews) * 100) : 0,
//                 'count' => $average,
//                 'range' => '2.5-3.4 stars'
//             ],
//             'poor' => [
//                 'percentage' => $validReviews > 0 ? round(($poor / $validReviews) * 100) : 0,
//                 'count' => $poor,
//                 'range' => '1.5-2.4 stars'
//             ],
//             'very_poor' => [
//                 'percentage' => $validReviews > 0 ? round(($veryPoor / $validReviews) * 100) : 0,
//                 'count' => $veryPoor,
//                 'range' => '0-1.4 stars'
//             ],
//             'avg_rating' => $avgRating,
//             'total_reviews' => $totalReviews,
//             'reviews_with_rating' => $validReviews,
//             'rating_distribution' => [
//                 '5_star' => $reviews->where('calculated_rating', '>=', 4.5)->count(),
//                 '4_star' => $reviews->whereBetween('calculated_rating', [4.0, 4.49])->count(),
//                 '3_star' => $reviews->whereBetween('calculated_rating', [3.0, 3.99])->count(),
//                 '2_star' => $reviews->whereBetween('calculated_rating', [2.0, 2.99])->count(),
//                 '1_star' => $reviews->where('calculated_rating', '<', 2.0)->count()
//             ],
//             'summary' => [
//                 'positive_reviews' => $reviews->where('calculated_rating', '>=', 4)->count(),
//                 'neutral_reviews' => $reviews->whereBetween('calculated_rating', [3, 3.99])->count(),
//                 'negative_reviews' => $reviews->where('calculated_rating', '<', 3)->count(),
//                 'positive_percentage' => $validReviews > 0
//                     ? round(($reviews->where('calculated_rating', '>=', 4)->count() / $validReviews) * 100)
//                     : 0,
//                 'csat_score' => $validReviews > 0
//                     ? round(($reviews->where('calculated_rating', '>=', 4)->count() / $validReviews) * 100)
//                     : 0
//             ]
//         ];
//     }