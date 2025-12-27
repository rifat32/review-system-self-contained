<?php

namespace App\Helpers;

use App\Models\ReviewNew;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use getID3;
use Carbon\Carbon;
class AIProcessor
{


    /**
     * Get sentiment label from score - CORRECTED VERSION
     */
    public static function getSentimentLabel(?float $score): string
    {
        if ($score === null) return 'neutral';

        // Ensure score is between 0 and 1
        $score = max(0, min(1, $score));

        // Debug: Check what score we're getting
        if (app()->environment('local')) {
            Log::debug('getSentimentLabel called', ['score' => $score]);
        }

        if ($score >= 0.8) return 'very_positive';
        if ($score >= 0.6) return 'positive';
        if ($score >= 0.4) return 'neutral';
        if ($score >= 0.2) return 'negative';
        return 'very_negative';
    }
   
    

    public static  function getTopMentionedStaff($positiveReviews)
    {
        $staffMentions = [];

        foreach ($positiveReviews as $review) {
            if ($review->staff_id) {
                $staffMentions[$review->staff_id] = ($staffMentions[$review->staff_id] ?? 0) + 1;
            }
        }

        if (empty($staffMentions)) {
            return [];
        }

        arsort($staffMentions);

        $result = [];
        foreach (array_slice($staffMentions, 0, 3) as $staffId => $count) {
            $staff = User::find($staffId);
            if ($staff) {
                $result[] = $staff->name . " ({$count})";
            }
        }

        return $result;
    }



   public static function getStaffPerformanceSnapshot($businessId, $dateRange)
    {
        
        // Get staff reviews WITH calculated rating
        $staffReviews = ReviewNew::with('staff')
            ->where('business_id', $businessId)
            ->globalFilters(0, $businessId)
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
                'skill_gaps' => self::extractSkillGapsFromSuggestions($staff_suggestions),
                'recommended_training' => $staff_suggestions->first() ?? 'General Training',
                'last_review_date' => $reviews->sortByDesc('created_at')->first()->created_at->diffForHumans(),
                'rating_trend' => self::calculateStaffRatingTrend($reviews)
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
public static function extractSkillGapsFromSuggestions($suggestions)
    {
        return $suggestions
            ->filter(fn($s) => stripos($s, 'needs') !== false || stripos($s, 'requires') !== false)
            ->map(fn($s) => preg_replace('/.*needs\s+(.*?) training.*/i', '$1', $s))
            ->filter(fn($s) => strlen($s) > 3)
            ->values()
            ->toArray();
    }
  public static   function extractOpportunitiesFromSuggestions($suggestions)
    {
        return collect($suggestions)
            ->filter(fn($s) => stripos($s, 'add') !== false || stripos($s, 'highlight') !== false)
            ->take(2)
            ->values()
            ->toArray();
    }
   public static function generatePredictions($reviews)
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
  public static  function transcribeAudio($filePath)
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

  public static  function getAiInsightsPanel($businessId, $dateRange)
    {
        // Get reviews WITH calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('ai_suggestions')
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();

        // Extract common themes from existing AI suggestions
        $allSuggestions = $reviews->pluck('ai_suggestions')->flatten();
        $allTopics = $reviews->pluck('topics')->flatten();

        return [
            'summary' => self::generateAiSummary($reviews),
            'detected_issues' => self::extractIssuesFromSuggestions($allSuggestions),
            'opportunities' => self::extractOpportunitiesFromSuggestions($allSuggestions),
            'predictions' => self::generatePredictions($reviews)
        ];
    }

    /**
     * Get branch comparison data with real metrics
     */
   public static function getBranchComparisonData($branch, $startDate, $endDate)
    {
        $businessId = $branch->business_id;

        // Get reviews with calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branch->id)
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->get();

           
        $totalReviews = $reviews->count();

        // Average rating from calculated_rating
        $averageRating = $reviews->avg('calculated_rating') ?? 0;
        
        // AI Sentiment Score
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $aiSentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

        // CSAT Score
        $csatCount = $reviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) >= 4;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        // Staff performance metrics
        $staffPerformance = self::getBranchStaffPerformance($branch->id, $businessId, $startDate, $endDate);
    

        // Top topics
        $topTopics = self::extractBranchTopics($reviews);

        return [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code ?? 'BRN-' . str_pad($branch->id, 5, '0', STR_PAD_LEFT),
                'location' => $branch->location,
                'manager_name' => $branch->manager ? $branch->manager->name : 'Not assigned',
                'business_name' => $branch->business ? $branch->business->name : 'Unknown'
            ],
            'metrics' => [
                'total_reviews' => $totalReviews,
                'average_rating' => round($averageRating, 1),
                'ai_sentiment_score' => $aiSentimentScore,
                'csat_score' => $csatScore,
                'response_rate' => calculateResponseRate($reviews)
            ],
            'staff_performance' => $staffPerformance,
            'top_topics' => array_slice($topTopics, 0, 5)
        ];
    }

    /**
     * Get branch staff performance
     */
public static function getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate)
{
    // First get the reviews with calculated rating
    $staffReviews = ReviewNew::where('business_id', $businessId)
        ->where('branch_id', $branchId)
        ->globalFilters(0, $businessId, 1)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->withCalculatedRating()
        ->get();

    // Manual grouping since calculated_rating is not a real database column
    $groupedReviews = [];
    foreach ($staffReviews as $review) {
        if ($review->staff_id) {
            $groupedReviews[$review->staff_id][] = $review;
        }
    }
    
    $staffPerformance = [];

    foreach ($groupedReviews as $staffId => $reviews) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        // Manual calculations
        $totalRating = 0;
        $reviewCount = count($reviews);
        $positiveCount = 0;
        $latestReviewDate = null;
        
        foreach ($reviews as $review) {
            $totalRating += $review->calculated_rating ?? 0;
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveCount++;
            }
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
        }
        
        $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;

        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'avg_rating' => round($avgRating, 1),
            'reviews_count' => $reviewCount,
            'positive_percentage' => $reviewCount > 0 ? round(($positiveCount / $reviewCount) * 100) : 0,
            'last_review_date' => $latestReviewDate 
                ? $latestReviewDate->diffForHumans() 
                : 'No reviews'
        ];
    }

    // Sort by average rating descending
    usort($staffPerformance, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return array_slice($staffPerformance, 0, 3);
}

  public static  function extractBranchTopics($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            // Use stored topics if available
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment
            if ($review->comment) {
                $commonTopics = ['service', 'staff', 'wait', 'quality', 'price', 'clean', 'product', 'location'];
                $comment = strtolower($review->comment);

                foreach ($commonTopics as $topic) {
                    if (strpos($comment, $topic) !== false) {
                        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($topicCounts);
        return $topicCounts;
    }

      /**
     * Generate AI insights for branch comparison
     */
   public static function generateBranchComparisonInsights($branchesData, $allMetrics)
    {
        if (count($branchesData) === 0) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        // Find best performing branch by rating
        $bestBranch = null;
        $bestRating = 0;
        $mostReviews = 0;
        $mostReviewsBranch = null;

        foreach ($branchesData as $branchData) {
            $rating = $branchData['metrics']['average_rating'];
            $reviews = $branchData['metrics']['total_reviews'];

            if ($rating > $bestRating) {
                $bestRating = $rating;
                $bestBranch = $branchData['branch']['name'];
            }

            if ($reviews > $mostReviews) {
                $mostReviews = $reviews;
                $mostReviewsBranch = $branchData['branch']['name'];
            }
        }

        // Find worst performing branch by rating
        $worstBranch = null;
        $worstRating = 5;
        foreach ($branchesData as $branchData) {
            $rating = $branchData['metrics']['average_rating'];
            if ($rating < $worstRating && $branchData['metrics']['total_reviews'] > 0) {
                $worstRating = $rating;
                $worstBranch = $branchData['branch']['name'];
            }
        }

        // Generate overview
        $overview = "The {$bestBranch} branch consistently outperforms others in Average Rating ({$bestRating}) ";
        $overview .= "and CSAT ({$branchesData[array_search($bestBranch, array_column($branchesData, 'branch'))]['metrics']['csat_score']}%), ";
        $overview .= "driven by positive feedback on staff performance. ";

        if ($mostReviewsBranch !== $bestBranch) {
            $overview .= "The {$mostReviewsBranch} branch has the highest volume of reviews, ";
            $overview .= "indicating high traffic, but its sentiment score is slightly lower. ";
        }

        if ($worstBranch) {
            $overview .= "{$worstBranch} lags in all key metrics, suggesting a need for operational review, ";
            $overview .= "particularly in areas affecting customer sentiment.";
        }

        $keyFindings = [
            "Highest rating: {$bestBranch} ({$bestRating})",
            "Most reviews: {$mostReviewsBranch} ({$mostReviews})"
        ];

        if ($worstBranch) {
            $keyFindings[] = "Needs improvement: {$worstBranch}";
        }

        return [
            'overview' => $overview,
            'key_findings' => $keyFindings
        ];
    }
     /**
     * Generate comparison highlights table
     */
   public static function generateComparisonHighlights($branchesData)
    {
        if (count($branchesData) < 2) {
            return [];
        }

        $highlights = [];

        // CSAT comparison
        $bestCsat = 0;
        $bestCsatBranch = '';
        $worstCsat = 100;
        $worstCsatBranch = '';

        foreach ($branchesData as $branchData) {
            $csat = $branchData['metrics']['csat_score'];
            $branchName = $branchData['branch']['name'];

            if ($csat > $bestCsat) {
                $bestCsat = $csat;
                $bestCsatBranch = $branchName;
            }

            if ($csat < $worstCsat && $branchData['metrics']['total_reviews'] > 0) {
                $worstCsat = $csat;
                $worstCsatBranch = $branchName;
            }
        }

        $highlights[] = [
            'category' => 'CSAT',
            'best_branch' => $bestCsatBranch,
            'best_value' => "{$bestCsat}%",
            'worst_branch' => $worstCsatBranch,
            'worst_value' => "{$worstCsat}%"
        ];

        // Staff Performance complaints
        $mostComplaints = 0;
        $mostComplaintsBranch = '';
        $leastComplaints = PHP_INT_MAX;
        $leastComplaintsBranch = '';

        foreach ($branchesData as $branchData) {
            $totalReviews = $branchData['metrics']['total_reviews'];
            if ($totalReviews === 0) continue;

            // Calculate complaints percentage (negative sentiment reviews)
            $negativeReviews = 0;
            foreach ($branchData['staff_performance'] as $staff) {
                $negativeReviews += (100 - $staff['positive_percentage']) * $staff['reviews_count'] / 100;
            }
            $complaintPercentage = $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0;
            $branchName = $branchData['branch']['name'];

            if ($complaintPercentage > $mostComplaints) {
                $mostComplaints = $complaintPercentage;
                $mostComplaintsBranch = $branchName;
            }

            if ($complaintPercentage < $leastComplaints) {
                $leastComplaints = $complaintPercentage;
                $leastComplaintsBranch = $branchName;
            }
        }

        $highlights[] = [
            'category' => 'Staff Performance',
            'most_complaints' => $mostComplaintsBranch,
            'least_complaints' => $leastComplaintsBranch
        ];

        return $highlights;
    }

    /**
     * Get sentiment trend over time for chart
     */
   public static function getSentimentTrendOverTime($branches, $startDate, $endDate)
    {
        // Group by month for the trend
        $months = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $months[] = $current->format('M Y');
            $current->addMonth();
        }

        $trendData = [];

        foreach ($branches as $branch) {
            $branchTrend = [];

            $current = $startDate->copy();
            while ($current <= $endDate) {
                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();

                $reviews = ReviewNew::where('business_id', $branch->business_id)
                    ->where('branch_id', $branch->id)
                    ->globalFilters(0, $branch->business_id)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->withCalculatedRating()
                    ->get();

                $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
                $totalReviews = $reviews->count();
                $sentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

                $branchTrend[] = $sentimentScore;
                $current->addMonth();
            }

            $trendData[] = [
                'branch_name' => $branch->name,
                'data' => $branchTrend
            ];
        }

        return [
            'months' => $months,
            'trends' => $trendData
        ];
    }

    /**
     * Get staff complaints by branch
     */
   public static function getStaffComplaintsByBranch($branches, $startDate, $endDate)
    {
        $complaintsByBranch = [];

        foreach ($branches as $branch) {
            $reviews = ReviewNew::where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->globalFilters(0, $branch->business_id, 1)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->withCalculatedRating()
                ->get();

            $negativeReviews = $reviews->where('sentiment_score', '<', 0.4)->count();
            $totalReviews = $reviews->count();

            $complaintsByBranch[] = [
                'branch_name' => $branch->name,
                'complaints_count' => $negativeReviews,
                'total_reviews' => $totalReviews,
                'complaint_percentage' => $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0
            ];
        }

        // Sort by complaint percentage descending
        usort($complaintsByBranch, function ($a, $b) {
            return $b['complaint_percentage'] <=> $a['complaint_percentage'];
        });

        return $complaintsByBranch;
    }

     /**
     * Calculate branch summary metrics
     */
   public static function calculateBranchSummary($reviews)
    {
        $totalReviews = $reviews->count();

        // Use calculated_rating instead of separate rating calculation
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        // AI Sentiment
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $sentiment = 'Neutral';

        if ($totalReviews > 0) {
            $positivePercentage = ($positiveReviews / $totalReviews) * 100;
            if ($positivePercentage >= 70) {
                $sentiment = 'Positive';
            } elseif ($positivePercentage <= 30) {
                $sentiment = 'Negative';
            }
        }

        // CSAT Score (percentage of 4-5 star ratings)
        $csatCount = $reviews->filter(function ($review) {
            return ($review->calculated_rating ?? 0) >= 4;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        // Top Topic (from review topics or extract from comments)
        $topTopic = self::extractTopTopic($reviews);

        // Flagged reviews
        $flagged = $reviews->where('status', 'flagged')->count();

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => round($averageRating, 1),
            'rating_out_of' => 5,
            'ai_sentiment' => $sentiment,
            'csat_score' => $csatScore,
            'top_topic' => $topTopic['topic'] ?? 'General',
            'flagged' => $flagged,
            'response_rate' => calculateResponseRate($reviews)
        ];
    }
   public static  function extractTopTopic($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            // Use stored topics if available
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment
            if ($review->comment) {
                $commonTopics = ['service', 'staff', 'wait', 'quality', 'price', 'clean', 'product', 'location'];
                $comment = strtolower($review->comment);

                foreach ($commonTopics as $topic) {
                    if (strpos($comment, $topic) !== false) {
                        $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                    }
                }
            }
        }

        if (empty($topicCounts)) {
            return ['topic' => 'General', 'count' => 0];
        }

        arsort($topicCounts);
        $topTopic = array_key_first($topicCounts);

        return [
            'topic' => ucfirst($topTopic),
            'count' => $topicCounts[$topTopic],
            'percentage' => $reviews->count() > 0 ? round(($topicCounts[$topTopic] / $reviews->count()) * 100, 1) : 0
        ];
    }
   public static function generateAiInsights($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                'summary' => 'No reviews available for analysis.',
                'sentiment_breakdown' => [
                    'positive' => 0,
                    'neutral' => 0,
                    'negative' => 0
                ]
            ];
        }

        $totalReviews = $reviews->count();

        // Sentiment breakdown
        $positive = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negative = $reviews->where('sentiment_score', '<', 0.4)->count();

        $sentimentBreakdown = [
            'positive' => round(($positive / $totalReviews) * 100),
            'neutral' => round(($neutral / $totalReviews) * 100),
            'negative' => round(($negative / $totalReviews) * 100)
        ];

        // Generate summary
        $summary = self::generateAiSummaryReport($reviews, $sentimentBreakdown);

        return [
            'summary' => $summary,
            'sentiment_breakdown' => $sentimentBreakdown,
            'key_trends' => self::extractKeyTrends($reviews)
        ];
    }
   public static function generateAiSummaryReport($reviews, $sentimentBreakdown)
    {
        $totalReviews = $reviews->count();
        $positivePercentage = $sentimentBreakdown['positive'];

        $summary = "Overall sentiment is ";

        if ($positivePercentage >= 70) {
            $summary .= "highly positive";
        } elseif ($positivePercentage >= 50) {
            $summary .= "generally positive";
        } elseif ($positivePercentage >= 30) {
            $summary .= "mixed";
        } else {
            $summary .= "predominantly negative";
        }

        $summary .= ", with {$positivePercentage}% of reviews expressing positive sentiment. ";

        // Calculate average rating
        $avgRating = $reviews->avg('calculated_rating') ?? 0;
        $summary .= "The average rating is " . round($avgRating, 1) . " out of 5. ";

        // Check for common issues
        $commonIssues = self::findCommonIssues($reviews);
        if (!empty($commonIssues)) {
            $summary .= "A recurring issue mentioned is " . $commonIssues[0]['topic'] . ". ";
        }

        // Check for peak times if available
        $peakTimes = self::findPeakReviewTimes($reviews);
        if ($peakTimes) {
            $summary .= "Peak feedback times are around {$peakTimes}. ";
        }

        return trim($summary);
    }
     /**
     * Extract key trends from reviews
     */
   public static function extractKeyTrends($reviews)
    {
        $trends = [];

        if ($reviews->isEmpty()) {
            return $trends;
        }

        // Check for improving/declining sentiment over time
        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstSentiment = $firstHalf->avg('sentiment_score');
        $secondSentiment = $secondHalf->avg('sentiment_score');

        if ($secondSentiment > $firstSentiment + 0.1) {
            $trends[] = 'Improving sentiment trend';
        } elseif ($secondSentiment < $firstSentiment - 0.1) {
            $trends[] = 'Declining sentiment trend';
        }

        // Check for specific issue trends
        $commonIssues = self::findCommonIssues($reviews);
        foreach ($commonIssues as $issue) {
            if ($issue['count'] >= 5) {
                $trends[] = "Frequent mentions of " . $issue['topic'];
            }
        }

        return array_slice($trends, 0, 3);
    }
     /**
     * Find common issues in reviews
     */
   public static function findCommonIssues($reviews)
    {
        $issues = [
            'Wait Time' => [
                'keywords' => ['wait', 'queue', 'line', 'slow', 'long', 'minutes', 'delay', 'time', 'late', 'patient', 'standing'],
                'description' => 'Customers mentioned longer than expected wait times'
            ],
            'Service Quality' => [
                'keywords' => ['rude', 'unhelpful', 'ignore', 'attitude', 'unprofessional', 'careless', 'inattentive', 'poor service'],
                'description' => 'Service quality needs improvement'
            ],
            'Cleanliness' => [
                'keywords' => ['dirty', 'messy', 'filthy', 'clean', 'hygiene', 'sanitary', 'untidy', 'stain', 'smell', 'wipe'],
                'description' => 'Cleanliness and maintenance concerns'
            ],
            'Pricing' => [
                'keywords' => ['expensive', 'pricey', 'overpriced', 'cost', 'value', 'worth', 'cheap', 'affordable', 'budget'],
                'description' => 'Pricing or value for money concerns'
            ],
            'Food Quality' => [
                'keywords' => ['taste', 'flavor', 'fresh', 'stale', 'cold', 'hot', 'cooked', 'raw', 'quality', 'bland', 'dry'],
                'description' => 'Food or product quality issues'
            ],
            'Ambiance' => [
                'keywords' => ['noisy', 'loud', 'quiet', 'atmosphere', 'music', 'lighting', 'crowded', 'small', 'uncomfortable'],
                'description' => 'Ambiance or environment feedback'
            ]
        ];

        $results = [];

        foreach ($reviews as $review) {
            if (empty($review->comment)) continue;

            $comment = strtolower(trim($review->comment));

            foreach ($issues as $topic => $data) {
                foreach ($data['keywords'] as $keyword) {
                    if (strpos($comment, $keyword) !== false) {
                        // Initialize if not exists
                        if (!isset($results[$topic])) {
                            $results[$topic] = [
                                'topic' => $topic,
                                'count' => 0,
                                'description' => $data['description'],
                                'keyword_matches' => []
                            ];
                        }

                        $results[$topic]['count']++;
                        if (!in_array($keyword, $results[$topic]['keyword_matches'])) {
                            $results[$topic]['keyword_matches'][] = $keyword;
                        }
                        break; // Count once per topic per review
                    }
                }
            }
        }

        // Convert to array and sort by count
        $sortedResults = array_values($results);
        usort($sortedResults, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $sortedResults;
    }
     /**
     * Find peak review times
     */
  public static  function findPeakReviewTimes($reviews)
    {
        if ($reviews->isEmpty()) return null;

        $hourlyCounts = array_fill(0, 24, 0);

        foreach ($reviews as $review) {
            $hour = $review->created_at->hour;
            $hourlyCounts[$hour]++;
        }

        $peakHour = array_search(max($hourlyCounts), $hourlyCounts);

        return sprintf('%02d:00', $peakHour);
    }
    /**
     * Generate recommendations based on review analysis
     */
   public static function generateBranchRecommendations($reviews)
    {
        $recommendations = [];
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                [
                    'type' => 'Info',
                    'title' => 'No Data Available',
                    'description' => 'No reviews found for this period. Encourage customers to provide feedback.'
                ]
            ];
        }

        // Track why recommendations might be empty
        $debugInfo = [
            'total_reviews' => $totalReviews,
            'positive_reviews' => 0,
            'has_comments' => 0,
            'staff_praise_count' => 0,
            'issues_found' => 0
        ];

        // 1. Identify strengths (positive reviews with specific praise)
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7);
        $debugInfo['positive_reviews'] = $positiveReviews->count();

        // Check how many reviews have comments
        $reviewsWithComments = $reviews->filter(function ($review) {
            return !empty(trim($review->comment ?? ''));
        });
        $debugInfo['has_comments'] = $reviewsWithComments->count();

        // Enhanced staff praise detection
        $staffPraise = $positiveReviews->filter(function ($review) {
            if (empty($review->comment)) return false;

            $text = strtolower(trim($review->comment));

            // Comprehensive staff and service keywords
            $staffKeywords = [
                // Staff roles
                'staff',
                'employee',
                'waiter',
                'waitress',
                'server',
                'host',
                'hostess',
                'bartender',
                'chef',
                'cook',
                'manager',
                'crew',
                'team',
                'personnel',
                'assistant',
                'attendant',
                'rep',
                'representative',
                'agent',
                'worker',
                'cashier',
                'receptionist',
                'front desk',
                'service',
                'person',

                // Positive service attributes
                'friendly',
                'helpful',
                'knowledgeable',
                'professional',
                'expert',
                'courteous',
                'polite',
                'respectful',
                'welcoming',
                'warm',
                'attentive',
                'caring',
                'thoughtful',
                'considerate',
                'efficient',
                'quick',
                'fast',
                'prompt',
                'timely',
                'smile',
                'smiling',
                'kind',
                'nice',
                'great',
                'excellent',
                'outstanding',
                'amazing',
                'fantastic',
                'wonderful',
                'patient',
                'understanding',
                'accommodating',
                'recommend',
                'suggest',
                'advise',
                'explain',
                'solve',
                'resolve',
                'fix',
                'handle',
                'manage',
                'go above',
                'above and beyond',
                'extra mile'
            ];

            // Check for any staff keyword
            foreach ($staffKeywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    // Quick check for obvious negations
                    if (
                        strpos($text, "not $keyword") !== false ||
                        strpos($text, "no $keyword") !== false ||
                        strpos($text, "never $keyword") !== false
                    ) {
                        continue;
                    }
                    return true;
                }
            }

            return false;
        });

        $debugInfo['staff_praise_count'] = $staffPraise->count();

        if ($staffPraise->count() >= 2) {
            // Get top mentioned staff and include in description
            $staffMentions = self::getTopMentionedStaff($staffPraise);
            $staffDescription = $staffMentions ?
                ' Top performing staff: ' . implode(', ', array_slice($staffMentions, 0, 2)) . '.' :
                '';

            $recommendations[] = [
                'type' => 'Strength',
                'title' => 'Staff Excellence',
                'description' => 'Customers appreciate your staff\'s service and professionalism.' . $staffDescription,
                'evidence_count' => $staffPraise->count(),
                'priority' => 'low'
            ];
        }

        // 2. Identify common issues
        $issues = self::findCommonIssues($reviews);
        $debugInfo['issues_found'] = count($issues);

        foreach ($issues as $issue) {
            if ($issue['count'] >= 2 && count($recommendations) < 3) {
                $recommendations[] = [
                    'type' => 'Weak Area',
                    'title' => $issue['topic'],
                    'description' => $issue['description'] . " (mentioned {$issue['count']} times)",
                    'evidence_count' => $issue['count'],
                    'priority' => $issue['count'] >= 4 ? 'high' : 'medium'
                ];

                // Add action item for this issue
                $action = self::generateActionItem($issue['topic'], $issue['count']);
                if ($action && count($recommendations) < 3) {
                    $recommendations[] = $action;
                }
            }
        }

        // 3. If no recommendations found, provide debug info
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'Info',
                'title' => 'Insufficient Feedback Data',
                'description' => 'Not enough specific feedback to generate recommendations. ' .
                    "Total reviews: {$debugInfo['total_reviews']}, " .
                    "With comments: {$debugInfo['has_comments']}, " .
                    "Positive reviews: {$debugInfo['positive_reviews']}, " .
                    "Staff praise mentions: {$debugInfo['staff_praise_count']}, " .
                    "Issues detected: {$debugInfo['issues_found']}",
                'debug_info' => $debugInfo
            ];
        }

        // Limit to 3 recommendations max
        return array_slice($recommendations, 0, 3);
    }
     /**
     * Get recent reviews for display
     */
  public static  function getRecentReviews($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $rating = $review->calculated_rating ?? $review->rate;

                return [
                    'id' => $review->id,
                    'rating' => $rating,
                    'stars' => str_repeat('★', floor($rating)) . str_repeat('☆', 5 - floor($rating)),
                    'review_text' => $review->comment ?? $review->raw_text ?? 'No comment',
                    'staff_name' => $review->staff ? $review->staff->name : 'Not assigned',
                    'staff_id' => $review->staff_id,
                    'sentiment' => self::getSentimentLabel($review->sentiment_score),
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
     * Get staff performance data
     */
 public static function getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 5)
{
    // Get reviews with staff assigned AND calculated rating in one query
    $staffReviews = ReviewNew::where('business_id', $businessId)
        ->where('branch_id', $branchId)
        ->globalFilters(0, $businessId, 1)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$startDate, $endDate])
        ->withCalculatedRating()
        ->get();

    $staffPerformance = [];
    
    // Group reviews by staff_id manually since we can't use group By with eager loading
    $groupedReviews = [];
    foreach ($staffReviews as $review) {
        if ($review->staff_id) {
            $groupedReviews[$review->staff_id][] = $review;
        }
    }

    foreach ($groupedReviews as $staffId => $reviews) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        // Calculate average manually from the reviews collection
        $totalRating = 0;
        $reviewCount = count($reviews);
        $positiveReviews = 0;
        $latestReviewDate = null;
        
        foreach ($reviews as $review) {
            $totalRating += $review->calculated_rating ?? 0;
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveReviews++;
            }
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
        }
        
        $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;

        // Skip staff with very few reviews
        if ($reviewCount < 3) continue;

        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'staff_code' => $staff->employee_code ?? 'EMP-' . $staffId,
            'avg_rating' => round($avgRating, 1),
            'rating_out_of' => 5,
            'reviews_count' => $reviewCount,
            'ai_evaluation' => self::getStaffEvaluation($avgRating, $reviewCount),
            'has_profile' => true,
            'positive_percentage' => $reviewCount > 0 ? round(($positiveReviews / $reviewCount) * 100) : 0,
            'last_review_date' => $latestReviewDate ? $latestReviewDate->diffForHumans() : 'Never'
        ];
    }

    // Sort by average rating descending
    usort($staffPerformance, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return array_slice($staffPerformance, 0, $limit);
}
 public static    function getStaffEvaluation($avgRating, $reviewCount)
    {
        if ($reviewCount < 3) return 'Insufficient Data';
        if ($avgRating >= 4.5) return 'Top Performer';
        if ($avgRating >= 4.0) return 'Excellent';
        if ($avgRating >= 3.5) return 'Good';
        if ($avgRating >= 3.0) return 'Consistent';
        if ($avgRating >= 2.0) return 'Needs Improvement';
        return 'Critical Attention';
    }

    /**
     * Generate action item based on issue
     */
   public static function generateActionItem($issue, $evidenceCount)
    {
        $actions = [
            'Wait Time' => [
                'title' => 'Optimize Service Flow',
                'description' => 'Review staffing schedules during peak hours and implement queue management.',
                'priority' => $evidenceCount >= 4 ? 'high' : 'medium'
            ],
            'Service Quality' => [
                'title' => 'Service Training',
                'description' => 'Provide customer service training focusing on communication and attentiveness.',
                'priority' => 'medium'
            ],
            'Cleanliness' => [
                'title' => 'Cleanliness Protocol',
                'description' => 'Establish regular cleaning schedules and quality checks.',
                'priority' => 'medium'
            ],
            'Pricing' => [
                'title' => 'Value Assessment',
                'description' => 'Review pricing strategy and ensure clear value communication.',
                'priority' => 'low'
            ],
            'Food Quality' => [
                'title' => 'Quality Control',
                'description' => 'Implement stricter quality checks and preparation standards.',
                'priority' => 'high'
            ],
            'Ambiance' => [
                'title' => 'Environment Improvement',
                'description' => 'Assess and improve lighting, noise levels, and seating comfort.',
                'priority' => 'low'
            ]
        ];

        if (isset($actions[$issue])) {
            return [
                'type' => 'Action',
                'title' => $actions[$issue]['title'],
                'description' => $actions[$issue]['description'],
                'priority' => $actions[$issue]['priority']
            ];
        }

        return null;
    }

    public static  function calculateStaffRatingTrend($reviews)
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
   


  public static  function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
         return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => 0,
            'avg_rating' => 0,
            'sentiment_breakdown' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0
            ],
            'performance_by_category' => [],
            'top_topics' => [],
            'notable_reviews' => []
        ];
        }

        // Calculate average rating from calculated_rating field
        $avgRating = $reviews->avg('calculated_rating') ?? 0;

        // Calculate sentiment distribution
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        $positivePercentage = round(($positiveCount / $totalReviews) * 100);
        $neutralPercentage = round(($neutralCount / $totalReviews) * 100);
        $negativePercentage = round(($negativeCount / $totalReviews) * 100);

        // Extract topics and categories
        $topics = self::extractTopicsFromReviews($reviews);
        $performanceByCategory = self::calculatePerformanceByCategory($reviews);
        $notableReviews = self::getNotableReviews($reviews);

        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => $totalReviews,
            'avg_rating' => round($avgRating, 1),
            'sentiment_breakdown' => [
                'positive' => $positivePercentage,
                'neutral' => $neutralPercentage,
                'negative' => $negativePercentage
            ],
            'performance_by_category' => $performanceByCategory,
            'top_topics' => array_slice($topics, 0, 5),
            'notable_reviews' => $notableReviews
        ];
    }
   public static  function extractTopicsFromReviews($reviews)
    {
        $allTopics = [];

        foreach ($reviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }
  public static  function calculatePerformanceByCategory($reviews)
    {
        $categories = [
            'friendliness' => ['friendly', 'polite', 'rude', 'attitude', 'nice'],
            'efficiency' => ['slow', 'fast', 'efficient', 'wait', 'time'],
            'knowledge' => ['knowledge', 'explain', 'information', 'helpful', 'expert']
        ];

        $performance = [];

        foreach ($categories as $category => $keywords) {
            $categoryReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($categoryReviews->count() > 0) {
                $avgSentiment = $categoryReviews->avg('sentiment_score');
                $performance[$category] = [
                    'score' => round($avgSentiment * 100),
                    'review_count' => $categoryReviews->count()
                ];
            } else {
                $performance[$category] = [
                    'score' => 0,
                    'review_count' => 0
                ];
            }
        }

        return $performance;
    }
   public static  function getNotableReviews($reviews, $limit = 2)
    {
        return $reviews->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                return [
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans()
                ];
            })
            ->values()
            ->toArray();
    }
   public static function getSentimentGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A has more positive reviews";
        } elseif ($gap < 0) {
            return "Staff B has more positive reviews";
        } else {
            return "Both have similar positive sentiment";
        }
    }
  public static   function getPreviousPeriodReviews($businessId, $period)
    {
        $startDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->startOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->startOfQuarter(),
            default => Carbon::now()->subMonth()->startOfMonth() // last_month
        };

        $endDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->endOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->endOfQuarter(),
            default => Carbon::now()->subMonth()->endOfMonth()
        };

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereNotNull('sentiment_score')
             ->globalFilters(0, $businessId)

            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)


            ->withCalculatedRating()

            ->get();
    }
 public static    function calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews)
    {
        // Calculate current period average rating from calculated_rating field
        $currentAvgRating = $currentReviews->isNotEmpty()
            ? round($currentReviews->avg('calculated_rating'), 1)
            : 0;

        // Calculate previous period average rating from calculated_rating field
        $previousAvgRating = $previousReviews->isNotEmpty()
            ? round($previousReviews->avg('calculated_rating'), 1)
            : 0;

        $currentSentiment = self::calculateAverageSentiment($currentReviews);
        $currentTotalReviews = $currentReviews->count();

        $previousSentiment = self::calculateAverageSentiment($previousReviews);
        $previousTotalReviews = $previousReviews->count();

        $ratingChange = $previousAvgRating > 0 ?
            round((($currentAvgRating - $previousAvgRating) / $previousAvgRating) * 100, 1) : 0;

        $sentimentChange = $previousSentiment > 0 ?
            round($currentSentiment - $previousSentiment, 1) : 0;

        $reviewsChange = $previousTotalReviews > 0 ?
            $currentTotalReviews - $previousTotalReviews : $currentTotalReviews;

        return [
            'overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $ratingChange,
                'change_type' => $ratingChange >= 0 ? 'positive' : 'negative'
            ],
            'overall_sentiment' => [
                'value' => $currentSentiment,
                'change' => $sentimentChange,
                'change_type' => $sentimentChange >= 0 ? 'positive' : 'negative'
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'change' => $reviewsChange,
                'change_type' => $reviewsChange >= 0 ? 'positive' : 'negative'
            ]
        ];
    }
   public static function calculateAverageSentiment($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        return round(($positiveReviews / $reviews->count()) * 100);
    }
  public static  function extractStaffTopics($staffReviews)
    {
        $allTopics = [];

        foreach ($staffReviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }

            // Also extract from comment if no topics set
            if (empty($review->topics) && $review->comment) {
                $commonWords = ['service', 'friendly', 'helpful', 'knowledge', 'slow', 'fast', 'polite', 'rude'];
                $comment = strtolower($review->comment);

                foreach ($commonWords as $word) {
                    if (strpos($comment, $word) !== false) {
                        $allTopics[$word] = ($allTopics[$word] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }
     /**
     * Get top three staff based on ratings and review count
     */
   public static function getTopThreeStaff($businessId, $filters = [])
{
    // Get reviews for the business with staff AND calculated rating
    $reviewsQuery = ReviewNew::where('business_id', $businessId)
        ->whereNotNull('staff_id')
         ->globalFilters(0, $businessId)
        ->withCalculatedRating();

    // Apply the same filters as main query
    $reviewsQuery = applyFilters($reviewsQuery, $filters);

    // Add calculated rating to the query
    $reviews = $reviewsQuery->get();

    if ($reviews->isEmpty()) {
        return [
            'message' => 'No staff reviews found',
            'staff' => []
        ];
    }

    // Manual grouping by staff_id
    $staffGroups = [];
    foreach ($reviews as $review) {
        if ($review->staff_id) {
            $staffGroups[$review->staff_id][] = $review;
        }
    }

    $staffPerformance = [];
    
    foreach ($staffGroups as $staffId => $reviewsArray) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        $totalRating = 0;
        $totalReviews = count($reviewsArray);
        $positiveCount = 0;
        $latestReviewDate = null;
        $allTopics = [];
        
        foreach ($reviewsArray as $review) {
            // Calculate average rating
            $totalRating += $review->calculated_rating ?? 0;
            
            // Count positive reviews
            if (isset($review->sentiment_score) && $review->sentiment_score >= 0.7) {
                $positiveCount++;
            }
            
            // Track latest review
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
            
            // Collect topics if they exist
            if (!empty($review->topics) && is_array($review->topics)) {
                $allTopics = array_merge($allTopics, $review->topics);
            }
        }
        
        // Calculate averages
        $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
        $sentimentPercentage = $totalReviews > 0 ? round(($positiveCount / $totalReviews) * 100) : 0;
        
        // Only include staff with at least 5 reviews
        if ($totalReviews < 5) {
            continue;
        }
        
        // Extract common topics
        $topTopics = self::extractStaffTopics(collect($reviewsArray));
        
        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'position' => $staff->job_title ?? 'Staff',
            'image' => $staff->image ?? null,
            'avg_rating' => round($avgRating, 1),
            'review_count' => $totalReviews,
            'sentiment_score' => $sentimentPercentage,
            'sentiment_label' => self::getSentimentLabelByPercentage($sentimentPercentage),
            'top_topics' => array_slice($topTopics, 0, 3), // Top 3 topics
            'recent_activity' => $latestReviewDate 
                ? $latestReviewDate->diffForHumans() 
                : 'No recent activity'
        ];
    }

    // Sort by rating, then by review count
    usort($staffPerformance, function ($a, $b) {
        if ($b['avg_rating'] == $a['avg_rating']) {
            return $b['review_count'] <=> $a['review_count'];
        }
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    // Take top 3
    $staffPerformance = array_slice($staffPerformance, 0, 3);

    return [
        'total_staff_reviewed' => count($staffGroups),
        'staff' => $staffPerformance
    ];
}





  public static  function calculatePerformanceOverviewFromReviewValue($reviews)
    {
        if ($reviews instanceof Builder) {
            $reviews = $reviews->get(); // convert to Collection
        }

        $totalSubmissions = $reviews->count();

        $averageScore = $totalSubmissions > 0
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        // Fix date comparisons
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        return [
            'total_submissions' => $totalSubmissions,
            'average_score' => $averageScore,
            'score_out_of' => 5,
            'sentiment_distribution' => [
                'positive' => $totalSubmissions > 0 ? round(($positiveCount / $totalSubmissions) * 100) : 0,
                'neutral' => $totalSubmissions > 0 ? round(($neutralCount / $totalSubmissions) * 100) : 0,
                'negative' => $totalSubmissions > 0 ? round(($negativeCount / $totalSubmissions) * 100) : 0
            ],
            'submissions_today' => $reviews->filter(function ($review) use ($today) {
                return $review->created_at->isSameDay($today);
            })->count(),
            'submissions_this_week' => $reviews->filter(function ($review) use ($startOfWeek, $endOfWeek) {
                return $review->created_at->between($startOfWeek, $endOfWeek);
            })->count(),
            'submissions_this_month' => $reviews->filter(function ($review) use ($startOfMonth, $endOfMonth) {
                return $review->created_at->between($startOfMonth, $endOfMonth);
            })->count(),
            'guest_reviews_count' => $reviews->whereNotNull('guest_id')->count(),
            'user_reviews_count' => $reviews->whereNotNull('user_id')->count(),
            // 'overall_reviews_count' => $reviews->where('is_overall', 1)->count(),
            // 'survey_reviews_count' => $reviews->whereNotNull('survey_id')->count()
        ];
    }
  public static  function getReviewSamples($reviews, $limit = 2)
    {
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)
            ->sortByDesc('created_at')
            ->take($limit);

        $constructiveReviews = $reviews->whereBetween('sentiment_score', [0.4, 0.69])
            ->sortByDesc('created_at')
            ->take($limit);

        $negativeReviews = $reviews->where('sentiment_score', '<', 0.4)
            ->sortByDesc('created_at')
            ->take($limit);

        return [
            'positive' => $positiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'constructive' => $constructiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'neutral' => $negativeReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray()
        ];
    }
public static function getSubmissionsOverTime($reviews, $period)
{
    $endDate = Carbon::now();
    $startDate = match ($period) {
        '7d' => Carbon::now()->subDays(7),
        '90d' => Carbon::now()->subDays(90),
        '1y' => Carbon::now()->subYear(),
        default => Carbon::now()->subDays(30) // 30d
    };
    
    $groupFormat = match ($period) {
        '7d' => 'd-m-Y', // Daily for 7 days
        '90d', '1y' => 'm-Y', // Monthly for 90 days and 1 year
        default => 'd-m-Y' // Daily for 30 days
    };

    // Check if $reviews is a Builder instance and execute the query
    if ($reviews instanceof \Illuminate\Database\Eloquent\Builder) {
        $reviews = $reviews->get();
    }
    
    // Now convert to array for consistent handling
    $reviewsArray = is_array($reviews) ? $reviews : $reviews->toArray();

    // Filter reviews manually
    $filteredReviews = [];
    foreach ($reviewsArray as $review) {
        // Handle both array and object access (in case toArray() didn't convert nested objects)
        $createdAt = is_array($review) 
            ? ($review['created_at'] ?? null) 
            : ($review->created_at ?? null);
            
        if (!$createdAt) continue;
        
        $reviewDate = Carbon::parse($createdAt);
        if ($reviewDate->between($startDate, $endDate)) {
            $filteredReviews[] = $review;
        }
    }

    // Manual grouping by period
    $submissionsByPeriod = [];
    foreach ($filteredReviews as $review) {
        // Handle both array and object access
        $createdAt = is_array($review) 
            ? ($review['created_at'] ?? null) 
            : ($review->created_at ?? null);
            
        if (!$createdAt) continue;
        
        $periodKey = Carbon::parse($createdAt)->format($groupFormat);
        
        if (!isset($submissionsByPeriod[$periodKey])) {
            $submissionsByPeriod[$periodKey] = [
                'total_rating' => 0,
                'total_sentiment' => 0,
                'count' => 0
            ];
        }
        
        // Get rating and sentiment
        $rating = is_array($review) 
            ? ($review['rate'] ?? 0) 
            : ($review->rate ?? 0);
            
        $sentiment = is_array($review) 
            ? ($review['sentiment_score'] ?? 0) 
            : ($review->sentiment_score ?? 0);
        
        $submissionsByPeriod[$periodKey]['total_rating'] += $rating;
        $submissionsByPeriod[$periodKey]['total_sentiment'] += $sentiment;
        $submissionsByPeriod[$periodKey]['count']++;
    }

    // Format the data with manual calculations
    $formattedData = [];
    $peakSubmissions = 0;
    
    foreach ($submissionsByPeriod as $periodKey => $data) {
        $count = $data['count'];
        $avgRating = $count > 0 ? $data['total_rating'] / $count : 0;
        $avgSentiment = $count > 0 ? $data['total_sentiment'] / $count : 0;
        
        $formattedData[$periodKey] = [
            'submissions_count' => $count,
            'average_rating' => round($avgRating, 1),
            'sentiment_score' => round($avgSentiment * 100, 1)
        ];
        
        if ($count > $peakSubmissions) {
            $peakSubmissions = $count;
        }
    }

    // Fill in missing periods
    $filledData = fillMissingPeriods($formattedData, $startDate, $endDate, $groupFormat);

    return [
        'period' => $period,
        'data' => $filledData,
        'total_submissions' => count($filteredReviews),
        'peak_submissions' => $peakSubmissions,
        'date_range' => [
            'start' => $startDate->format('d-m-Y'),
            'end' => $endDate->format('d-m-Y')
        ]
    ];
}



  public static  function getRecentSubmissions($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $userName = getUserName($review);

                return [
                    'review_id' => $review->id,
                    'user_name' => $userName,
                    'rating' => $review->rate,
                    'comment' => $review->comment,
                    'submission_date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('d-m-Y H:i:s'),
                    'is_guest' => !is_null($review->guest_id),
                    'is_overall' => (bool)$review->is_overall,
                    'sentiment_score' => $review->sentiment_score,
                    'survey_name' => $review->survey ? $review->survey->name : null,
                    'staff_name' => $review->staff ? $review->staff->name : null,
                    "calculated_rating" => $review->calculated_rating ?? null,
                ];
            })
            ->values()
            ->toArray();
    }
  public static   function getRatingGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A is performing better";
        } elseif ($gap < 0) {
            return "Staff B is performing better";
        } else {
            return "Both staff are performing equally";
        }
    }
   public static  function getRecommendedTraining($reviews)
    {
        $trainingRecommendations = [];

        // Analyze reviews for training needs
        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Check for conflict resolution needs
        if (strpos($textLower, 'escalat') !== false || strpos($textLower, 'conflict') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Advanced Conflict Resolution',
                'description' => 'Recommended based on feedback regarding complex customer escalations.',
                'priority' => 'high',
                'category' => 'communication'
            ];
        }

        // Check for technical knowledge gaps
        if (strpos($textLower, 'technical') !== false || strpos($textLower, 'knowledge') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Technical Product Training',
                'description' => 'Recommended to improve product knowledge and technical expertise.',
                'priority' => 'medium',
                'category' => 'knowledge'
            ];
        }

        // Check for upselling opportunities
        if (strpos($textLower, 'upsell') !== false || strpos($textLower, 'recommend') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Sales and Upselling Techniques',
                'description' => 'Recommended to enhance sales skills and product recommendation abilities.',
                'priority' => 'medium',
                'category' => 'sales'
            ];
        }

        // Default training if no specific needs detected
        if (empty($trainingRecommendations)) {
            $trainingRecommendations[] = [
                'title' => 'Customer Service Excellence',
                'description' => 'General customer service skills enhancement.',
                'priority' => 'low',
                'category' => 'communication'
            ];
        }

        return $trainingRecommendations;
    }
  public static   function analyzeSkillGaps($reviews)
    {
        $strengths = [];
        $improvement_areas = [];

        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Analyze strengths
        if (strpos($textLower, 'communicat') !== false || strpos($textLower, 'explain') !== false) {
            $strengths[] = 'Communication';
        }
        if (strpos($textLower, 'solve') !== false || strpos($textLower, 'resolve') !== false) {
            $strengths[] = 'Problem Solving';
        }
        if (strpos($textLower, 'patient') !== false) {
            $strengths[] = 'Patience';
        }
        if (strpos($textLower, 'professional') !== false) {
            $strengths[] = 'Professionalism';
        }

        // Analyze improvement areas
        if (strpos($textLower, 'technical') !== false && strpos($textLower, 'know') === false) {
            $improvement_areas[] = 'Technical Knowledge';
        }
        if (strpos($textLower, 'upsell') !== false) {
            $improvement_areas[] = 'Upselling';
        }
        if (strpos($textLower, 'slow') !== false) {
            $improvement_areas[] = 'Process Efficiency';
        }

        // Remove duplicates
        $strengths = array_unique($strengths);
        $improvement_areas = array_unique($improvement_areas);

        return [
            'strengths' => array_values($strengths),
            'improvement_areas' => array_values($improvement_areas)
        ];
    }
  public static   function calculateCustomerTone($reviews)
    {
        $toneMetrics = [
            'friendliness' => ['friendly', 'nice', 'kind', 'pleasant', 'warm'],
            'patience' => ['patient', 'calm', 'understanding', 'tolerant'],
            'professionalism' => ['professional', 'expert', 'knowledgeable', 'competent']
        ];

        $results = [];

        foreach ($toneMetrics as $tone => $keywords) {
            $matchingReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($matchingReviews->count() > 0) {
                $positiveMatches = $matchingReviews->where('sentiment_score', '>=', 0.7)->count();
                $percentage = round(($positiveMatches / $matchingReviews->count()) * 100);
            } else {
                $percentage = 0;
            }

            $results[$tone] = $percentage;
        }

        return $results;
    }
   public static   function calculateSentimentDistribution($reviews)
    {
        $total = $reviews->count();

        if ($total === 0) {
            return ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $positive = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negative = $reviews->where('sentiment_score', '<', 0.4)->count();

        return [
            'positive' => round(($positive / $total) * 100),
            'neutral' => round(($neutral / $total) * 100),
            'negative' => round(($negative / $total) * 100)
        ];
    }
  public static   function calculateComplimentRatio($reviews)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                'compliments_percentage' => 0,
                'complaints_percentage' => 0,
                'compliments_count' => 0,
                'complaints_count' => 0
            ];
        }

        $compliments = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $complaints = $reviews->where('sentiment_score', '<', 0.4)->count();
        $neutral = $totalReviews - $compliments - $complaints;

        return [
            'compliments_percentage' => round(($compliments / $totalReviews) * 100),
            'complaints_percentage' => round(($complaints / $totalReviews) * 100),
            'neutral_percentage' => round(($neutral / $totalReviews) * 100),
            'compliments_count' => $compliments,
            'complaints_count' => $complaints,
            'neutral_count' => $neutral
        ];
    }
 public static function getAllStaffMetricsFromReviewValue($reviews)
{
    // Manual grouping by staff_id
    $staffGroups = [];
    foreach ($reviews as $review) {
        if ($review->staff_id) {
            $staffGroups[$review->staff_id][] = $review;
        }
    }

    $staffMetrics = [];
    
    foreach ($staffGroups as $staffId => $reviewsArray) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        $totalRating = 0;
        $totalSentiment = 0;
        $totalReviews = count($reviewsArray);
        $compliments = 0;
        $complaints = 0;
        $neutral = 0;
        
        foreach ($reviewsArray as $review) {
            // Sum up calculated rating
            $totalRating += $review->calculated_rating ?? 0;
            
            // Sum up sentiment score
            $sentimentScore = $review->sentiment_score ?? 0;
            $totalSentiment += $sentimentScore;
            
            // Count sentiment categories
            if ($sentimentScore >= 0.7) {
                $compliments++;
            } elseif ($sentimentScore < 0.4) {
                $complaints++;
            } else {
                $neutral++;
            }
        }
        
        // Calculate averages
        $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;
        $avgSentiment = $totalReviews > 0 ? $totalSentiment / $totalReviews : 0;
        
        $staffMetrics[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'position' => $staff->job_title ?? 'Staff',
            'avg_rating' => round($avgRating, 1),
            'sentiment_score' => self::getSentimentLabel($avgSentiment),
            'compliments_count' => $compliments,
            'complaints_count' => $complaints,
            'neutral_count' => $neutral,
            'total_reviews' => $totalReviews,
            'sentiment_numeric' => round($avgSentiment * 100)
        ];
    }

    // Sort by average rating descending
    usort($staffMetrics, function ($a, $b) {
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return $staffMetrics;
}
 public static function generateAiSummary($reviews)
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

   public static  function extractIssuesFromSuggestions($suggestions)
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


    public static function getReviewFeed($businessId, $dateRange, $limit = 10)
    {
        $reviews = ReviewNew::with(['user', 'guest_user', 'staff', 'value.tag', 'value'])
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->orderBy('created_at', 'desc')
            ->globalFilters(0, $businessId)
            ->limit($limit)
            ->withCalculatedRating()
            ->get();

        return $reviews->map(function ($review) {
            // Use the calculated_rating from the query, no need to recalculate
            $calculatedRating = (float) $review->calculated_rating; // Cast to float


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
                'tags' => $review->value->map(fn($v) => $v->tag->tag ?? null)->filter()->unique()->values()->toArray(),
                'is_voice' => $review->is_voice_review,
                'sentiment' =>  self::getSentimentLabel($review->sentiment_score),
                'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
            ];
        });
    }
    /**
     * Step 1: AI Moderation Pipeline (Improved)
     */

 public static function getAudioDuration($filePath)
    {
        try {
            $getID3 = new getID3();
            $fileInfo = $getID3->analyze($filePath);
            return $fileInfo['playtime_seconds'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }



   public static function getSentimentLabelByPercentage($percentage)
    {
        if ($percentage >= 70) {
            return 'Excellent';
        } elseif ($percentage >= 50) {
            return 'Good';
        } elseif ($percentage >= 30) {
            return 'Average';
        } else {
            return 'Needs Improvement';
        }
    }
    
  
    /**
     * Calculate aggregated sentiment metrics for reports
     */
    public static function calculateAggregatedSentiment($reviews)
    {
        $total = count($reviews);
        $positive = 0;
        $neutral = 0;
        $negative = 0;
        $totalScore = 0;
        
        foreach ($reviews as $review) {
            $score = $review->sentiment_score ?? 0;
            $totalScore += $score;
            
            if ($score >= 0.7) {
                $positive++;
            } elseif ($score >= 0.4) {
                $neutral++;
            } else {
                $negative++;
            }
        }
        
        $avgScore = $total > 0 ? round($totalScore / $total, 2) : 0;
        
        return [
            'total_reviews' => $total,
            'positive_count' => $positive,
            'neutral_count' => $neutral,
            'negative_count' => $negative,
            'positive_percentage' => $total > 0 ? round(($positive / $total) * 100) : 0,
            'neutral_percentage' => $total > 0 ? round(($neutral / $total) * 100) : 0,
            'negative_percentage' => $total > 0 ? round(($negative / $total) * 100) : 0,
            'average_score' => $avgScore,
            'average_percentage' => round($avgScore * 100),
            'sentiment_label' => self::getSentimentLabel($avgScore)
        ];
    }
    
    /**
     * Extract common topics for reports
     */
    public static function extractCommonTopics($reviews, $limit = 5)
    {
        $topicCounts = [];
        
        foreach ($reviews as $review) {
            $topics = $review->topics ?? [];
            if (is_string($topics)) {
                $topics = json_decode($topics, true) ?? [];
            }
            
            foreach ($topics as $topic) {
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            }
        }
        
        arsort($topicCounts);
        return array_slice($topicCounts, 0, $limit, true);
    }
    
    /**
     * Generate AI insights summary for dashboard
     */
    public static function generateDashboardInsights($reviews)
    {
        $sentimentData = self::calculateAggregatedSentiment($reviews);
        $topTopics = self::extractCommonTopics($reviews, 3);
        
        $insights = [
            'summary' => '',
            'key_findings' => [],
            'recommendations' => []
        ];
        
        // Generate summary
        if ($sentimentData['total_reviews'] === 0) {
            $insights['summary'] = 'No reviews available for analysis.';
        } else {
            $summary = "Overall sentiment is ";
            
            if ($sentimentData['positive_percentage'] >= 70) {
                $summary .= "highly positive";
            } elseif ($sentimentData['positive_percentage'] >= 50) {
                $summary .= "generally positive";
            } elseif ($sentimentData['positive_percentage'] >= 30) {
                $summary .= "mixed";
            } else {
                $summary .= "predominantly negative";
            }
            
            $summary .= ", with {$sentimentData['positive_percentage']}% of reviews expressing positive sentiment. ";
            $summary .= "The average rating is {$sentimentData['average_score']} out of 5. ";
            
            if (!empty($topTopics)) {
                $topTopic = array_key_first($topTopics);
                $summary .= "A recurring topic mentioned is " . $topTopic . ". ";
            }
            
            $insights['summary'] = trim($summary);
        }
        
        // Key findings
        if ($sentimentData['positive_percentage'] >= 70) {
            $insights['key_findings'][] = 'Strong positive sentiment among customers';
        }
        
        if ($sentimentData['negative_percentage'] >= 30) {
            $insights['key_findings'][] = 'Significant negative feedback requires attention';
        }
        
        foreach ($topTopics as $topic => $count) {
            $insights['key_findings'][] = "Frequent mentions of: {$topic} ({$count} times)";
        }
        
        // Recommendations
        if ($sentimentData['negative_percentage'] >= 30) {
            $insights['recommendations'][] = 'Address negative feedback patterns immediately';
        }
        
        if ($sentimentData['positive_percentage'] >= 70) {
            $insights['recommendations'][] = 'Leverage positive feedback for marketing';
        }
        
        if (!empty($topTopics)) {
            $topTopic = array_key_first($topTopics);
            $insights['recommendations'][] = "Focus on improving: {$topTopic}";
        }
        
        return $insights;
    }


  
public static function getTopWorstStaff($businessId, $dateRange, $limit = 5, $criteria = 'rating')
{
    // Get staff reviews WITH calculated rating
    $staffReviews = ReviewNew::with('staff')
        ->where('business_id', $businessId)
        ->globalFilters(0, $businessId)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->withCalculatedRating()
        ->get();

    if ($staffReviews->isEmpty()) {
        return [
            'message' => 'No staff reviews found in the selected period',
            'worst_staff' => []
        ];
    }

    // Manual grouping since calculated_rating is not a real database column
    $groupedReviews = [];
    foreach ($staffReviews as $review) {
        if ($review->staff_id) {
            $groupedReviews[$review->staff_id][] = $review;
        }
    }

    $staffPerformance = [];
    
    foreach ($groupedReviews as $staffId => $reviews) {
        $staff = User::find($staffId);
        if (!$staff) continue;

        // Manual calculations
        $totalRating = 0;
        $reviewCount = count($reviews);
        $positiveCount = 0;
        $negativeCount = 0;
        $totalSentiment = 0;
        $latestReviewDate = null;
        
        foreach ($reviews as $review) {
            // Calculate average rating
            $totalRating += $review->calculated_rating ?? 0;
            
            // Count positive/negative reviews based on sentiment score
            $sentimentScore = $review->sentiment_score ?? 0;
            $totalSentiment += $sentimentScore;
            
            if ($sentimentScore >= 0.7) {
                $positiveCount++;
            } elseif ($sentimentScore < 0.4) {
                $negativeCount++;
            }
            
            // Track latest review
            if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                $latestReviewDate = $review->created_at;
            }
        }
        
        // Calculate averages
        $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;
        $avgSentiment = $reviewCount > 0 ? $totalSentiment / $reviewCount : 0;
        
        // Only include staff with at least 3 reviews for meaningful analysis
        if ($reviewCount < 3) continue;
        
        // Calculate additional metrics
        $sentimentPercentage = $reviewCount > 0 ? round(($positiveCount / $reviewCount) * 100) : 0;
        $negativePercentage = $reviewCount > 0 ? round(($negativeCount / $reviewCount) * 100) : 0;
        
        // Get common complaints/issues
        $commonComplaints = self::extractCommonComplaints(collect($reviews));
        
        $staffPerformance[] = [
            'staff_id' => $staffId,
            'staff_name' => $staff->name,
            'job_title' => $staff->job_title ?? 'Staff',
            'email' => $staff->email,
            'avg_rating' => round($avgRating, 2),
            'avg_sentiment' => round($avgSentiment, 3),
            'sentiment_label' => self::getSentimentLabel($avgSentiment),
            'sentiment_percentage' => $sentimentPercentage,
            'negative_percentage' => $negativePercentage,
            'review_count' => $reviewCount,
            'positive_reviews' => $positiveCount,
            'negative_reviews' => $negativeCount,
            'common_complaints' => array_slice($commonComplaints, 0, 3),
            'last_review_date' => $latestReviewDate ? $latestReviewDate->diffForHumans() : 'No reviews',
            'rating_trend' => self::calculateStaffRatingTrend(collect($reviews)),
            'performance_issue' => self::identifyPerformanceIssue($avgRating, $avgSentiment, $negativePercentage)
        ];
    }

    // Sort based on selected criteria
    if ($criteria === 'rating') {
        // Sort by average rating (lowest first)
        usort($staffPerformance, function ($a, $b) {
            return $a['avg_rating'] <=> $b['avg_rating'];
        });
    } elseif ($criteria === 'sentiment') {
        // Sort by sentiment score (lowest first)
        usort($staffPerformance, function ($a, $b) {
            return $a['avg_sentiment'] <=> $b['avg_sentiment'];
        });
    } else {
        // Default: sort by negative percentage (highest first)
        usort($staffPerformance, function ($a, $b) {
            return $b['negative_percentage'] <=> $a['negative_percentage'];
        });
    }

    // Take the worst staff
    $worstStaff = array_slice($staffPerformance, 0, $limit);

    // Calculate overall summary
    $summary = self::generateWorstStaffSummary($worstStaff, $staffPerformance);

    return [
        'worst_staff' => $worstStaff,
        'summary' => $summary,
        'total_staff_analyzed' => count($staffPerformance),
        'criteria_used' => $criteria,
        'date_range' => [
            'start' => $dateRange['start']->format('Y-m-d'),
            'end' => $dateRange['end']->format('Y-m-d')
        ]
    ];
}

/**
 * Extract common complaints from reviews
 */
public static function extractCommonComplaints($reviews)
{
    $complaints = [];
    
    foreach ($reviews as $review) {
        if (empty($review->comment)) continue;
        
        $text = strtolower($review->comment);
        
        // Define complaint patterns
        $patterns = [
            'rude' => ['rude', 'impolite', 'disrespectful', 'unprofessional'],
            'slow' => ['slow', 'late', 'delay', 'wait', 'long time'],
            'ignore' => ['ignore', 'ignored', 'unattentive', 'unhelpful'],
            'mistake' => ['mistake', 'error', 'wrong', 'incorrect'],
            'knowledge' => ["don't know", 'uninformed', 'no knowledge', 'clueless'],
            'attitude' => ['attitude', 'arrogant', 'dismissive', 'condescending'],
            'communication' => ['unclear', 'confusing', 'poor communication', "didn't explain"],
            'inefficient' => ['inefficient', 'disorganized', 'messy', 'chaotic']
        ];
        
        foreach ($patterns as $key => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $complaints[$key] = ($complaints[$key] ?? 0) + 1;
                    break; // Count once per pattern per review
                }
            }
        }
    }
    
    // Sort by frequency
    arsort($complaints);
    
    // Convert to readable format
    $readableComplaints = [];
    $labelMap = [
        'rude' => 'Rudeness/Unprofessionalism',
        'slow' => 'Slow Service',
        'ignore' => 'Being Ignored',
        'mistake' => 'Mistakes/Errors',
        'knowledge' => 'Lack of Knowledge',
        'attitude' => 'Bad Attitude',
        'communication' => 'Poor Communication',
        'inefficient' => 'Inefficiency'
    ];
    
    foreach ($complaints as $key => $count) {
        $readableComplaints[] = [
            'issue' => $labelMap[$key] ?? ucfirst($key),
            'count' => $count,
            'percentage' => count($reviews) > 0 ? round(($count / count($reviews)) * 100) : 0
        ];
    }
    
    return $readableComplaints;
}

/**
 * Identify performance issues based on metrics
 */
public static function identifyPerformanceIssue($avgRating, $avgSentiment, $negativePercentage)
{
    if ($avgRating <= 2.0 || $avgSentiment < 0.3) {
        return 'Critical Issue - Needs Immediate Attention';
    } elseif ($avgRating <= 2.5 || $avgSentiment < 0.4) {
        return 'Major Concern - Requires Training';
    } elseif ($avgRating <= 3.0 || $negativePercentage > 30) {
        return 'Needs Improvement - Monitor Closely';
    } elseif ($avgRating <= 3.5 || $negativePercentage > 20) {
        return 'Below Average - Coaching Recommended';
    } else {
        return 'Acceptable - Minor Issues Only';
    }
}

/**
 * Generate summary for worst staff
 */
public static function generateWorstStaffSummary($worstStaff, $allStaff)
{
    if (empty($worstStaff)) {
        return [
            'overall_status' => 'No staff with significant issues',
            'average_rating_gap' => 0,
            'key_issues' => []
        ];
    }
    
    // Calculate average rating of worst staff vs all staff
    $worstAvgRating = array_sum(array_column($worstStaff, 'avg_rating')) / count($worstStaff);
    $allAvgRating = array_sum(array_column($allStaff, 'avg_rating')) / count($allStaff);
    
    // Get most common complaints across worst staff
    $allComplaints = [];
    foreach ($worstStaff as $staff) {
        foreach ($staff['common_complaints'] as $complaint) {
            $issue = $complaint['issue'];
            $allComplaints[$issue] = ($allComplaints[$issue] ?? 0) + $complaint['count'];
        }
    }
    
    arsort($allComplaints);
    $topIssues = array_slice($allComplaints, 0, 3, true);
    
    $keyIssues = [];
    foreach ($topIssues as $issue => $count) {
        $keyIssues[] = $issue;
    }
    
    // Determine severity
    $severity = 'low';
    $worstRating = min(array_column($worstStaff, 'avg_rating'));
    if ($worstRating < 2.0) {
        $severity = 'critical';
    } elseif ($worstRating < 2.5) {
        $severity = 'high';
    } elseif ($worstRating < 3.0) {
        $severity = 'medium';
    }
    
    return [
        'overall_status' => $severity === 'critical' ? 'Critical Issues Detected' : 'Improvement Needed',
        'severity_level' => $severity,
        'worst_rating' => round($worstRating, 1),
        'average_rating_gap' => round($allAvgRating - $worstAvgRating, 2),
        'affected_staff_count' => count($worstStaff),
        'key_issues' => $keyIssues,
        'recommendation' => self::getPerformanceRecommendation($severity, $keyIssues)
    ];
}

/**
 * Get performance recommendation
 */
public static function getPerformanceRecommendation($severity, $keyIssues)
{
    if ($severity === 'critical') {
        return 'Immediate intervention required. Consider formal performance review or retraining.';
    } elseif ($severity === 'high') {
        return 'Urgent coaching and monitoring needed. Set clear performance expectations.';
    } elseif ($severity === 'medium') {
        return 'Regular feedback and training recommended. Address specific skill gaps.';
    } else {
        return 'Provide constructive feedback and ongoing support.';
    }
}
}




// {
//   "model": "gpt-4o-mini",
//   "temperature": 0.2,
//   "max_tokens": 900,
//   "messages": [
//     {
//       "role": "system",
//       "content": "You are an AI Experience Intelligence Engine. Analyze reviews fairly and return ONLY valid JSON exactly matching this schema: { \"language\": {\"detected\": \"\", \"translated_text\": \"\"}, \"sentiment\": {\"label\": \"\", \"score\": 0.0}, \"emotion\": {\"primary\": \"\", \"intensity\": \"\"}, \"moderation\": {\"is_abusive\": false, \"safe_for_public_display\": true}, \"themes\": [], \"category_analysis\": [], \"staff_intelligence\": {\"staff_id\": \"\", \"staff_name\": \"\", \"mentioned_explicitly\": false, \"sentiment_towards_staff\": \"\", \"soft_skill_scores\": {}, \"training_recommendations\": [], \"risk_level\": \"\"}, \"service_unit_intelligence\": {\"unit_type\": \"\", \"unit_id\": \"\", \"issues_detected\": [], \"maintenance_required\": false}, \"business_insights\": {\"root_cause\": \"\", \"repeat_issue_likelihood\": \"\"}, \"recommendations\": {\"business_actions\": [], \"staff_actions\": []}, \"alerts\": {\"triggered\": false}, \"explainability\": {\"decision_basis\": [], \"confidence_score\": 0.0}, \"summary\": {\"one_line\": \"\", \"manager_summary\": \"\"} } Do NOT add extra fields. Do not shorten or summarize."
//     },
//     {
//       "role": "user",
//       "content": "{ \"business_ai_settings\": { \"staff_intelligence\": true, \"ignore_abusive_reviews_for_staff\": true, \"min_reviews_for_staff_score\": 3, \"confidence_threshold\": 0.7 }, \"review_metadata\": { \"source\": \"platform\", \"business_type\": \"hotel\", \"branch_id\": \"BR-101\", \"submitted_at\": \"2025-11-01T10:15:00Z\" }, \"review_content\": { \"text\": \"Ateeq served us very badly today. He was rude and ignored our requests. The room 305 was clean but service was terrible.\", \"voice_review\": false }, \"ratings\": { \"overall\": 2, \"questions\": [ {\"question_id\": \"Q1\", \"question_text\": \"Staff behavior\", \"main_category\": \"Staff\", \"sub_category\": \"Politeness\", \"rating\": 2}, {\"question_id\": \"Q2\", \"question_text\": \"Room cleanliness\", \"main_category\": \"Service\", \"sub_category\": \"Cleanliness\", \"rating\": 4} ] }, \"staff_context\": { \"staff_selected\": true, \"staff_id\": \"ST-2001\", \"staff_name\": \"Ateeq\" }, \"service_unit\": { \"unit_type\": \"Room\", \"unit_id\": \"305\" } }"
//     }
//   ]
// }
