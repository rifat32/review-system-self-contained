<?php

namespace App\Services\AIProcessor;

use App\Models\Branch;
use App\Models\BusinessArea;
use App\Models\BusinessService;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Business\BusinessAnalyticsService;
use App\Services\Review\ReviewService;
use App\Services\Staff\StaffPerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use getID3;
use Carbon\Carbon;

use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Services\Rule\RuleEngineService;
use App\Models\InsightRecord;
use App\Models\Tag;

class AIProcessorService
{
    // ========== CORE DYNAMIC METHODS ==========

    /**
     * Get sentiment label from score - Dynamic version
     */
    public static function getSentimentLabel(?float $score): string
    {
        if ($score === null) {
            return RuleEngineService::getDefaultSentimentLabel();
        }

        // Use rule engine to determine sentiment label
        return RuleEngineService::getSentimentLabelFromScore($score);
    }

    /**
     * Get top mentioned staff dynamically
     */
    public static function getTopMentionedStaff($positiveReviews)
    {
        if ($positiveReviews->isEmpty()) {
            return [];
        }

        // Use rule engine to extract staff mentions
        $staffMentions = RuleEngineService::extractStaffMentions($positiveReviews);

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

    /**
     * Extract recommended training dynamically
     */
    private static function extractRecommendedTraining($suggestions)
    {
        $skillGaps = StaffPerformanceService::extractSkillGapsFromSuggestions($suggestions);

        if (!empty($skillGaps)) {
            return $skillGaps[0] . ' Training';
        }

        return RuleEngineService::getDefaultTrainingRecommendation();
    }

    /**
     * Extract opportunities from suggestions dynamically
     */
    public static function extractOpportunitiesFromSuggestions($suggestions)
    {
        $opportunityKeywords = RuleEngineService::getOpportunityKeywords();

        return collect($suggestions)
            ->filter(function ($s) use ($opportunityKeywords) {
                foreach ($opportunityKeywords as $keyword) {
                    if (stripos($s, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            })
            ->take(2)
            ->values()
            ->toArray();
    }

    /**
     * Generate predictions dynamically
     */
    public static function generatePredictions($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                [
                    'prediction' => 'No reviews available for prediction.',
                    'estimated_impact' => 'N/A'
                ]
            ];
        }

        $avgRating = $reviews->avg('calculated_rating') ?? 0;

        // Use rule engine for prediction calculation
        $predictionData = RuleEngineService::generateRatingPrediction($avgRating);

        return [
            [
                'prediction' => $predictionData['prediction'],
                'estimated_impact' => $predictionData['estimated_impact'],
                'current_avg_rating' => round($avgRating, 1),
                'potential_new_rating' => round($predictionData['potential_rating'], 1)
            ]
        ];
    }

    /**
     * Transcribe audio - Keep as is (external API)
     */
    public static function transcribeAudio($filePath)
    {
        try {
            $api_key = env('HF_API_KEY');
            $audio = file_get_contents($filePath);

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
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            \Log::info("HF Whisper API Response", [
                'http_status' => $status,
                'curl_error' => $error,
                'raw_result' => $result
            ]);

            if ($error) {
                \Log::error("HF Whisper CURL Error: $error");
                return '';
            }

            $data = json_decode($result, true);

            \Log::info("HF Whisper Decoded Response", [
                'decoded' => $data
            ]);

            return $data['text'] ?? '';
        } catch (\Exception $e) {
            \Log::error("transcribeAudio() exception: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get recommendations from rule engine
     */
    public static function getRecommendationsFromRuleEngine(int $businessId, $reviews, $dateRange): array
    {
        return RecommendationGeneratorService::generateFromInsights($businessId, 30);
    }

    /**
     * Generate branch recommendations dynamically
     */
    public static function generateBranchRecommendations($reviews): array
    {
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

        $firstReview = $reviews->first();
        $businessId = $firstReview->business_id ?? 0;
        $branchId = $firstReview->branch_id ?? 0;

        if ($businessId && $branchId) {
            return self::generateBranchRecommendationsFromRuleEngine($reviews, $businessId, $branchId);
        }

        return self::generateDynamicRecommendations($reviews);
    }

    /**
     * Generate branch recommendations using rule engine
     */
    public static function generateBranchRecommendationsFromRuleEngine($reviews, int $businessId, int $branchId): array
    {
        $recommendations = [];

        // Get aggregated insights for this branch
        $branchInsights = InsightRecord::where('business_id', $businessId)
            ->whereHas('review_ids', function ($query) use ($branchId) {
                // This assumes review_ids is JSON and we need to check branch_id
                // In production, you'd join with reviews table
            })
            ->limit(5)
            ->get();

        if ($branchInsights->isEmpty()) {
            return [
                [
                    'type' => 'Info',
                    'title' => 'No Data Available',
                    'description' => 'No reviews found for this period. Encourage customers to provide feedback.'
                ]
            ];
        }

        foreach ($branchInsights as $insight) {
            if ($insight->mentions_count >= RuleEngineService::getMinimumMentionsForRecommendation()) {
                $matchedRules = RuleEngineService::matchRulesToInsight($insight);

                foreach ($matchedRules as $matched) {
                    $rule = $matched['rule'];
                    $recData = RuleEngineService::generateRecommendation($rule, $insight);

                    if (!empty($recData)) {
                        $recommendations[] = [
                            'type' => ucfirst($recData['type']),
                            'title' => "{$insight->main_category} Improvement",
                            'description' => $recData['text'],
                            'evidence_count' => $insight->mentions_count,
                            'priority' => $recData['priority'],
                            'confidence' => $recData['confidence']
                        ];
                    }
                }
            }
        }

        if (empty($recommendations)) {
            $totalReviews = $reviews->count();

            if ($totalReviews === 0) {
                $recommendations[] = [
                    'type' => 'Info',
                    'title' => 'Insufficient Data',
                    'description' => 'Not enough reviews to generate specific recommendations.'
                ];
            } else {
                $staffTrainings = RuleEngineService::getStaffTrainingRecommendations(0, $businessId);

                if (!empty($staffTrainings)) {
                    foreach (array_slice($staffTrainings, 0, 2) as $training) {
                        $recommendations[] = [
                            'type' => 'Action',
                            'title' => $training['title'],
                            'description' => "Consider {$training['type']} training for staff.",
                            'priority' => $training['priority']
                        ];
                    }
                }
            }
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * Generate dynamic recommendations without hardcoding
     */
    private static function generateDynamicRecommendations($reviews): array
    {
        $recommendations = [];
        $totalReviews = $reviews->count();

        $debugInfo = [
            'total_reviews' => $totalReviews,
            'positive_reviews' => 0,
            'has_comments' => 0
        ];

        // Get thresholds from rule engine
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold);
        $debugInfo['positive_reviews'] = $positiveReviews->count();

        $reviewsWithComments = $reviews->filter(function ($review) {
            return !empty(trim($review->comment ?? ''));
        });
        $debugInfo['has_comments'] = $reviewsWithComments->count();

        // Use rule engine to detect staff praise
        $staffPraise = RuleEngineService::detectStaffPraise($positiveReviews);

        if ($staffPraise['count'] >= RuleEngineService::getMinimumPraiseForRecommendation()) {
            $recommendations[] = [
                'type' => 'Strength',
                'title' => $staffPraise['title'],
                'description' => $staffPraise['description'],
                'evidence_count' => $staffPraise['count'],
                'priority' => 'low'
            ];
        }

        // Find common issues dynamically
        $issues = self::findCommonIssues($reviews);

        foreach ($issues as $issue) {
            if ($issue['count'] >= RuleEngineService::getMinimumMentionsForIssue() && count($recommendations) < 3) {
                $recommendations[] = [
                    'type' => 'Weak Area',
                    'title' => $issue['topic'],
                    'description' => $issue['description'] . " (mentioned {$issue['count']} times)",
                    'evidence_count' => $issue['count'],
                    'priority' => $issue['count'] >= RuleEngineService::getHighPriorityThreshold() ? 'high' : 'medium'
                ];
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'Info',
                'title' => 'Insufficient Feedback Data',
                'description' => 'Not enough specific feedback to generate recommendations.',
                'debug_info' => $debugInfo
            ];
        }

        return array_slice($recommendations, 0, 3);
    }

    /**
     * Find common issues dynamically
     */
    public static function findCommonIssues($reviews)
    {
        if ($reviews->isEmpty()) {
            return [];
        }

        // Get issue patterns from rule engine
        $issuePatterns = RuleEngineService::getIssuePatterns();

        $results = [];

        foreach ($reviews as $review) {
            if (empty($review->comment)) {
                continue;
            }

            $comment = strtolower(trim($review->comment));

            foreach ($issuePatterns as $topic => $patternData) {
                $matched = false;

                foreach ($patternData['keywords'] as $keyword) {
                    if (strpos($comment, $keyword) !== false) {
                        $matched = true;
                        break;
                    }
                }

                if ($matched) {
                    if (!isset($results[$topic])) {
                        $results[$topic] = [
                            'topic' => $topic,
                            'count' => 0,
                            'description' => $patternData['description'],
                            'keyword_matches' => []
                        ];
                    }

                    $results[$topic]['count']++;
                }
            }
        }

        $sortedResults = array_values($results);
        usort($sortedResults, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return $sortedResults;
    }

    /**
     * Get branch comparison data dynamically
     */
    public static function getBranchComparisonData($branch, $startDate, $endDate)
    {
        $businessId = $branch->business_id;

        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branch->id)
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->get();

        $totalReviews = $reviews->count();
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        // Use dynamic thresholds
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $aiSentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

        $csatThreshold = RuleEngineService::getCsatThreshold();
        $csatCount = $reviews->filter(function ($review) use ($csatThreshold) {
            return ($review->calculated_rating ?? 0) >= $csatThreshold;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        $staffPerformance = self::getBranchStaffPerformance($branch->id, $businessId, $startDate, $endDate);
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
                'response_rate' => ReviewService::calculateResponseRate($reviews)
            ],
            'staff_performance' => $staffPerformance,
            'top_topics' => array_slice($topTopics, 0, 5)
        ];
    }

    /**
     * Get branch staff performance dynamically
     */
    public static function getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate)
    {
        $staffReviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->globalFilters(0, $businessId, 1)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->get();

        $groupedReviews = [];
        foreach ($staffReviews as $review) {
            if ($review->staff_id) {
                $groupedReviews[$review->staff_id][] = $review;
            }
        }

        $staffPerformance = [];

        foreach ($groupedReviews as $staffId => $reviews) {
            $staff = User::find($staffId);
            if (!$staff) {
                continue;
            }

            $totalRating = 0;
            $reviewCount = count($reviews);
            $positiveCount = 0;
            $latestReviewDate = null;

            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();

            foreach ($reviews as $review) {
                $totalRating += $review->calculated_rating ?? 0;
                if (isset($review->sentiment_score) && $review->sentiment_score >= $positiveThreshold) {
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

        usort($staffPerformance, function ($a, $b) {
            return $b['avg_rating'] <=> $a['avg_rating'];
        });

        return array_slice($staffPerformance, 0, 3);
    }

    /**
     * Extract branch topics dynamically
     */
    public static function extractBranchTopics($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            if ($review->comment) {
                $commonTopics = RuleEngineService::getCommonTopicKeywords();
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
     * Generate branch comparison insights dynamically
     */
    public static function generateBranchComparisonInsights($branchesData, $allMetrics)
    {
        if (count($branchesData) === 0) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        return RuleEngineService::generateBranchComparisonInsights($branchesData);
    }

    /**
     * Generate comparison highlights dynamically
     */
    public static function generateComparisonHighlights($branchesData)
    {
        if (count($branchesData) < 2) {
            return [];
        }

        return RuleEngineService::generateComparisonHighlights($branchesData);
    }

    /**
     * Get sentiment trend over time dynamically
     */
    public static function getSentimentTrendOverTime($branches, $startDate, $endDate)
    {
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

                $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
                $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
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
     * Get staff complaints by branch dynamically
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

            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();
            $negativeReviews = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();
            $totalReviews = $reviews->count();

            $complaintsByBranch[] = [
                'branch_name' => $branch->name,
                'complaints_count' => $negativeReviews,
                'total_reviews' => $totalReviews,
                'complaint_percentage' => $totalReviews > 0 ? round(($negativeReviews / $totalReviews) * 100) : 0
            ];
        }

        usort($complaintsByBranch, function ($a, $b) {
            return $b['complaint_percentage'] <=> $a['complaint_percentage'];
        });

        return $complaintsByBranch;
    }

    /**
     * Calculate branch summary dynamically
     */
    public static function calculateBranchSummary($reviews)
    {
        $totalReviews = $reviews->count();
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();


        $sentiment = RuleEngineService::determineOverallSentiment($positiveReviews, $totalReviews);



        $csatThreshold = RuleEngineService::getCsatThreshold();
        $csatCount = $reviews->filter(function ($review) use ($csatThreshold) {
            return ($review->calculated_rating ?? 0) >= $csatThreshold;
        })->count();


        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        $topTopic = self::extractTopTopic($reviews);


        $flagged = $reviews->where('status', 'flagged')->count();

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => round($averageRating, 1),
            'rating_out_of' => 5,
            'ai_sentiment' => $sentiment,
            'csat_score' => $csatScore,
            'top_topic' => $topTopic['topic'] ?? 'General',
            'flagged' => $flagged,
            'response_rate' => ReviewService::calculateResponseRate($reviews)
        ];
    }

    /**
     * Extract top topic dynamically
     */
    public static function extractTopTopic($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
                }
            }

            if ($review->comment) {
                $commonTopics = RuleEngineService::getCommonTopicKeywords();
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

    /**
     * Generate AI insights dynamically
     */
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

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positive = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [$negativeThreshold, $positiveThreshold])->count();
        $negative = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

        $sentimentBreakdown = [
            'positive' => round(($positive / $totalReviews) * 100),
            'neutral' => round(($neutral / $totalReviews) * 100),
            'negative' => round(($negative / $totalReviews) * 100)
        ];

        $summary = self::generateAiSummaryReport($reviews, $sentimentBreakdown);

        return [
            'summary' => $summary,
            'sentiment_breakdown' => $sentimentBreakdown,
            'key_trends' => self::extractKeyTrends($reviews)
        ];
    }

    /**
     * Generate AI summary report dynamically
     */
    public static function generateAiSummaryReport($reviews, $sentimentBreakdown)
    {
        $totalReviews = $reviews->count();
        $positivePercentage = $sentimentBreakdown['positive'];

        $sentimentDescription = RuleEngineService::getSentimentDescription($positivePercentage);

        $summary = "Overall sentiment is {$sentimentDescription}, with {$positivePercentage}% of reviews expressing positive sentiment. ";

        $avgRating = $reviews->avg('calculated_rating') ?? 0;
        $summary .= "The average rating is " . round($avgRating, 1) . " out of 5. ";

        $commonIssues = self::findCommonIssues($reviews);
        if (!empty($commonIssues)) {
            $summary .= "A recurring issue mentioned is " . $commonIssues[0]['topic'] . ". ";
        }

        $peakTimes = self::findPeakReviewTimes($reviews);
        if ($peakTimes) {
            $summary .= "Peak feedback times are around {$peakTimes}. ";
        }

        return trim($summary);
    }

    /**
     * Extract key trends dynamically
     */
    public static function extractKeyTrends($reviews)
    {
        $trends = [];

        if ($reviews->isEmpty()) {
            return $trends;
        }

        $sortedReviews = $reviews->sortBy('created_at');
        $half = ceil($sortedReviews->count() / 2);

        $firstHalf = $sortedReviews->slice(0, $half);
        $secondHalf = $sortedReviews->slice($half);

        $firstSentiment = $firstHalf->avg('sentiment_score');
        $secondSentiment = $secondHalf->avg('sentiment_score');

        $trendThreshold = RuleEngineService::getTrendThreshold();

        if ($secondSentiment > $firstSentiment + $trendThreshold) {
            $trends[] = RuleEngineService::getImprovingTrendMessage();
        } elseif ($secondSentiment < $firstSentiment - $trendThreshold) {
            $trends[] = RuleEngineService::getDecliningTrendMessage();
        }

        $commonIssues = self::findCommonIssues($reviews);
        $frequentIssueThreshold = RuleEngineService::getFrequentIssueThreshold();

        foreach ($commonIssues as $issue) {
            if ($issue['count'] >= $frequentIssueThreshold) {
                $trends[] = "Frequent mentions of " . $issue['topic'];
            }
        }

        return array_slice($trends, 0, 3);
    }

    /**
     * Find peak review times dynamically
     */
    public static function findPeakReviewTimes($reviews)
    {
        if ($reviews->isEmpty()) {
            return null;
        }

        $hourlyCounts = array_fill(0, 24, 0);

        foreach ($reviews as $review) {
            $hour = $review->created_at->hour;
            $hourlyCounts[$hour]++;
        }

        $peakHour = array_search(max($hourlyCounts), $hourlyCounts);

        return sprintf('%02d:00', $peakHour);
    }

    // public static function getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 5)
    // {
    //     $staffReviews = ReviewNew::where('business_id', $businessId)
    //         ->where('branch_id', $branchId)
    //         ->globalFilters(0, $businessId, 1)
    //         ->whereNotNull('staff_id')
    //         // ->whereBetween('created_at', [$startDate, $endDate])
    //         ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
    //             return $query->whereBetween('created_at', [$startDate, $endDate]);
    //         })
    //         ->withCalculatedRating()
    //         ->get();

    //     $staffPerformance = [];
    //     $groupedReviews = [];

    //     foreach ($staffReviews as $review) {
    //         if ($review->staff_id) {
    //             $groupedReviews[$review->staff_id][] = $review;
    //         }
    //     }

    //     foreach ($groupedReviews as $staffId => $reviews) {
    //         $staff = User::find($staffId);
    //         if (!$staff) {
    //             continue;
    //         }

    //         $totalRating = 0;
    //         $reviewCount = count($reviews);
    //         $positiveReviews = 0;
    //         $latestReviewDate = null;

    //         $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();

    //         foreach ($reviews as $review) {
    //             $totalRating += $review->calculated_rating ?? 0;
    //             if (isset($review->sentiment_score) && $review->sentiment_score >= $positiveThreshold) {
    //                 $positiveReviews++;
    //             }
    //             if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
    //                 $latestReviewDate = $review->created_at;
    //             }
    //         }

    //         $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;

    //         if ($reviewCount < RuleEngineService::getMinimumReviewsForStaffEvaluation()) {
    //             continue;
    //         }

    //         $staffPerformance[] = [
    //             'staff_id' => $staffId,
    //             'staff_name' => $staff->name,
    //             'staff_code' => $staff->employee_code ?? 'EMP-' . $staffId,
    //             'avg_rating' => round($avgRating, 1),
    //             'rating_out_of' => 5,
    //             'reviews_count' => $reviewCount,
    //             'ai_evaluation' => self::getStaffEvaluation($avgRating, $reviewCount),
    //             'has_profile' => true,
    //             'positive_percentage' => $reviewCount > 0 ? round(($positiveReviews / $reviewCount) * 100) : 0,
    //             'last_review_date' => $latestReviewDate ? $latestReviewDate->diffForHumans() : 'Never'
    //         ];
    //     }

    //     usort($staffPerformance, function ($a, $b) {
    //         return $b['avg_rating'] <=> $a['avg_rating'];
    //     });

    //     return array_slice($staffPerformance, 0, $limit);
    // }

    /**
     * Get staff evaluation dynamically
     */
    public static function getStaffEvaluation($avgRating, $reviewCount)
    {
        if ($reviewCount < RuleEngineService::getMinimumReviewsForStaffEvaluation()) {
            return RuleEngineService::getInsufficientDataMessage();
        }

        return RuleEngineService::getStaffEvaluationFromRating($avgRating);
    }

    /**
     * Generate action item dynamically
     */
    public static function generateActionItem($issue, $evidenceCount)
    {
        $actionData = RuleEngineService::generateActionForIssue($issue, $evidenceCount);

        if ($actionData) {
            return [
                'type' => 'Action',
                'title' => $actionData['title'],
                'description' => $actionData['description'],
                'priority' => $actionData['priority']
            ];
        }

        return null;
    }

    /**
     * Calculate staff metrics from review value dynamically
     */
    public static function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
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

        $avgRating = $reviews->avg('calculated_rating') ?? 0;

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positiveCount = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [$negativeThreshold, $positiveThreshold])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

        $positivePercentage = round(($positiveCount / $totalReviews) * 100);
        $neutralPercentage = round(($neutralCount / $totalReviews) * 100);
        $negativePercentage = round(($negativeCount / $totalReviews) * 100);

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

    /**
     * Extract topics from reviews dynamically
     */
    public static function extractTopicsFromReviews($reviews)
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

    /**
     * Calculate performance by category dynamically
     */
    public static function calculatePerformanceByCategory($reviews)
    {
        $performanceCategories = RuleEngineService::getPerformanceCategories();

        $performance = [];

        foreach ($performanceCategories as $category => $keywords) {
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

    /**
     * Get notable reviews dynamically
     */
    public static function getNotableReviews($reviews, $limit = 2)
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

    /**
     * Get sentiment gap message dynamically
     */
    public static function getSentimentGapMessage($gap)
    {
        return RuleEngineService::getSentimentGapMessage($gap);
    }

    /**
     * Get previous period reviews
     */
    // public static function getPreviousPeriodReviews($businessId, $period = null)
    // {
    //     if ($period === null) {
    //         return ReviewNew::where('business_id', $businessId)
    //             ->whereNotNull('staff_id')
    //             ->whereNotNull('sentiment_score')
    //             ->globalFilters(0, $businessId)
    //             ->withCalculatedRating()
    //             ->get();
    //     }

    //     $startDate = match ($period) {
    //         'this_week' => Carbon::now()->subWeek()->startOfWeek(),
    //         'this_month' => Carbon::now()->subMonth()->startOfMonth(),
    //         'last_week' => Carbon::now()->subWeeks(2)->startOfWeek(),
    //         'last_month' => Carbon::now()->subMonths(2)->startOfMonth(),
    //         default => Carbon::now()->subMonth()->startOfMonth()
    //     };

    //     $endDate = match ($period) {
    //         'this_week' => Carbon::now()->subWeek()->endOfWeek(),
    //         'this_month' => Carbon::now()->subMonth()->endOfMonth(),
    //         'last_week' => Carbon::now()->subWeeks(2)->endOfWeek(),
    //         'last_month' => Carbon::now()->subMonths(2)->endOfMonth(),
    //         default => Carbon::now()->subMonth()->endOfMonth()
    //     };

    //     return ReviewNew::where('business_id', $businessId)
    //         ->whereNotNull('staff_id')
    //         ->whereNotNull('sentiment_score')
    //         ->globalFilters(0, $businessId)
    //         ->whereDate('created_at', '>=', $startDate)
    //         ->whereDate('created_at', '<=', $endDate)
    //         ->withCalculatedRating()
    //         ->get();
    // }

    /**
     * Calculate overall metrics from review value dynamically
     */
    public static function calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews)
    {
        $currentAvgRating = $currentReviews->isNotEmpty()
            ? round($currentReviews->avg('calculated_rating'), 1)
            : 0;

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
                'change_type' => RuleEngineService::getChangeType($ratingChange)
            ],
            'overall_sentiment' => [
                'value' => $currentSentiment,
                'change' => $sentimentChange,
                'change_type' => RuleEngineService::getChangeType($sentimentChange)
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'change' => $reviewsChange,
                'change_type' => RuleEngineService::getChangeType($reviewsChange)
            ]
        ];
    }

    /**
     * Calculate average sentiment dynamically
     */
    public static function calculateAverageSentiment($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();

        return round(($positiveReviews / $reviews->count()) * 100);
    }

    /**
     * Extract staff topics dynamically
     */
    public static function extractStaffTopics($staffReviews)
    {
        $allTopics = [];

        foreach ($staffReviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }

            if (empty($review->topics) && $review->comment) {
                $commonWords = RuleEngineService::getCommonStaffTopicKeywords();
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
     * Calculate performance overview from review value dynamically
     */
    public static function calculatePerformanceOverviewFromReviewValue($reviews)
    {
        if ($reviews instanceof Builder) {
            $reviews = $reviews->get();
        }

        $totalSubmissions = $reviews->count();

        $averageScore = $totalSubmissions > 0
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positiveCount = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [$negativeThreshold, $positiveThreshold])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

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
        ];
    }

    /**
     * Get review samples dynamically
     */
    public static function getReviewSamples($reviews, $limit = 2)
    {
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)
            ->sortByDesc('created_at')
            ->take($limit);

        $constructiveReviews = $reviews->whereBetween('sentiment_score', [$negativeThreshold, $positiveThreshold])
            ->sortByDesc('created_at')
            ->take($limit);

        $negativeReviews = $reviews->where('sentiment_score', '<', $negativeThreshold)
            ->sortByDesc('created_at')
            ->take($limit);

        return [
            'positive' => $positiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->calculated_rating
                ];
            })->values()->toArray(),
            'constructive' => $constructiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->calculated_rating
                ];
            })->values()->toArray(),
            'negative' => $negativeReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->calculated_rating
                ];
            })->values()->toArray()
        ];
    }


    /**
     * Get recent submissions
     */
    public static function getRecentSubmissions($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $userName = ReviewService::getUserName($review);

                return [
                    'review_id' => $review->id,
                    'user_name' => $userName,
                    'rating' => $review->calculated_rating,
                    'comment' => $review->comment,
                    'submission_date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('d-m-Y H:i:s'),
                    'is_guest' => !is_null($review->guest_id),
                    'is_overall' => (bool) $review->is_overall,
                    'sentiment_score' => $review->sentiment_score,
                    'survey_name' => $review->survey ? $review->survey->name : null,
                    'staff_name' => $review->staff ? $review->staff->name : null,
                    "calculated_rating" => $review->calculated_rating ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get rating gap message dynamically
     */
    public static function getRatingGapMessage($gap)
    {
        return RuleEngineService::getRatingGapMessage($gap);
    }

    /**
     * Get recommended training dynamically
     */
    public static function getRecommendedTraining($reviews)
    {
        return RuleEngineService::getTrainingRecommendations($reviews);
    }

    /**
     * Analyze skill gaps dynamically
     */
    public static function analyzeSkillGaps($reviews)
    {
        return RuleEngineService::analyzeSkillGaps($reviews);
    }

    /**
     * Calculate customer tone dynamically
     */
    public static function calculateCustomerTone($reviews)
    {
        return RuleEngineService::calculateCustomerTone($reviews);
    }

    /**
     * Calculate sentiment distribution dynamically
     */
    public static function calculateSentimentDistribution($reviews)
    {
        $total = $reviews->count();

        if ($total === 0) {
            return ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $positive = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [$negativeThreshold, $positiveThreshold])->count();
        $negative = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();

        return [
            'positive' => round(($positive / $total) * 100),
            'neutral' => round(($neutral / $total) * 100),
            'negative' => round(($negative / $total) * 100)
        ];
    }

    /**
     * Calculate compliment ratio dynamically
     */
    public static function calculateComplimentRatio($reviews)
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

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $compliments = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $complaints = $reviews->where('sentiment_score', '<', $negativeThreshold)->count();
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

    /**
     * Get all staff metrics from review value dynamically
     */
    public static function getAllStaffMetricsFromReviewValue($reviews)
    {
        $staffGroups = [];
        foreach ($reviews as $review) {
            if ($review->staff_id) {
                $staffGroups[$review->staff_id][] = $review;
            }
        }

        $staffMetrics = [];

        foreach ($staffGroups as $staffId => $reviewsArray) {
            $staff = User::find($staffId);
            if (!$staff) {
                continue;
            }

            $totalRating = 0;
            $totalSentiment = 0;
            $totalReviews = count($reviewsArray);
            $compliments = 0;
            $complaints = 0;
            $neutral = 0;

            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            foreach ($reviewsArray as $review) {
                $totalRating += $review->calculated_rating ?? 0;

                $sentimentScore = $review->sentiment_score ?? 0;
                $totalSentiment += $sentimentScore;

                if ($sentimentScore >= $positiveThreshold) {
                    $compliments++;
                } elseif ($sentimentScore < $negativeThreshold) {
                    $complaints++;
                } else {
                    $neutral++;
                }
            }

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

        usort($staffMetrics, function ($a, $b) {
            return $b['avg_rating'] <=> $a['avg_rating'];
        });

        return $staffMetrics;
    }

    /**
     * Generate AI summary dynamically
     */
    public static function generateAiSummary($reviews)
    {
        return BusinessAnalyticsService::generateAiSummaryFromRuleEngine(0, $reviews);
    }

    /**
     * Extract issues from suggestions dynamically
     */
    public static function extractIssuesFromSuggestions($suggestions)
    {
        $issueKeywords = RuleEngineService::getIssueKeywords();

        $issues = collect($suggestions)
            ->filter(function ($s) use ($issueKeywords) {
                foreach ($issueKeywords as $keyword) {
                    if (stripos($s, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            })
            ->map(fn($s) => [
                'issue' => $s,
                'mention_count' => 1
            ])
            ->take(3)
            ->values();

        return $issues->isEmpty() ? [
            [
                'issue' => 'No major issues detected.',
                'mention_count' => 0
            ]
        ] : $issues->toArray();
    }

    /**
     * Get review feed
     */
    public static function getReviewFeed($businessId, $dateRange = null, $limit = 10, $user = null)
    {
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
                'sentiment' => self::getSentimentLabel($review->sentiment_score),
                'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
            ];
        });
    }

    /**
     * Get audio duration
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

    /**
     * Get sentiment label by percentage dynamically
     */
    public static function getSentimentLabelByPercentage($percentage)
    {
        return RuleEngineService::getSentimentLabelByPercentage($percentage);
    }

    /**
     * Calculate aggregated sentiment dynamically
     */
    public static function calculateAggregatedSentiment($reviews)
    {
        $total = count($reviews);
        $positive = 0;
        $neutral = 0;
        $negative = 0;
        $totalScore = 0;

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        foreach ($reviews as $review) {
            $score = $review->sentiment_score ?? 0;
            $totalScore += $score;

            if ($score >= $positiveThreshold) {
                $positive++;
            } elseif ($score >= $negativeThreshold) {
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
     * Extract common topics dynamically
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
     * Generate dashboard insights dynamically
     */
    public static function generateDashboardInsights($reviews)
    {
        $sentimentData = self::calculateAggregatedSentiment($reviews);
        $topTopics = self::extractCommonTopics($reviews, 3);

        return RuleEngineService::generateDashboardInsights($sentimentData, $topTopics, $reviews->count());
    }

    /**
     * Get insights overview dynamically
     */
    public static function getInsightsOverview($businessId, $dateRange)
    {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();


        $topIssues = self::extractTopIssuesFromReviews($reviews);

        $performanceByBranch = self::getPerformanceByBranch($businessId, $dateRange);

        $performanceByArea = self::getPerformanceByArea($businessId, $dateRange);


        $topPerformingStaff = self::getTopPerformingStaffFromTopWorst($businessId, $dateRange);

        return [
            'top_issues' => $topIssues,
            'performance_by_branch' => $performanceByBranch,
            'performance_by_area' => $performanceByArea,
            'top_performing_staff' => $topPerformingStaff
        ];
    }

    /**
     * Extract top issues from reviews dynamically
     */
    public static function extractTopIssuesFromReviews($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                ['issue' => 'No data', 'percentage' => 0]
            ];
        }

        $commonIssues = self::findCommonIssues($reviews);
        $totalReviews = $reviews->count();
        $issuesWithPercentages = [];

        foreach (array_slice($commonIssues, 0, 5) as $issue) {
            $percentage = $totalReviews > 0 ? round(($issue['count'] / $totalReviews) * 100) : 0;

            $issuesWithPercentages[] = [
                'issue' => $issue['topic'] ?? 'General',
                'percentage' => $percentage,
                'count' => $issue['count']
            ];
        }

        return array_slice($issuesWithPercentages, 0, 3);
    }

    public static function getPerformanceByBranch($businessId, $dateRange)
    {
        // KEEP EXACTLY AS IS
        $branches = Branch::where('business_id', $businessId)
            ->where('is_active', true)
            ->get();

        $performanceData = [];


        foreach ($branches as $branch) {
            $reviews = ReviewNew::where('business_id', $businessId)
                ->where('branch_id', $branch->id)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->globalFilters(0, $businessId)
                ->withCalculatedRating()
                ->get();

            if ($reviews->isNotEmpty()) {

                $branchSummary = self::calculateBranchSummary($reviews);


                $performanceData[] = [
                    'name' => $branch->name,
                    'rating' => $branchSummary['average_rating'] ?? 0,
                    'review_count' => $branchSummary['total_reviews'] ?? 0,
                    'branch_id' => $branch->id
                ];
            }
        }

        usort($performanceData, function ($a, $b) {
            return $b['rating'] <=> $a['rating'];
        });

        return array_slice($performanceData, 0, 3);
    }
    /**
     * Get performance by area dynamically
     */
    public static function getPerformanceByArea($businessId, $dateRange)
    {
        $areasWithReviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->whereHas('review_business_services', function ($query) {
                $query->whereNotNull('business_area_id');
            })
            ->with(['review_business_services.business_service.business_areas'])
            ->get();

        $areaReviews = [];

        foreach ($areasWithReviews as $review) {
            foreach ($review->review_business_services as $service) {
                if ($service->business_area_id) {
                    if ($service->business_service && $service->business_service->business_areas) {
                        foreach ($service->business_service->business_areas as $area) {
                            if ($area->id == $service->business_area_id) {
                                if (!isset($areaReviews[$area->id])) {
                                    $areaReviews[$area->id] = [
                                        'area' => $area,
                                        'reviews' => [],
                                        'ratings' => []
                                    ];
                                }
                                $areaReviews[$area->id]['reviews'][] = $review;
                                $areaReviews[$area->id]['ratings'][] = $review->calculated_rating ?? 0;
                            }
                        }
                    }
                }
            }
        }

        $performanceData = [];

        foreach ($areaReviews as $areaData) {
            $area = $areaData['area'];
            $reviews = $areaData['reviews'];
            $ratings = $areaData['ratings'];

            if (count($ratings) > 0) {
                $avgRating = round(array_sum($ratings) / count($ratings), 1);

                $performanceData[] = [
                    'name' => $area->area_name,
                    'rating' => $avgRating,
                    'review_count' => count($reviews),
                    'area_id' => $area->id,
                    'business_service_id' => $area->business_service_id,
                    'business_service_name' => $area->business_service->name ?? 'Unknown'
                ];
            }
        }

        if (empty($performanceData)) {
            $areas = BusinessArea::where('business_id', $businessId)
                ->where('is_active', true)
                ->with(['business_service'])
                ->get();

            foreach ($areas as $area) {
                $reviewCount = ReviewNew::where('business_id', $businessId)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->whereHas('review_business_services', function ($query) use ($area) {
                        $query->where('business_area_id', $area->id);
                    })
                    ->globalFilters(0, $businessId)
                    ->withCalculatedRating()
                    ->count();

                $reviewsForArea = ReviewNew::where('business_id', $businessId)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->whereHas('review_business_services', function ($query) use ($area) {
                        $query->where('business_area_id', $area->id);
                    })
                    ->globalFilters(0, $businessId)
                    ->withCalculatedRating()
                    ->get();

                if ($reviewsForArea->isNotEmpty()) {
                    $avgRating = round($reviewsForArea->avg('calculated_rating'), 1);

                    $performanceData[] = [
                        'name' => $area->area_name,
                        'rating' => $avgRating,
                        'review_count' => $reviewCount,
                        'area_id' => $area->id,
                        'business_service_id' => $area->business_service_id,
                        'business_service_name' => $area->business_service->name ?? 'Unknown'
                    ];
                }
            }
        }

        usort($performanceData, function ($a, $b) {
            return $b['rating'] <=> $a['rating'];
        });

        return array_slice($performanceData, 0, 3);
    }

    /**
     * Get top performing staff from top/worst analysis dynamically
     */
    public static function getTopPerformingStaffFromTopWorst($businessId, $dateRange)
    {
        $staffAnalysis = self::getTopWorstStaff($businessId, $dateRange, 3, 'rating');

        if (empty($staffAnalysis['top_staff'])) {
            return [];
        }

        $topStaff = [];

        foreach ($staffAnalysis['top_staff'] as $staff) {
            $staffUser = User::with("branches")
                ->where('id', $staff['staff_id'])
                ->first();

            if (!$staffUser) {
                continue;
            }

            $name = $staffUser->name;
            $nameParts = explode(' ', $name);
            $formattedName = $nameParts[0] . ' ' .
                (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) . '.' : '');

            $topStaff[] = [
                'staff_id' => $staff['staff_id'],
                'name' => $formattedName,
                'role' => $staffUser->job_title ?? 'Staff',
                'branches' => $staffUser->branches,
                'rating' => round($staff['avg_rating'], 1),
                'review_count' => $staff['review_count'] ?? 0,
                'image' => $staffUser->image ?? null
            ];
        }

        return $topStaff;
    }

    /**
     * Get top and worst staff dynamically
     */
    public static function getTopWorstStaff($businessId, $dateRange, $limit = 3, $criteria = 'rating')
    {
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
                'top_staff' => [],
                'worst_staff' => []
            ];
        }

        $groupedReviews = [];
        foreach ($staffReviews as $review) {
            if ($review->staff_id) {
                $groupedReviews[$review->staff_id][] = $review;
            }
        }

        $staffPerformance = [];

        foreach ($groupedReviews as $staffId => $reviews) {
            $staff = User::find($staffId);
            if (!$staff) {
                continue;
            }

            $totalRating = 0;
            $reviewCount = count($reviews);
            $positiveCount = 0;
            $negativeCount = 0;
            $totalSentiment = 0;
            $latestReviewDate = null;

            $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
            $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

            foreach ($reviews as $review) {
                $totalRating += $review->calculated_rating ?? 0;

                $sentimentScore = $review->sentiment_score ?? 0;
                $totalSentiment += $sentimentScore;

                if ($sentimentScore >= $positiveThreshold) {
                    $positiveCount++;
                } elseif ($sentimentScore < $negativeThreshold) {
                    $negativeCount++;
                }

                if (!$latestReviewDate || $review->created_at > $latestReviewDate) {
                    $latestReviewDate = $review->created_at;
                }
            }

            $avgRating = $reviewCount > 0 ? $totalRating / $reviewCount : 0;
            $avgSentiment = $reviewCount > 0 ? $totalSentiment / $reviewCount : 0;

            $minimumReviews = RuleEngineService::getMinimumReviewsForStaffAnalysis();
            if ($reviewCount < $minimumReviews) {
                continue;
            }

            $sentimentPercentage = $reviewCount > 0 ? round(($positiveCount / $reviewCount) * 100) : 0;
            $negativePercentage = $reviewCount > 0 ? round(($negativeCount / $reviewCount) * 100) : 0;

            $commonPraise = RuleEngineService::extractCommonPraise(collect($reviews));

            $staffPerformance[] = [
                'staff_id' => $staffId,
                'staff_name' => $staff->name,
                'job_title' => $staff->job_title ?? 'Staff',
                'email' => $staff->email,
                'image' => $staff->image,
                'avg_rating' => round($avgRating, 2),
                'avg_sentiment' => round($avgSentiment, 3),
                'sentiment_label' => self::getSentimentLabel($avgSentiment),
                'sentiment_percentage' => $sentimentPercentage,
                'negative_percentage' => $negativePercentage,
                'review_count' => $reviewCount,
                'positive_reviews' => $positiveCount,
                'negative_reviews' => $negativeCount,
                'common_praise' => array_slice($commonPraise, 0, 3),
                'last_review_date' => $latestReviewDate ? $latestReviewDate->diffForHumans() : 'No reviews',
                'rating_trend' => \App\Services\Staff\StaffPerformanceService::calculateStaffRatingTrend(collect($reviews)),
                'performance_level' => RuleEngineService::identifyPerformanceLevel($avgRating, $avgSentiment, $negativePercentage)
            ];
        }

        if ($criteria === 'rating') {
            usort($staffPerformance, function ($a, $b) {
                return $b['avg_rating'] <=> $a['avg_rating'];
            });
            $topStaff = array_slice($staffPerformance, 0, $limit);

            usort($staffPerformance, function ($a, $b) {
                return $a['avg_rating'] <=> $b['avg_rating'];
            });
            $worstStaff = array_slice($staffPerformance, 0, $limit);
        } elseif ($criteria === 'sentiment') {
            usort($staffPerformance, function ($a, $b) {
                return $b['avg_sentiment'] <=> $a['avg_sentiment'];
            });
            $topStaff = array_slice($staffPerformance, 0, $limit);

            usort($staffPerformance, function ($a, $b) {
                return $a['avg_sentiment'] <=> $b['avg_sentiment'];
            });
            $worstStaff = array_slice($staffPerformance, 0, $limit);
        } else {
            usort($staffPerformance, function ($a, $b) {
                return $b['sentiment_percentage'] <=> $a['sentiment_percentage'];
            });
            $topStaff = array_slice($staffPerformance, 0, $limit);

            usort($staffPerformance, function ($a, $b) {
                return $b['negative_percentage'] <=> $a['negative_percentage'];
            });
            $worstStaff = array_slice($staffPerformance, 0, $limit);
        }

        $summary = RuleEngineService::generateTopWorstSummary($topStaff, $worstStaff, $staffPerformance);

        return [
            'top_staff' => $topStaff,
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
     * Extract top topic V2 dynamically
     */
    public static function extractTopTopicV2($reviews, $limit = 5)
    {
        $totalReviews = is_countable($reviews) ? count($reviews) : $reviews->count();

        if ($totalReviews === 0) {
            return [
                'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
                'all_topics' => [],
                'sources' => ['ai_topics' => 0, 'keyword_matches' => 0]
            ];
        }

        return RuleEngineService::extractTopicsV2($reviews, $limit);
    }
}
