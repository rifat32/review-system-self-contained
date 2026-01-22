<?php

namespace App\Services\AIProcessor;

use App\Models\Branch;
use App\Models\BusinessArea;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Business\BusinessAnalyticsService;
use App\Services\Review\ReviewService;
use App\Services\Staff\StaffPerformanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use getID3;
use Carbon\Carbon;
use App\Services\AIProcessor\InsightAggregationService;
use App\Services\AIProcessor\RecommendationGeneratorService;
use App\Services\Rule\RuleEngineService;
use App\Models\InsightRecord;

class AIProcessorService
{
    private RuleEngineService $ruleEngineService;
    private ReviewService $reviewService;
    private StaffPerformanceService $staffPerformanceService;
    private BusinessAnalyticsService $businessAnalyticsService;
    private InsightAggregationService $insightAggregationService;
    private RecommendationGeneratorService $recommendationGeneratorService;
    private ConfidenceCalculatorService $confidenceCalculatorService;

    public function __construct(
        RuleEngineService $ruleEngineService,
        InsightAggregationService $insightAggregationService,
        RecommendationGeneratorService $recommendationGeneratorService,
        ConfidenceCalculatorService $confidenceCalculatorService
    ) {
        $this->ruleEngineService = $ruleEngineService;
        $this->insightAggregationService = $insightAggregationService;
        $this->recommendationGeneratorService = $recommendationGeneratorService;
        $this->confidenceCalculatorService = $confidenceCalculatorService;
    }

    // ========== CORE DYNAMIC METHODS ==========

    /**
     * Get sentiment label from score - Dynamic version
     */


    /**
     * Find common issues dynamically
     */
    public static function findCommonIssues($reviews)
    {
        if ($reviews->isEmpty()) {
            return [];
        }

        $businessId = $reviews->first()->business_id ?? 0;
        $results = [];

        // Method 1: Check InsightRecord (Pre-aggregated by Cron)
        if ($businessId) {
            $insights = InsightRecord::where('business_id', $businessId)
                ->where('mentions_count', '>=', 2)
                ->orderByDesc('mentions_count')
                ->limit(5)
                ->get();

            if ($insights->isNotEmpty()) {
                return $insights->map(function ($insight) {
                    return [
                        'topic' => $insight->main_category,
                        'count' => $insight->mentions_count,
                        'description' => "Common issues related to {$insight->main_category} ({$insight->sub_category})",
                        'severity' => $insight->severity,
                        'trend' => $insight->trend
                    ];
                })->toArray();
            }
        }

        // Method 2: Fallback - On-the-fly aggregation from AI payloads
        foreach ($reviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $categories = $openaiData['category_analysis'] ?? [];
            foreach ($categories as $cat) {
                $topic = $cat['main_category'] ?? 'General';
                if (!isset($results[$topic])) {
                    $results[$topic] = [
                        'topic' => $topic,
                        'count' => 0,
                        'description' => "Issues related to $topic",
                        'severity' => $cat['severity'] ?? 'low'
                    ];
                }
                $results[$topic]['count']++;
            }
        }

        $sortedResults = array_values($results);
        usort($sortedResults, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($sortedResults, 0, 5);
    }


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
    public function getTopMentionedStaff($positiveReviews)
    {
        if ($positiveReviews->isEmpty()) {
            return [];
        }

        // Use rule engine to extract staff mentions
        $staffMentions = $this->ruleEngineService->extractStaffMentions($positiveReviews);

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
     * Extract opportunities from suggestions dynamically
     */
    public function extractOpportunitiesFromSuggestions($suggestions)
    {
        $opportunityKeywords = $this->ruleEngineService->getOpportunityKeywords();

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
    public function generatePredictions($reviews)
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
        $predictionData = $this->ruleEngineService->generateRatingPrediction($avgRating);

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
    public function transcribeAudio($filePath)
    {
        try {
            $api_key = env('HF_API_KEY');
            $audio = file_get_contents($filePath);

            Log::info("HF Transcription Started", [
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

            Log::info("HF Whisper API Response", [
                'http_status' => $status,
                'curl_error' => $error,
                'raw_result' => $result
            ]);

            if ($error) {
                Log::error("HF Whisper CURL Error: $error");
                return '';
            }

            $data = json_decode($result, true);

            Log::info("HF Whisper Decoded Response", [
                'decoded' => $data
            ]);

            return $data['text'] ?? '';
        } catch (\Exception $e) {
            Log::error("transcribeAudio() exception: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get recommendations from rule engine
     */
    public function getRecommendationsFromRuleEngine(int $businessId, $reviews, $dateRange): array
    {
        return $this->recommendationGeneratorService->generateFromInsights($businessId, 30);
    }

    /**
     * Generate branch recommendations dynamically
     */
    public function generateBranchRecommendations($reviews): array
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
            return $this->generateBranchRecommendationsFromRuleEngine($reviews, $businessId, $branchId);
        }

        return $this->generateDynamicRecommendations($reviews);
    }

    /**
     * Generate branch recommendations using rule engine
     */
    public function generateBranchRecommendationsFromRuleEngine($reviews, int $businessId, int $branchId): array
    {
        $recommendations = [];

        // Get aggregated insights for this branch
        // Get aggregated insights for this business (filtering by specific branch reviews in JSON is complex, 
        // so we retrieve recent business insights and can optionally filter in memory if strictly needed, 
        // but for recommendations, business-level insights are often relevant enough or the best we can do without a direct relationship)
        $branchInsights = InsightRecord::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
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
            if ($insight->mentions_count >= $this->ruleEngineService->getMinimumMentionsForRecommendation()) {
                $matchedRules = $this->ruleEngineService->matchRulesToInsight($insight);

                foreach ($matchedRules as $matched) {
                    $rule = $matched['rule'];
                    $recData = $this->ruleEngineService->generateRecommendation($rule, $insight);

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
                $staffTrainings = $this->ruleEngineService->getStaffTrainingRecommendations(0, $businessId);

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
    private function generateDynamicRecommendations($reviews): array
    {
        $recommendations = [];
        $totalReviews = $reviews->count();

        // Use thresholds from rule engine for grouping
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold);

        // Analyze staff intelligence directly from AI payloads
        $staffPerformance = [];
        foreach ($positiveReviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $staffEvaluation = $openaiData['staff_intelligence']['performance_evaluation'] ?? null;
            if ($staffEvaluation) {
                $staffPerformance[] = $staffEvaluation;
            }
        }

        if (count($staffPerformance) >= 3) {
            $recommendations[] = [
                'type' => 'Strength',
                'title' => 'Positive Staff Mentions',
                'description' => 'Customers are consistently praising staff performance and professionalism.',
                'evidence_count' => count($staffPerformance),
                'priority' => 'low'
            ];
        }

        // Find common issues using the AI-driven method
        $issues = self::findCommonIssues($reviews);

        foreach ($issues as $issue) {
            if ($issue['count'] >= 2 && count($recommendations) < 3) {
                $recommendations[] = [
                    'type' => 'Weak Area',
                    'title' => $issue['topic'],
                    'description' => "Recognized trend in the '{$issue['topic']}' category. (mentioned {$issue['count']} times)",
                    'evidence_count' => $issue['count'],
                    'priority' => $issue['count'] >= $this->ruleEngineService->getHighPriorityThreshold() ? 'high' : 'medium'
                ];
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'Info',
                'title' => 'Insufficient Qualitative Data',
                'description' => 'Not enough specific feedback patterns to generate automated recommendations.'
            ];
        }

        return array_slice($recommendations, 0, 3);
    }


    /**
     * Get branch comparison data dynamically
     */
    public function getBranchComparisonData($branch, $startDate, $endDate)
    {
        $businessId = $branch->business_id;

        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branch->id)
            ->globalReviewFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->get();

        $totalReviews = $reviews->count();
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        // Use dynamic thresholds
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();
        $aiSentimentScore = $totalReviews > 0 ? round(($positiveReviews / $totalReviews) * 100) : 0;

        $csatThreshold = $this->ruleEngineService->getCsatThreshold();
        $csatCount = $reviews->filter(function ($review) use ($csatThreshold) {
            return ($review->calculated_rating ?? 0) >= $csatThreshold;
        })->count();

        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        $staffPerformance = $this->getBranchStaffPerformance($branch->id, $businessId, $startDate, $endDate);
        $topTopics = $this->extractBranchTopics($reviews);

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
    public function getBranchStaffPerformance($branchId, $businessId, $startDate, $endDate)
    {
        $staffReviews = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->globalReviewFilters(0, $businessId, 1)
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
    public function extractBranchTopics($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $categories = $openaiData['category_analysis'] ?? [];
            foreach ($categories as $cat) {
                $topic = $cat['main_category'] ?? 'General';
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;

                if (isset($cat['sub_category'])) {
                    $sub = $cat['sub_category'];
                    $topicCounts[$sub] = ($topicCounts[$sub] ?? 0) + 1;
                }
            }
        }

        arsort($topicCounts);
        return $topicCounts;
    }

    /**
     * Generate branch comparison insights dynamically
     */
    public function generateBranchComparisonInsights($branchesData, $allMetrics)
    {
        if (count($branchesData) === 0) {
            return [
                'overview' => 'No branch data available for comparison.',
                'key_findings' => []
            ];
        }

        return $this->ruleEngineService->generateBranchComparisonInsights($branchesData);
    }

    /**
     * Generate comparison highlights dynamically
     */
    public function generateComparisonHighlights($branchesData)
    {
        if (count($branchesData) < 2) {
            return [];
        }

        return $this->ruleEngineService->generateComparisonHighlights($branchesData);
    }

    /**
     * Get sentiment trend over time dynamically
     */
    public function getSentimentTrendOverTime($branches, $startDate, $endDate)
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
                    ->globalReviewFilters(0, $branch->business_id)
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
    public function getStaffComplaintsByBranch($branches, $startDate, $endDate)
    {
        $complaintsByBranch = [];

        foreach ($branches as $branch) {
            $reviews = ReviewNew::where('business_id', $branch->business_id)
                ->where('branch_id', $branch->id)
                ->globalReviewFilters(0, $branch->business_id, 1)
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
    public function calculateBranchSummary($reviews)
    {
        $totalReviews = $reviews->count();
        $averageRating = $reviews->avg('calculated_rating') ?? 0;

        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $positiveReviews = $reviews->where('sentiment_score', '>=', $positiveThreshold)->count();


        $sentiment = $this->ruleEngineService->determineOverallSentiment($positiveReviews, $totalReviews);



        $csatThreshold = $this->ruleEngineService->getCsatThreshold();
        $csatCount = $reviews->filter(function ($review) use ($csatThreshold) {
            return ($review->calculated_rating ?? 0) >= $csatThreshold;
        })->count();


        $csatScore = $totalReviews > 0 ? round(($csatCount / $totalReviews) * 100) : 0;

        $topTopic = $this->extractTopTopic($reviews);


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
    public function extractTopTopic($reviews)
    {
        $topicCounts = [];

        foreach ($reviews as $review) {
            $openaiData = is_string($review->openai_raw_response)
                ? json_decode($review->openai_raw_response, true)
                : ($review->openai_raw_response ?? []);

            $categories = $openaiData['category_analysis'] ?? [];
            foreach ($categories as $cat) {
                $topic = $cat['main_category'] ?? 'General';
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
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


    /**
     * Get staff evaluation dynamically
     */
    public function getStaffEvaluation($avgRating, $reviewCount)
    {
        if ($reviewCount < $this->ruleEngineService->getMinimumReviewsForStaffEvaluation()) {
            return $this->ruleEngineService->getInsufficientDataMessage();
        }

        return $this->ruleEngineService->getStaffEvaluationFromRating($avgRating);
    }

    /**
     * Generate action item dynamically
     */
    public function generateActionItem($issue, $evidenceCount)
    {
        $actionData = $this->ruleEngineService->generateActionForIssue($issue, $evidenceCount);

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
    public function calculateStaffMetricsFromReviewValue($reviews, $staffUser)
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

        $topics = $this->extractTopicsFromReviews($reviews);
        $performanceByCategory = $this->calculatePerformanceByCategory($reviews);
        $notableReviews = $this->getNotableReviews($reviews);

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
     * Get staff performance dynamically for a branch or business
     */
    public function getStaffPerformance($branchId, $businessId, $startDate, $endDate, $limit = 10)
    {
        /** @var StaffPerformanceService $staffPerformanceService */
        $staffPerformanceService = app(StaffPerformanceService::class);

        $query = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('staff_id')
            ->with(['staff'])
            ->globalReviewFilters(0, $businessId)
            ->withCalculatedRating(); // Ensure metrics use calculated rating

        if ($branchId) {
            // Filter by reviews where the staff member belongs to this branch
            $query->whereHas('staff.branches', function ($q) use ($branchId) {
                $q->where('branches.id', $branchId);
            });
        }

        $reviews = $query->get();

        // Calculate metrics using StaffPerformanceService logic
        $metrics = $staffPerformanceService->getAllStaffMetricsFromReviewValue($reviews);

        // Sort by average rating descending (default from service) but ensure consistency
        // limit logic
        return array_slice($metrics, 0, $limit);
    }

    /**
     * Extract topics from reviews dynamically
     */
    public function extractTopicsFromReviews($reviews)
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
    public function calculatePerformanceByCategory($reviews)
    {
        $performanceCategories = $this->ruleEngineService->getPerformanceCategories();

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
    public function getNotableReviews($reviews, $limit = 2)
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
    public function getSentimentGapMessage($gap)
    {
        return $this->ruleEngineService->getSentimentGapMessage($gap);
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
     * Calculate performance overview from review value dynamically
     */
    public function calculatePerformanceOverviewFromReviewValue($reviews)
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
    public function getReviewSamples($reviews, $limit = 2)
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
     * Get rating gap message dynamically
     */
    public function getRatingGapMessage($gap)
    {
        return $this->ruleEngineService->getRatingGapMessage($gap);
    }

    /**
     * Get recommended training dynamically
     */
    public function getRecommendedTraining($reviews)
    {
        return $this->ruleEngineService->getTrainingRecommendations($reviews);
    }

    /**
     * Analyze skill gaps dynamically
     */
    public function analyzeSkillGaps($reviews)
    {
        return $this->ruleEngineService->analyzeSkillGaps($reviews);
    }

    /**
     * Calculate customer tone dynamically
     */
    public function calculateCustomerTone($reviews)
    {
        return $this->ruleEngineService->calculateCustomerTone($reviews);
    }

    /**
     * Calculate sentiment distribution dynamically
     */
    public function calculateSentimentDistribution($reviews)
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
    public function calculateComplimentRatio($reviews)
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
    public function getAllStaffMetricsFromReviewValue($reviews)
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
    public function generateAiSummary($reviews)
    {
        // Use lazy loading to break circular dependency
        $businessAnalyticsService = app()->make(\App\Services\Business\BusinessAnalyticsService::class);
        return $businessAnalyticsService->generateAiSummaryFromRuleEngine(0, $reviews);
    }

    /**
     * Extract issues from suggestions dynamically
     */
    public function extractIssuesFromSuggestions($suggestions, $reviews = null)
    {
        // suggestions are already pre-extracted issues from OpenAI
        $issues = collect($suggestions)
            ->map(fn($s) => [
                'issue' => (string)$s,
                'mention_count' => 1
            ])
            ->take(3)
            ->values();

        return $issues->isEmpty() ? [
            [
                'issue' => 'Analysis completed.',
                'mention_count' => 0
            ]
        ] : $issues->toArray();
    }



    /**
     * Get audio duration
     */
    public function getAudioDuration($filePath)
    {
        try {
            // This is external library, not a service, so direct instantiation is fine
            $getID3 = new \getID3();
            $fileInfo = $getID3->analyze($filePath);
            return $fileInfo['playtime_seconds'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get sentiment label by percentage dynamically
     */
    public function getSentimentLabelByPercentage($percentage)
    {
        return $this->ruleEngineService->getSentimentLabelByPercentage($percentage);
    }

    /**
     * Calculate aggregated sentiment dynamically
     */
    public function calculateAggregatedSentiment($reviews)
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
    public function extractCommonTopics($reviews, $limit = 5)
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
    public function generateDashboardInsights($reviews)
    {
        $sentimentData = $this->calculateAggregatedSentiment($reviews);
        $topTopics = $this->extractCommonTopics($reviews, 3);

        return $this->ruleEngineService->generateDashboardInsights($sentimentData, $topTopics, $reviews->count());
    }

    /**
     * Get insights overview dynamically
     */
    public function getInsightsOverview($businessId, $dateRange)
    {
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalReviewFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();


        $topIssues = $this->extractTopIssuesFromReviews($reviews);

        $performanceByBranch = $this->getPerformanceByBranch($businessId, $dateRange);

        $performanceByArea = $this->getPerformanceByArea($businessId, $dateRange);


        $topPerformingStaff = $this->getTopPerformingStaffFromTopWorst($businessId, $dateRange);

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
    public function extractTopIssuesFromReviews($reviews)
    {
        if ($reviews->isEmpty()) {
            return [
                ['issue' => 'No data', 'percentage' => 0]
            ];
        }

        $commonIssues = $this->findCommonIssues($reviews);
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

    public function getPerformanceByBranch($businessId, $dateRange)
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
                ->globalReviewFilters(0, $businessId)
                ->withCalculatedRating()
                ->get();

            if ($reviews->isNotEmpty()) {

                $branchSummary = $this->calculateBranchSummary($reviews);


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
    public function getPerformanceByArea($businessId, $dateRange)
    {
        $areasWithReviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->globalReviewFilters(0, $businessId)
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
                    ->globalReviewFilters(0, $businessId)
                    ->withCalculatedRating()
                    ->count();

                $reviewsForArea = ReviewNew::where('business_id', $businessId)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->whereHas('review_business_services', function ($query) use ($area) {
                        $query->where('business_area_id', $area->id);
                    })
                    ->globalReviewFilters(0, $businessId)
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
    public function getTopPerformingStaffFromTopWorst($businessId, $dateRange)
    {
        $staffAnalysis = $this->getTopWorstStaff($businessId, $dateRange, 3, 'rating');

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
    public function getTopWorstStaff($businessId, $dateRange, $limit = 3, $criteria = 'rating')
    {
        $staffReviews = ReviewNew::with('staff')
            ->where('business_id', $businessId)
            ->globalReviewFilters(0, $businessId)
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

            $minimumReviews = $this->ruleEngineService->getMinimumReviewsForStaffAnalysis();
            if ($reviewCount < $minimumReviews) {
                continue;
            }

            $sentimentPercentage = $reviewCount > 0 ? round(($positiveCount / $reviewCount) * 100) : 0;
            $negativePercentage = $reviewCount > 0 ? round(($negativeCount / $reviewCount) * 100) : 0;

            $commonPraise = $this->ruleEngineService->extractCommonPraise(collect($reviews));

            // Use lazy loading for StaffPerformanceService
            $staffPerformanceService = app()->make(\App\Services\Staff\StaffPerformanceService::class);
            $suggestions = $staffPerformanceService->extractSuggestionsFromReviews(collect($reviews));

            $performanceScore = $this->ruleEngineService->identifyPerformanceLevel($avgRating, $avgSentiment, $negativePercentage);

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
                'recommended_training' => $staffPerformanceService->extractRecommendedTraining($suggestions),
                'skill_gaps' => $staffPerformanceService->extractSkillGapsFromSuggestions($suggestions),
                'rating_trend' => $staffPerformanceService->calculateStaffRatingTrend(collect($reviews)),
                'performance_level' => $performanceScore
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

        $summary = $this->ruleEngineService->generateTopWorstSummary($topStaff, $worstStaff, $staffPerformance);

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
    public function extractTopTopicV2($reviews, $limit = 5)
    {
        $totalReviews = is_countable($reviews) ? count($reviews) : $reviews->count();

        if ($totalReviews === 0) {
            return [
                'top_topic' => ['name' => 'General', 'count' => 0, 'percentage' => 0],
                'all_topics' => [],
                'sources' => ['ai_topics' => 0, 'keyword_matches' => 0]
            ];
        }

        return $this->ruleEngineService->extractTopicsV2($reviews, $limit);
    }
}
