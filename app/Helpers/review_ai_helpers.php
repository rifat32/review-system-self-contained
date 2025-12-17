<?php

// AI Moderation

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\User;
use Carbon\Carbon;
use getID3;

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
if (!function_exists('getSentimentLabel')) {
    function getSentimentLabel($score)
    {
        return App\Helpers\AIProcessor::getSentimentLabel($score);
    }
}
if (!function_exists('getSentimentLabelByPercentage')) {
    function getSentimentLabelByPercentage($percentage)
    {
        return App\Helpers\AIProcessor::getSentimentLabelByPercentage($percentage);
    }
}
if (!function_exists('emptyStaffMetrics')) {
    function emptyStaffMetrics($staffUser)
    {
        return App\Helpers\AIProcessor::emptyStaffMetrics($staffUser);
    }
}

if (!function_exists('calculateStaffRatingTrend')) {
    function calculateStaffRatingTrend($reviews)
    {
        return App\Helpers\AIProcessor::calculateStaffRatingTrend($reviews);
    }
}

if (!function_exists('getTopMentionedStaff')) {
    function getTopMentionedStaff($positiveReviews)
    {
        return App\Helpers\AIProcessor::getTopMentionedStaff($positiveReviews);
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
    return App\Helpers\AIProcessor::generateStaffSuggestions($weaknesses);

   
}





if (!function_exists('getReviewFeed')) {
    return App\Helpers\AIProcessor::getReviewFeed($businessId, $dateRange, $limit = 10);
}

if (!function_exists('getStaffPerformanceSnapshot')) {
    return App\Helpers\AIProcessor::getStaffPerformanceSnapshot($businessId, $dateRange);
}

if (!function_exists('extractSkillGapsFromSuggestions')) {
    return App\Helpers\AIProcessor::extractSkillGapsFromSuggestions($suggestions);
}




if (!function_exists('generateAiSummary')) {
    return App\Helpers\AIProcessor::generateAiSummary($reviews);
}

if (!function_exists('extractIssuesFromSuggestions')) {
    return App\Helpers\AIProcessor::extractIssuesFromSuggestions($suggestions);
}


if (!function_exists('extractOpportunitiesFromSuggestions')) {
    return App\Helpers\AIProcessor::extractOpportunitiesFromSuggestions($suggestions);
   
}


if (!function_exists('generatePredictions')) {
    return App\Helpers\AIProcessor::generatePredictions($reviews);
    
}


if (!function_exists('transcribeAudio')) {
    return App\Helpers\AIProcessor::transcribeAudio($filePath);

}


if (!function_exists('getAiInsightsPanel')) {
    return App\Helpers\AIProcessor::getAiInsightsPanel($businessId, $dateRange);
    
}

if (!function_exists('getBranchComparisonData')) {
    return App\Helpers\AIProcessor::getBranchComparisonData($branch, $startDate, $endDate);
    
}



if (!function_exists('getBranchStaffPerformance')) {
    return App\Helpers\AIProcessor::getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate);
    
}


if (!function_exists('extractBranchTopics')) {
    return App\Helpers\AIProcessor::extractBranchTopics($reviews);
    
}

if (!function_exists('generateBranchComparisonInsights')) {
    return App\Helpers\AIProcessor::generateBranchComparisonInsights($branchesData, $allMetrics);
  
}


if (!function_exists('generateComparisonHighlights')) {
    return App\Helpers\AIProcessor::generateComparisonHighlights($branchesData);
   
}

if (!function_exists('getSentimentTrendOverTime')) {
    return App\Helpers\AIProcessor::getSentimentTrendOverTime($branches, $startDate, $endDate);
    
}


if (!function_exists('getStaffComplaintsByBranch')) {
    return App\Helpers\AIProcessor::getStaffComplaintsByBranch($branches, $startDate, $endDate);
    
}

if (!function_exists('calculateBranchSummary')) {
    return App\Helpers\AIProcessor::calculateBranchSummary($reviews);
   
}




if (!function_exists('extractTopTopic')) {
    return App\Helpers\AIProcessor::extractTopTopic($reviews);
   
}
if (!function_exists('generateAiInsights')) {
    return App\Helpers\AIProcessor::generateAiInsights($reviews);
    
}
if (!function_exists('generateAiSummaryReport')) {
    return App\Helpers\AIProcessor::generateAiSummaryReport($reviews, $sentimentBreakdown);
    
}
if (!function_exists('extractKeyTrends')) {
    return App\Helpers\AIProcessor::extractKeyTrends($reviews);
   
}
if (!function_exists('findCommonIssues')) {
    return App\Helpers\AIProcessor::findCommonIssues($reviews);
   
}
if (!function_exists('findPeakReviewTimes')) {
    return App\Helpers\AIProcessor::findPeakReviewTimes($reviews);
   
}
if (!function_exists('generateBranchRecommendations')) {
    return App\Helpers\AIProcessor::generateBranchRecommendations($reviews);
    
}


if (!function_exists('getRecentReviews')) {
    return App\Helpers\AIProcessor::getRecentReviews($reviews, $limit = 5);
   
}
if (!function_exists('getStaffPerformance')) {
    return App\Helpers\AIProcessor::getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 5);
   
}

if (!function_exists('getStaffEvaluation')) {
    return App\Helpers\AIProcessor::getStaffEvaluation($avgRating, $reviewCount);
    /**
     * Get staff evaluation label
     */
   
}



if (!function_exists('generateActionItem')) {
    return App\Helpers\AIProcessor::generateActionItem($issue, $evidenceCount);
    
}




if (!function_exists('calculateStaffMetricsFromReviewValue')) {
    return App\Helpers\AIProcessor::calculateStaffMetricsFromReviewValue($reviews, $staffUser);
    
}

if (!function_exists('extractTopicsFromReviews')) {
    return App\Helpers\AIProcessor::extractTopicsFromReviews($reviews);
   
}
if (!function_exists('calculatePerformanceByCategory')) {
    return App\Helpers\AIProcessor::calculatePerformanceByCategory($reviews);
    
}
if (!function_exists('getNotableReviews')) {
    return App\Helpers\AIProcessor::getNotableReviews($reviews, $limit = 2);
   
}


if (!function_exists('getSentimentGapMessage')) {
    return App\Helpers\AIProcessor::getSentimentGapMessage($gap);
    
}







if (!function_exists('getPreviousPeriodReviews')) {
    return App\Helpers\AIProcessor::getPreviousPeriodReviews($businessId, $period);
   
}



if (!function_exists('calculateOverallMetricsFromReviewValue')) {
    return App\Helpers\AIProcessor::calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews);

   
}



if (!function_exists('calculateAverageSentiment')) {
    return App\Helpers\AIProcessor::calculateAverageSentiment($reviews);
    
}


if (!function_exists('extractStaffTopics')) {
    return App\Helpers\AIProcessor::extractStaffTopics($staffReviews);
    
}
if (!function_exists('getTopThreeStaff')) {
    return App\Helpers\AIProcessor::getTopThreeStaff($businessId, $filters = []);
   
}

if (!function_exists('calculatePerformanceOverviewFromReviewValue')) {
    return App\Helpers\AIProcessor::calculatePerformanceOverviewFromReviewValue($reviews);
    
}
if (!function_exists('getReviewSamples')) {
    return App\Helpers\AIProcessor::getReviewSamples($reviews, $limit = 2);
    
}
if (!function_exists('getSubmissionsOverTime')) {
    return App\Helpers\AIProcessor::getSubmissionsOverTime($reviews, $period);
    
}


if (!function_exists('getRecentSubmissions')) {
    return App\Helpers\AIProcessor::getRecentSubmissions($reviews, $limit = 5);
    
}

if (!function_exists('getRatingGapMessage')) {
    return App\Helpers\AIProcessor::getRatingGapMessage($gap);
   
}

if (!function_exists('getRecommendedTraining')) {
    return App\Helpers\AIProcessor::getRecommendedTraining($reviews);
   
}

if (!function_exists('analyzeSkillGaps')) {
    return App\Helpers\AIProcessor::analyzeSkillGaps($reviews);
    
}
if (!function_exists('calculateCustomerTone')) {
    return App\Helpers\AIProcessor::calculateCustomerTone($reviews);
   
}
if (!function_exists('calculateSentimentDistribution')) {
    return App\Helpers\AIProcessor::calculateSentimentDistribution($reviews);
   
}
if (!function_exists('calculateComplimentRatio')) {
    return App\Helpers\AIProcessor::calculateComplimentRatio($reviews);
    
}



if (!function_exists('getAllStaffMetricsFromReviewValue')) {
    return App\Helpers\AIProcessor::getAllStaffMetricsFromReviewValue($reviews);
    
}



if (!function_exists('getAudioDuration')) {
    return App\Helpers\AIProcessor::getAudioDuration($filePath);
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