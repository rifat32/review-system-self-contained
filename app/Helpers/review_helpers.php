<?php

// AI Moderation

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\User;

if (!function_exists('aiModeration')) {
    function aiModeration($text)
    {
        return App\Helpers\AIProcessor::aiModeration($text);
    }
}


// Sentiment Analysis
if (!function_exists('analyzeSentiment')) {
    function analyzeSentiment($text)
    {
        return App\Helpers\AIProcessor::analyzeSentiment($text);
    }
}

// Topic Extraction
if (!function_exists('extractTopics')) {
    function extractTopics($text)
    {
        return App\Helpers\AIProcessor::extractTopics($text);
    }
}

// Staff Performance Analysis
if (!function_exists('analyzeStaffPerformance')) {
    function analyzeStaffPerformance($text, $staff_id, $sentiment_score = null)
    {
        return App\Helpers\AIProcessor::analyzeStaffPerformance($text, $staff_id, $sentiment_score);
    }
}

// Generate Recommendations
if (!function_exists('generateRecommendations')) {
    function generateRecommendations($topics, $sentiment_score)
    {
        return App\Helpers\AIProcessor::generateRecommendations($topics, $sentiment_score);
    }
}

// Emotion Detection
if (!function_exists('detectEmotion')) {
    function detectEmotion($text)
    {
        return App\Helpers\AIProcessor::detectEmotion($text);
    }
}

// Key Phrases Extraction
if (!function_exists('extractKeyPhrases')) {
    function extractKeyPhrases($text)
    {
        return App\Helpers\AIProcessor::extractKeyPhrases($text);
    }
}

// Generate Staff Suggestions
if (!function_exists('generateStaffSuggestions')) {
    function generateStaffSuggestions($weaknesses)
    {
        $suggestions = [];

        foreach ($weaknesses as $weakness) {
            switch ($weakness) {
                case 'communication':
                    $suggestions[] = 'Needs better communication skills training';
                    break;
                case 'service_speed':
                    $suggestions[] = 'Requires efficiency and time management training';
                    break;
                case 'product_knowledge':
                    $suggestions[] = 'Needs product knowledge workshop';
                    break;
                case 'attitude':
                    $suggestions[] = 'Customer service excellence training recommended';
                    break;
            }
        }

        return $suggestions;
    }
}


if (!function_exists('getSentimentLabel')) {
      function getSentimentLabel(?float $score): string
    {
        if ($score === null) {
            return 'neutral';
        }
        return $score >= 0.7 ? 'positive' : ($score >= 0.4 ? 'neutral' : 'negative');
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


 if (!function_exists('getReviewFeed')) {

 function getReviewFeed($businessId, $dateRange, $limit = 10)
{
    $reviews = ReviewNew::with(['user', 'guest_user', 'staff', 'value.tag', 'value'])
        ->where('business_id', $businessId)
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->orderBy('created_at', 'desc')
        ->globalFilters(1, $businessId)
        ->limit($limit)
        ->withCalculatedRating()
        ->get();

    return $reviews->map(function ($review) {
        // Use the calculated_rating from the query, no need to recalculate
        $calculatedRating = (float) $review->calculated_rating; // Cast to float

        return [
            'id' => $review->id,
            'responded_at' => $review->responded_at,
            'rating' => ($calculatedRating ?? 0) . '/5',
            'calculated_rating' => $calculatedRating,
            'author' => $review->user?->name ?? $review->guest_user?->full_name ?? 'Anonymous',
            'time_ago' => $review->created_at->diffForHumans(),
            'comment' => $review->comment,
            'staff_name' => $review->staff?->name,
            'tags' => $review->value->map(fn($v) => $v->tag->tag ?? null)->filter()->unique()->values()->toArray(),
            'is_voice' => $review->is_voice_review,
            'sentiment' => getSentimentLabel($review->sentiment_score),
            'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
        ];
    });
}
 }

if (!function_exists('getStaffPerformanceSnapshot')) {
 function getStaffPerformanceSnapshot($businessId, $dateRange)
    {
        // Get staff reviews WITH calculated rating
        $staffReviews = ReviewNew::with('staff')
            ->where('business_id', $businessId)
            ->globalFilters(1, $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
           ->withCalculatedRating()
            ->get();

        $staffData = [];

        foreach ($staffReviews as $staffId => $reviews) {
            if ($reviews->count() < 3) continue; // Minimum reviews

            $staff = $reviews->first()->staff;
            if (!$staff) continue;

            // Calculate average rating FROM calculated_rating field
            $avgRating = $reviews->isNotEmpty()
                ? round($reviews->avg('calculated_rating'), 1)
                : 0;

            // Calculate sentiment metrics
            $positiveReviews = $reviews->where('calculated_rating', '>=', 4)->count();
            $negativeReviews = $reviews->where('calculated_rating', '<=', 2)->count();
            $neutralReviews = $reviews->whereBetween('calculated_rating', [2.1, 3.9])->count();

            $staff_suggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();

            $staffData[] = [
                'id' => $staffId,
                'name' => $staff->name,
                'email' => $staff->email,
                'job_title' => $staff->job_title ?? 'Staff',
                'rating' => $avgRating,
                'review_count' => $reviews->count(),
                'sentiment_breakdown' => [
                    'positive' => $reviews->count() > 0
                        ? round(($positiveReviews / $reviews->count()) * 100)
                        : 0,
                    'neutral' => $reviews->count() > 0
                        ? round(($neutralReviews / $reviews->count()) * 100)
                        : 0,
                    'negative' => $reviews->count() > 0
                        ? round(($negativeReviews / $reviews->count()) * 100)
                        : 0
                ],
                'positive_count' => $positiveReviews,
                'negative_count' => $negativeReviews,
                'skill_gaps' => extractSkillGapsFromSuggestions($staff_suggestions),
                'recommended_training' => $staff_suggestions->first() ?? 'General Training',
                'last_review_date' => $reviews->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                'rating_trend' => calculateStaffRatingTrend($reviews)
            ];
        }

        // Sort by rating (highest first)
        usort($staffData, fn($a, $b) => $b['rating'] <=> $a['rating']);

        $top = array_slice($staffData, 0, 3);
        $needsImprovement = array_slice(array_reverse($staffData), 0, 3);

        // Add overall stats
        $totalStaffWithReviews = count($staffData);
        $overallAvgRating = $totalStaffWithReviews > 0
            ? round(array_sum(array_column($staffData, 'rating')) / $totalStaffWithReviews, 1)
            : 0;

        return [
            'top_performing' => $top,
            'needs_improvement' => $needsImprovement,
            'overall_stats' => [
                'total_staff_with_reviews' => $totalStaffWithReviews,
                'overall_average_rating' => $overallAvgRating,
                'top_performer_rating' => !empty($top) ? $top[0]['rating'] : 0,
                'lowest_performer_rating' => !empty($needsImprovement)
                    ? $needsImprovement[0]['rating']
                    : 0,
                'rating_gap' => !empty($top) && !empty($needsImprovement)
                    ? round($top[0]['rating'] - $needsImprovement[0]['rating'], 1)
                    : 0
            ]
        ];
    }
}

if (!function_exists('extractSkillGapsFromSuggestions')) {
      function extractSkillGapsFromSuggestions($suggestions)
    {
        return $suggestions
            ->filter(fn($s) => stripos($s, 'needs') !== false || stripos($s, 'requires') !== false)
            ->map(fn($s) => preg_replace('/.*needs\s+(.*?) training.*/i', '$1', $s))
            ->filter(fn($s) => strlen($s) > 3)
            ->values()
            ->toArray();
    }
}

if (!function_exists('calculateStaffRatingTrend')) {
     function calculateStaffRatingTrend($reviews)
    {
        if ($reviews->count() < 4) {
            return 'insufficient_data';
        }

        // Split reviews into two halves to see trend
        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstHalfAvg = $firstHalf->avg('calculated_rating') ?? 0;
        $secondHalfAvg = $secondHalf->avg('calculated_rating') ?? 0;

        if ($secondHalfAvg > $firstHalfAvg + 0.2) {
            return 'improving';
        } elseif ($secondHalfAvg < $firstHalfAvg - 0.2) {
            return 'declining';
        } else {
            return 'stable';
        }
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


if (!function_exists('generateAiSummary')) {
     function generateAiSummary($reviews)
    {
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();
        $total = $reviews->count();

        if ($total == 0) return 'No reviews to analyze.';

        $positivePercent = round(($positiveCount / $total) * 100);
        $negativePercent = round(($negativeCount / $total) * 100);

        return "Customers are {$positivePercent}% positive and {$negativePercent}% negative. " .
            "Common themes include staff friendliness, service speed, and occasional cleanliness concerns.";
    }
}

if (!function_exists('extractIssuesFromSuggestions')) {
  function extractIssuesFromSuggestions($suggestions)
    {
        $issues = collect($suggestions)
            ->filter(fn($s) => stripos($s, 'consider') !== false || stripos($s, 'implement') !== false)
            ->map(fn($s) => [
                'issue' => $s,
                'mention_count' => 1
            ])
            ->take(3)
            ->values();

        return $issues->isEmpty() ? [[
            'issue' => 'No major issues detected.',
            'mention_count' => 0
        ]] : $issues->toArray();
    }
}


if (!function_exists('extractOpportunitiesFromSuggestions')) {
   function extractOpportunitiesFromSuggestions($suggestions)
    {
        return collect($suggestions)
            ->filter(fn($s) => stripos($s, 'add') !== false || stripos($s, 'highlight') !== false)
            ->take(2)
            ->values()
            ->toArray();
    }
}


if (!function_exists('generatePredictions')) {
       function generatePredictions($reviews)
    {
        // Calculate average rating from calculated_rating field (much faster)
        if ($reviews->isEmpty()) {
            return [[
                'prediction' => 'No reviews available for prediction.',
                'estimated_impact' => 'N/A'
            ]];
        }

        // Use calculated_rating directly from the query results
        $avgRating = $reviews->avg('calculated_rating') ?? 0;
        $predictedIncrease = max(0, 5 - $avgRating) * 0.05;

        return [[
            'prediction' => 'Improving identified issues could boost overall rating.',
            'estimated_impact' => '+' . round($predictedIncrease, 2) . ' points',
            'current_avg_rating' => round($avgRating, 1),
            'potential_new_rating' => round(min(5, $avgRating + $predictedIncrease), 1)
        ]];
    }
}


if (!function_exists('transcribeAudio')) {
   
     function transcribeAudio($filePath)
    {
        try {
            $api_key = env('HF_API_KEY');
            $audio = file_get_contents($filePath);

            // Log file basic info
            \Log::info("HF Transcription Started", [
                'file_path' => $filePath,
                'file_size' => strlen($audio),
                'mime' => mime_content_type($filePath)
            ]);

            $ch = curl_init("https://router.huggingface.co/hf-inference/models/openai/whisper-large-v3");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer $api_key",
                    "Content-Type: audio/mpeg"
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $audio,
                CURLOPT_RETURNTRANSFER => true,
            ]);

            $result = curl_exec($ch);
            $error  = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Log full CURL response
            \Log::info("HF Whisper API Response", [
                'http_status' => $status,
                'curl_error'  => $error,
                'raw_result'  => $result
            ]);

            if ($error) {
                \Log::error("HF Whisper CURL Error: $error");
                return '';
            }

            $data = json_decode($result, true);

            // Log decoded output
            \Log::info("HF Whisper Decoded Response", [
                'decoded' => $data
            ]);

            return $data['text'] ?? '';
        } catch (\Exception $e) {
            \Log::error("transcribeAudio() exception: " . $e->getMessage());
            return '';
        }
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




if (!function_exists('getAiInsightsPanel')) {
  function getAiInsightsPanel($businessId, $dateRange)
    {
        // Get reviews WITH calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('ai_suggestions')
            ->globalFilters(1, $businessId)
            ->withCalculatedRating()
            ->get();

        // Extract common themes from existing AI suggestions
        $allSuggestions = $reviews->pluck('ai_suggestions')->flatten();
        $allTopics = $reviews->pluck('topics')->flatten();

        return [
            'summary' => generateAiSummary($reviews),
            'detected_issues' => extractIssuesFromSuggestions($allSuggestions),
            'opportunities' => extractOpportunitiesFromSuggestions($allSuggestions),
            'predictions' => generatePredictions($reviews)
        ];
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