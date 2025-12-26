<?php

// AI Moderation

use App\Models\BusinessService;
use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use Illuminate\Support\Facades\DB;



if (!function_exists('getSentimentLabel')) {
    function getSentimentLabel($score)
    {
        return App\Helpers\AIProcessor::getSentimentLabel($score);
    }
}


if (!function_exists('getTopMentionedStaff')) {
    function getTopMentionedStaff($positiveReviews)
    {
        return App\Helpers\AIProcessor::getTopMentionedStaff($positiveReviews);
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



if (!function_exists('calculateStaffMetricsFromReviewValue')) {
    function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
    {
        return App\Helpers\AIProcessor::calculateStaffMetricsFromReviewValue($reviews, $staffUser);
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

if (!function_exists('getTopTopic')) {
    
/**
 * Helper method to get the top topic from tags
 */
 function getTopTopic($businessId, $startDate, $endDate)
{
    // Get tag counts from ReviewValueNew for the period
    $topTag = ReviewValueNew::
    
    join('tags', 'review_value_news.tag_id', '=', 'tags.id')
    ->whereHas("review", function ($query) use ($businessId, $startDate, $endDate) {
            $query->where('business_id', $businessId)
                  ->whereBetween('created_at', [$startDate, $endDate])
                  ->globalFilters(0, $businessId);
                   
         })
        ->where('tags.business_id', $businessId)
        ->whereBetween('review_value_news.created_at', [$startDate, $endDate])
    
        ->select('tags.tag', DB::raw('COUNT(review_value_news.id) as count'))
        ->groupBy('tags.id', 'tags.tag')
        ->orderByDesc('count')
        ->first();
    
    if ($topTag) {
        return [
            'name' => $topTag->name,
            'count' => $topTag->count
        ];
    }
    
    // Fallback: check for common topics from AI analysis
    $commonTopics = ReviewNew::where('business_id', $businessId)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->whereNotNull('topics')
        ->globalFilters(0, $businessId)
        ->get()
        ->pluck('topics')
        ->flatten()
        ->countBy()
        ->sortDesc()
        ->first();
    
    if ($commonTopics) {
        return [
            'name' => $commonTopics->keys()->first() ?? 'Service',
            'count' => $commonTopics->first()
        ];
    }
    
    return [
        'name' => 'Service',
        'count' => 0
    ];
}
}

if (!function_exists('analyzeBusinessServicesPerformance')) {
    function analyzeBusinessServicesPerformance($businessId, $dateRange)
    {
        // Get all business services for this business
        $businessServices = BusinessService::where('business_id', $businessId)
            ->get(['id', 'name', 'description']);
        
        if ($businessServices->isEmpty()) {
            return [
                'top_services' => [],
                'worst_services' => [],
                'message' => 'No business services defined'
            ];
        }
        
        // Get reviews within date range with their associated services
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalFilters(0, $businessId)
            ->with(['business_services', 'value']) // Eager load services and values
            ->withCalculatedRating()
            ->get();
        
        if ($reviews->isEmpty()) {
            return [
                'top_services' => [],
                'worst_services' => [],
                'message' => 'No reviews available for the selected period'
            ];
        }
        
        $serviceMetrics = [];
        
        foreach ($businessServices as $service) {
            // Find reviews that mention this specific service
            $serviceReviews = $reviews->filter(function ($review) use ($service) {
                return $review->business_services->contains('id', $service->id);
            });
            
            if ($serviceReviews->isNotEmpty()) {
                // Calculate average rating for this service
                $avgRating = round($serviceReviews->avg('calculated_rating'), 2);
                $totalReviews = $serviceReviews->count();
                
                // Calculate sentiment for this service
                $positiveCount = $serviceReviews->where('sentiment_score', '>=', 0.7)->count();
                $negativeCount = $serviceReviews->where('sentiment_score', '<', 0.4)->count();
                $sentimentPercentage = $totalReviews > 0 ? round(($positiveCount / $totalReviews) * 100) : 0;
                
                // Get common tags for this service
                $commonTags = [];
                foreach ($serviceReviews as $review) {
                    if ($review->value) {
                        foreach ($review->value as $value) {
                            if ($value->tag) {
                                $tagName = $value->tag->tag;
                                $commonTags[$tagName] = ($commonTags[$tagName] ?? 0) + 1;
                            }
                        }
                    }
                }
                arsort($commonTags);
                $topTags = array_slice(array_keys($commonTags), 0, 3);
                
                // Get sample comments
                $sampleComments = $serviceReviews->sortByDesc('calculated_rating')
                    ->take(2)
                    ->map(function ($review) {
                        return [
                            'comment' => substr($review->comment ?? '', 0, 100) . (strlen($review->comment ?? '') > 100 ? '...' : ''),
                            'rating' => round($review->calculated_rating ?? 0, 1),
                            'sentiment' => getSentimentLabel($review->sentiment_score ?? 0),
                            'date' => $review->created_at->format('M d, Y')
                        ];
                    })
                    ->values()
                    ->toArray();
                
                $serviceMetrics[$service->id] = [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'description' => $service->description,
                    'average_rating' => $avgRating,
                    'total_reviews' => $totalReviews,
                    'sentiment_score' => $sentimentPercentage,
                    'positive_reviews' => $positiveCount,
                    'negative_reviews' => $negativeCount,
                    'top_tags' => $topTags,
                    'sample_comments' => $sampleComments,
                    'performance_label' => $avgRating >= 4.5 ? 'Excellent' : 
                                         ($avgRating >= 4.0 ? 'Very Good' : 
                                         ($avgRating >= 3.5 ? 'Good' : 
                                         ($avgRating >= 3.0 ? 'Average' : 
                                         ($avgRating >= 2.0 ? 'Below Average' : 'Poor'))))
                ];
            }
        }
        
        // Sort by average rating (highest first)
        uasort($serviceMetrics, function ($a, $b) {
            // First by rating, then by number of reviews for tie-breaking
            if ($b['average_rating'] == $a['average_rating']) {
                return $b['total_reviews'] <=> $a['total_reviews'];
            }
            return $b['average_rating'] <=> $a['average_rating'];
        });
        
        // Get services with at least 3 reviews for accurate analysis
        $qualifiedServices = array_filter($serviceMetrics, function ($service) {
            return $service['total_reviews'] >= 3;
        });
        
        // Get top 3 and worst 3
        if (count($qualifiedServices) >= 6) {
            $allServices = array_values($qualifiedServices);
            $topServices = array_slice($allServices, 0, 3);
            $worstServices = array_slice(array_reverse($allServices), 0, 3);
        } else {
            // If not enough qualified services, use all available
            $allServices = array_values($serviceMetrics);
            $topServices = array_slice($allServices, 0, min(3, count($allServices)));
            $worstServices = array_slice(array_reverse($allServices), 0, min(3, count($allServices)));
        }
        
        // Calculate overall metrics
        $allServiceRatings = array_column($serviceMetrics, 'average_rating');
        $overallServiceRating = count($allServiceRatings) > 0 ? 
            round(array_sum($allServiceRatings) / count($allServiceRatings), 2) : 0;
        
        return [
            'top_services' => array_values($topServices),
            'worst_services' => array_values($worstServices),
            'all_services' => array_values($serviceMetrics),
            'summary' => [
                'total_services_analyzed' => count($serviceMetrics),
                'services_with_reviews' => count($qualifiedServices),
                'overall_service_rating' => $overallServiceRating,
                'best_performing_service' => !empty($topServices) ? $topServices[0]['service_name'] : 'N/A',
                'worst_performing_service' => !empty($worstServices) ? $worstServices[0]['service_name'] : 'N/A',
                'period' => [
                    'start' => $dateRange['start']->format('Y-m-d'),
                    'end' => $dateRange['end']->format('Y-m-d')
                ]
            ]
        ];
    }
}

// Calculate Aggregated Sentiment
if (!function_exists('calculateAggregatedSentiment')) {
    function calculateAggregatedSentiment($reviews)
    {
        return App\Helpers\AIProcessor::calculateAggregatedSentiment($reviews);
    }
}