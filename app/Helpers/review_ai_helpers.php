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
    function generateStaffSuggestions($weaknesses)
    {
        return App\Helpers\AIProcessor::generateStaffSuggestions($weaknesses);
    }
}

if (!function_exists('getReviewFeed')) {
    function getReviewFeed($businessId, $dateRange, $limit = 10)
    {
        return App\Helpers\AIProcessor::getReviewFeed($businessId, $dateRange, $limit);
    }
}

if (!function_exists('getStaffPerformanceSnapshot')) {
    function getStaffPerformanceSnapshot($businessId, $dateRange)
    {
        return App\Helpers\AIProcessor::getStaffPerformanceSnapshot($businessId, $dateRange);
    }
}

if (!function_exists('extractSkillGapsFromSuggestions')) {
    function extractSkillGapsFromSuggestions($suggestions)
    {
        return App\Helpers\AIProcessor::extractSkillGapsFromSuggestions($suggestions);
    }
}

if (!function_exists('generateAiSummary')) {
    function generateAiSummary($reviews)
    {
        return App\Helpers\AIProcessor::generateAiSummary($reviews);
    }
}

if (!function_exists('extractIssuesFromSuggestions')) {
    function extractIssuesFromSuggestions($suggestions)
    {
        return App\Helpers\AIProcessor::extractIssuesFromSuggestions($suggestions);
    }
}

if (!function_exists('extractOpportunitiesFromSuggestions')) {
    function extractOpportunitiesFromSuggestions($suggestions)
    {
        return App\Helpers\AIProcessor::extractOpportunitiesFromSuggestions($suggestions);
    }
}










































if (!function_exists('generatePredictions')) {
    function generatePredictions($reviews)
    {
        return App\Helpers\AIProcessor::generatePredictions($reviews);
    }
}

if (!function_exists('transcribeAudio')) {
    function transcribeAudio($filePath)
    {
        return App\Helpers\AIProcessor::transcribeAudio($filePath);
    }
}

if (!function_exists('getAiInsightsPanel')) {
    function getAiInsightsPanel($businessId, $dateRange)
    {
        return App\Helpers\AIProcessor::getAiInsightsPanel($businessId, $dateRange);
    }
}

if (!function_exists('getBranchComparisonData')) {
    function getBranchComparisonData($branch, $startDate, $endDate)
    {
        return App\Helpers\AIProcessor::getBranchComparisonData($branch, $startDate, $endDate);
    }
}

if (!function_exists('getBranchStaffPerformance')) {
    function getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate)
    {
        return App\Helpers\AIProcessor::getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate);
    }
}

if (!function_exists('extractBranchTopics')) {
    function extractBranchTopics($reviews)
    {
        return App\Helpers\AIProcessor::extractBranchTopics($reviews);
    }
}

if (!function_exists('generateBranchComparisonInsights')) {
    function generateBranchComparisonInsights($branchesData, $allMetrics)
    {
        return App\Helpers\AIProcessor::generateBranchComparisonInsights($branchesData, $allMetrics);
    }
}

if (!function_exists('generateComparisonHighlights')) {
    function generateComparisonHighlights($branchesData)
    {
        return App\Helpers\AIProcessor::generateComparisonHighlights($branchesData);
    }
}

if (!function_exists('getSentimentTrendOverTime')) {
    function getSentimentTrendOverTime($branches, $startDate, $endDate)
    {
        return App\Helpers\AIProcessor::getSentimentTrendOverTime($branches, $startDate, $endDate);
    }
}

if (!function_exists('getStaffComplaintsByBranch')) {
    function getStaffComplaintsByBranch($branches, $startDate, $endDate)
    {
        return App\Helpers\AIProcessor::getStaffComplaintsByBranch($branches, $startDate, $endDate);
    }
}

if (!function_exists('calculateBranchSummary')) {
    function calculateBranchSummary($reviews)
    {
        return App\Helpers\AIProcessor::calculateBranchSummary($reviews);
    }
}

if (!function_exists('extractTopTopic')) {
    function extractTopTopic($reviews)
    {
        return App\Helpers\AIProcessor::extractTopTopic($reviews);
    }
}

if (!function_exists('generateAiInsights')) {
    function generateAiInsights($reviews)
    {
        return App\Helpers\AIProcessor::generateAiInsights($reviews);
    }
}

if (!function_exists('generateAiSummaryReport')) {
    function generateAiSummaryReport($reviews, $sentimentBreakdown)
    {
        return App\Helpers\AIProcessor::generateAiSummaryReport($reviews, $sentimentBreakdown);
    }
}

if (!function_exists('extractKeyTrends')) {
    function extractKeyTrends($reviews)
    {
        return App\Helpers\AIProcessor::extractKeyTrends($reviews);
    }
}

if (!function_exists('findCommonIssues')) {
    function findCommonIssues($reviews)
    {
        return App\Helpers\AIProcessor::findCommonIssues($reviews);
    }
}

if (!function_exists('findPeakReviewTimes')) {
    function findPeakReviewTimes($reviews)
    {
        return App\Helpers\AIProcessor::findPeakReviewTimes($reviews);
    }
}

if (!function_exists('generateBranchRecommendations')) {
    function generateBranchRecommendations($reviews)
    {
        return App\Helpers\AIProcessor::generateBranchRecommendations($reviews);
    }
}

if (!function_exists('getRecentReviews')) {
    function getRecentReviews($reviews, $limit = 5)
    {
        return App\Helpers\AIProcessor::getRecentReviews($reviews, $limit);
    }
}

if (!function_exists('getStaffPerformance')) {
    function getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 5)
    {
        return App\Helpers\AIProcessor::getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit);
    }
}

if (!function_exists('getStaffEvaluation')) {
    function getStaffEvaluation($avgRating, $reviewCount)
    {
        return App\Helpers\AIProcessor::getStaffEvaluation($avgRating, $reviewCount);
    }
}

if (!function_exists('generateActionItem')) {
    function generateActionItem($issue, $evidenceCount)
    {
        return App\Helpers\AIProcessor::generateActionItem($issue, $evidenceCount);
    }
}

if (!function_exists('calculateStaffMetricsFromReviewValue')) {
    function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
    {
        return App\Helpers\AIProcessor::calculateStaffMetricsFromReviewValue($reviews, $staffUser);
    }
}

if (!function_exists('extractTopicsFromReviews')) {
    function extractTopicsFromReviews($reviews)
    {
        return App\Helpers\AIProcessor::extractTopicsFromReviews($reviews);
    }
}

if (!function_exists('calculatePerformanceByCategory')) {
    function calculatePerformanceByCategory($reviews)
    {
        return App\Helpers\AIProcessor::calculatePerformanceByCategory($reviews);
    }
}

if (!function_exists('getNotableReviews')) {
    function getNotableReviews($reviews, $limit = 2)
    {
        return App\Helpers\AIProcessor::getNotableReviews($reviews, $limit);
    }
}

if (!function_exists('getSentimentGapMessage')) {
    function getSentimentGapMessage($gap)
    {
        return App\Helpers\AIProcessor::getSentimentGapMessage($gap);
    }
}

if (!function_exists('getPreviousPeriodReviews')) {
    function getPreviousPeriodReviews($businessId, $period)
    {
        return App\Helpers\AIProcessor::getPreviousPeriodReviews($businessId, $period);
    }
}

if (!function_exists('calculateOverallMetricsFromReviewValue')) {
    function calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews)
    {
        return App\Helpers\AIProcessor::calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews);
    }
}

if (!function_exists('calculateAverageSentiment')) {
    function calculateAverageSentiment($reviews)
    {
        return App\Helpers\AIProcessor::calculateAverageSentiment($reviews);
    }
}

if (!function_exists('extractStaffTopics')) {
    function extractStaffTopics($staffReviews)
    {
        return App\Helpers\AIProcessor::extractStaffTopics($staffReviews);
    }
}

if (!function_exists('getTopThreeStaff')) {
    function getTopThreeStaff($businessId, $filters = [])
    {
        return App\Helpers\AIProcessor::getTopThreeStaff($businessId, $filters);
    }
}

if (!function_exists('calculatePerformanceOverviewFromReviewValue')) {
    function calculatePerformanceOverviewFromReviewValue($reviews)
    {
        return App\Helpers\AIProcessor::calculatePerformanceOverviewFromReviewValue($reviews);
    }
}

if (!function_exists('getReviewSamples')) {
    function getReviewSamples($reviews, $limit = 2)
    {
        return App\Helpers\AIProcessor::getReviewSamples($reviews, $limit);
    }
}

if (!function_exists('getSubmissionsOverTime')) {
    function getSubmissionsOverTime($reviews, $period)
    {
        return App\Helpers\AIProcessor::getSubmissionsOverTime($reviews, $period);
    }
}































if (!function_exists('getRecentSubmissions')) {
    function getRecentSubmissions($reviews, $limit = 5)
    {
        return App\Helpers\AIProcessor::getRecentSubmissions($reviews, $limit);
    }
}

if (!function_exists('getRatingGapMessage')) {
    function getRatingGapMessage($gap)
    {
        return App\Helpers\AIProcessor::getRatingGapMessage($gap);
    }
}

if (!function_exists('getRecommendedTraining')) {
    function getRecommendedTraining($reviews)
    {
        return App\Helpers\AIProcessor::getRecommendedTraining($reviews);
    }
}

if (!function_exists('analyzeSkillGaps')) {
    function analyzeSkillGaps($reviews)
    {
        return App\Helpers\AIProcessor::analyzeSkillGaps($reviews);
    }
}

if (!function_exists('calculateCustomerTone')) {
    function calculateCustomerTone($reviews)
    {
        return App\Helpers\AIProcessor::calculateCustomerTone($reviews);
    }
}

if (!function_exists('calculateSentimentDistribution')) {
    function calculateSentimentDistribution($reviews)
    {
        return App\Helpers\AIProcessor::calculateSentimentDistribution($reviews);
    }
}

if (!function_exists('calculateComplimentRatio')) {
    function calculateComplimentRatio($reviews)
    {
        return App\Helpers\AIProcessor::calculateComplimentRatio($reviews);
    }
}

if (!function_exists('getAllStaffMetricsFromReviewValue')) {
    function getAllStaffMetricsFromReviewValue($reviews)
    {
        return App\Helpers\AIProcessor::getAllStaffMetricsFromReviewValue($reviews);
    }
}

if (!function_exists('getAudioDuration')) {
    function getAudioDuration($filePath)
    {
        return App\Helpers\AIProcessor::getAudioDuration($filePath);
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