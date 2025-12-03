<?php

namespace App\Http\Controllers;

use App\Models\GuestUser;
use App\Models\Question;
use App\Models\QusetionStar;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\Survey;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\StarTag;
use App\Models\StarTagQuestion;
use App\Models\SurveyQuestion;
use App\Models\Tag;
use App\Models\User;
use App\Http\Requests\SetOverallQuestionRequest;
use App\Http\Requests\StoreTagMultipleRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReviewNewController extends Controller
{



// ##################################################
// Get Overall Business Dashboard Data
// ##################################################

/**
 * @OA\Get(
 *      path="/v1.0/reviews/overall-dashboard/{businessId}",
 *      operationId="getOverallDashboard",
 *      tags={"review.dashboard"},
 *      security={{"bearerAuth": {}}},
 *      summary="Get overall business dashboard data",
 *      description="Get comprehensive dashboard data with AI insights and analytics",
 *      @OA\Parameter(
 *          name="businessId",
 *          in="path",
 *          required=true,
 *          example="1"
 *      ),
 *      @OA\Parameter(
 *          name="period",
 *          in="query",
 *          required=false,
 *          description="Period: last_30_days, last_7_days, this_month, last_month",
 *          example="last_30_days"
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Successful operation",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Dashboard data retrieved successfully"),
 *              @OA\Property(property="data", type="object")
 *          )
 *      )
 * )
 */
public function getOverallDashboard($businessId, Request $request)
{
    $filterable_fields = [
        "last_30_days",
        "last_7_days",
        "this_month",
        "last_month"  
    ];
    
    $business = Business::findOrFail($businessId);
    
    if (!in_array($request->get('period', 'last_30_days'), $filterable_fields)) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid period. Only allowed' . implode(', ', $filterable_fields),
            'data' => null
        ], 400);
    }    
    $period = $request->get('period', 'last_30_days');
    
    // Get period dates
    $dateRange = $this->getDateRange($period);
    
    // Calculate metrics using existing methods
    $metrics = $this->calculateDashboardMetrics($businessId, $dateRange);
    
    // Get rating breakdown using existing getAverage method logic
    $ratingBreakdown = $this->calculateRatingBreakdown($businessId, $dateRange);
    
    // Get AI insights using existing AI pipeline
    $aiInsights = $this->getAiInsightsPanel($businessId, $dateRange);
    
    // Get staff performance using existing staff suggestions
    $staffPerformance = $this->getStaffPerformanceSnapshot($businessId, $dateRange);
    
    // Get recent reviews feed
    $reviewFeed = $this->getReviewFeed($businessId, $dateRange);
    
    // Get available filters
    $filters = $this->getAvailableFilters($businessId);
    
    return response()->json([
        'success' => true,
        'message' => 'Dashboard data retrieved successfully',
        'data' => [
            'metrics' => $metrics,
            'rating_breakdown' => $ratingBreakdown,
            'ai_insights_panel' => $aiInsights,
            'staff_performance_snapshot' => $staffPerformance,
            'review_feed' => $reviewFeed,
            'filters' => $filters
        ]
    ], 200);
}



// ##################################################
// Voice Review Submission
// ##################################################

/**
 * @OA\Post(
 *      path="/v1.0/reviews/voice/{businessId}",
 *      operationId="storeVoiceReview",
 *      tags={"review"},
 *      security={{"bearerAuth": {}}},
 *      summary="Submit a voice review",
 *      description="Submit voice recording with transcription and AI analysis",
 *      @OA\Parameter(
 *          name="businessId",
 *          in="path",
 *          required=true,
 *          example="1"
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          content={
 *              @OA\MediaType(
 *                  mediaType="multipart/form-data",
 *                  @OA\Schema(
 *                      required={"audio"},
 *                      @OA\Property(property="audio", type="string", format="binary"),
 *                      @OA\Property(property="rate", type="number", example=4),
 *                      @OA\Property(property="staff_id", type="integer", example=1),
 *                      @OA\Property(property="description", type="string", example="Voice feedback"),
 *                      @OA\Property(property="values", type="string", example="[{'question_id':1,'tag_id':2,'star_id':4}]"),
 *                      @OA\Property(property="is_overall", type="boolean", example=true),
 *                      @OA\Property(property="guest_full_name", type="string", example="John Doe"),
 *                      @OA\Property(property="guest_phone", type="string", example="1234567890")
 *                  )
 *              )
 *          }
 *      ),
 *      @OA\Response(
 *          response=201,
 *          description="Voice review submitted successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Voice review submitted successfully"),
 *              @OA\Property(property="data", type="object")
 *          )
 *      )
 * )
 */
public function storeVoiceReview($businessId, Request $request)
{
    $request->validate([
        'audio' => 'required|file|mimes:mp3,wav,m4a,ogg|max:10240', // 10MB max
        'rate' => 'required|numeric|min:1|max:5',
        'staff_id' => 'required|numeric|exists:users,id',
        'description' => 'required|string',
        'values' => 'required|string',
        'is_overall' => 'required|boolean',
        'guest_full_name' => 'nullable|string',
        'guest_phone' => 'nullable|string',
    ]);
    
    $business = Business::findOrFail($businessId);
    
    // Store audio file
    $audioPath = $request->file('audio')->store('voice-reviews', 'public');
    $audioUrl = Storage::url($audioPath);
    
    // Transcribe using existing transcribeAudio method
    $transcription = $this->transcribeAudio($request->file('audio')->getRealPath());
    
    // Create review with voice metadata
    $reviewData = [
        'description' => $request->description ?? 'Voice Review',
        'rate' => $request->rate,
        'comment' => $transcription,
        'raw_text' => $transcription,
        'business_id' => $businessId,
        'staff_id' => $request->staff_id,
        'is_overall' => $request->is_overall ?? false,
        'is_voice_review' => true,
        'voice_url' => $audioUrl,
        'voice_duration' => $this->getAudioDuration($request->file('audio')->getRealPath()),
        'transcription_metadata' => [
            'audio_path' => $audioPath,
            'file_size' => $request->file('audio')->getSize(),
            'mime_type' => $request->file('audio')->getMimeType(),
        ]
    ];
    
    // Add user/guest info
    if ($request->user()) {
        $reviewData['user_id'] = $request->user()->id;
    } else {
        $guest = GuestUser::create([
            'full_name' => $request->guest_full_name ?? 'Voice User',
            'phone' => $request->guest_phone ?? '0000000000',
        ]);
        $reviewData['guest_id'] = $guest->id;
    }
    
    // Run AI analysis using existing pipeline
    $reviewData['sentiment_score'] = $this->analyzeSentiment($transcription);
    $reviewData['topics'] = $this->extractTopics($transcription);
    $reviewData['moderation_results'] = $this->aiModeration($transcription);
    $reviewData['ai_suggestions'] = $this->generateRecommendations($transcription, $reviewData['topics'], $reviewData['sentiment_score'], $businessId);
    
    if ($request->staff_id) {
        $reviewData['staff_suggestions'] = $this->analyzeStaffPerformance($transcription, $request->staff_id, $businessId);
    }
    
    // Create review
    $review = ReviewNew::create($reviewData);
    
    // Store review values if provided
    if ($request->has('values')) {
        $values = json_decode($request->values, true);
        $this->storeReviewValues($review, $values, $business);
    }
    
    return response()->json([
        'success' => true,
        'message' => 'Voice review submitted successfully',
        'data' => [
            'review_id' => $review->id,
            'transcription' => $transcription,
            'voice_url' => $audioUrl,
            'duration' => $reviewData['voice_duration'],
            'ai_analysis' => [
                'sentiment_score' => $reviewData['sentiment_score'],
                'topics' => $reviewData['topics'],
                'ai_suggestions' => $reviewData['ai_suggestions']
            ]
        ]
    ], 201);
}

// ##################################################
// Update Business Settings
// ##################################################

/**
 * @OA\Put(
 *      path="/v1.0/businesses/{businessId}/review-settings",
 *      operationId="updateReviewSettings",
 *      tags={"business.settings"},
 *      security={{"bearerAuth": {}}},
 *      summary="Update business review settings",
 *      description="Update customer flow and review settings",
 *      @OA\Parameter(
 *          name="businessId",
 *          in="path",
 *          required=true,
 *          example="1"
 *      ),
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"enable_detailed_survey"},
 *              @OA\Property(property="enable_detailed_survey", type="boolean", example=true),
 *              @OA\Property(property="detailed_survey_threshold", type="integer", example=4),
 *              @OA\Property(property="export_settings", type="object", example={"format": "csv", "include_comments": true})
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="Settings updated successfully",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Review settings updated successfully"),
 *              @OA\Property(property="data", type="object")
 *          )
 *      )
 * )
 */
public function updateReviewSettings($businessId, Request $request)
{
    $business = Business::findOrFail($businessId);
    
    // Verify ownership
    if (!$request->user()->hasRole('superadmin') && $business->OwnerID != $request->user()->id) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized to update settings'
        ], 403);
    }
    
    $validated = $request->validate([
        'enable_detailed_survey' => 'boolean',
        'detailed_survey_threshold' => 'integer|min:1|max:5',
        'export_settings' => 'json'
    ]);
    
    $business->update($validated);
    
    return response()->json([
        'success' => true,
        'message' => 'Review settings updated successfully',
        'data' => $business->only([
            'id', 
            'enable_detailed_survey', 
            'detailed_survey_threshold', 
            'export_settings'
        ])
    ], 200);
}

// ##################################################
// Helper Methods
// ##################################################

private function getDateRange($period)
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

private function calculatePercentageChange($current, $previous)
{
    if ($previous == 0) return 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

private function getSentimentLabel($score)
{
    if ($score === null) return 'neutral';
    return $score >= 0.7 ? 'positive' : ($score >= 0.4 ? 'neutral' : 'negative');
}

private function getAudioDuration($filePath)
{
    try {
        $getID3 = new \getID3();
        $fileInfo = $getID3->analyze($filePath);
        return $fileInfo['playtime_seconds'] ?? null;
    } catch (\Exception $e) {
        return null;
    }
}

private function generateAiSummary($reviews)
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

private function extractIssuesFromSuggestions($suggestions)
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

private function extractOpportunitiesFromSuggestions($suggestions)
{
    return collect($suggestions)
        ->filter(fn($s) => stripos($s, 'add') !== false || stripos($s, 'highlight') !== false)
        ->take(2)
        ->values()
        ->toArray();
}

private function generatePredictions($reviews)
{
    $avgRating = $reviews->avg('rate') ?? 0;
    $predictedIncrease = max(0, 5 - $avgRating) * 0.05;
    
    return [[
        'prediction' => 'Improving identified issues could boost overall rating.',
        'estimated_impact' => '+' . round($predictedIncrease, 2) . ' points'
    ]];
}

private function extractSkillGapsFromSuggestions($suggestions)
{
    return $suggestions
        ->filter(fn($s) => stripos($s, 'needs') !== false || stripos($s, 'requires') !== false)
        ->map(fn($s) => preg_replace('/.*needs\s+(.*?) training.*/i', '$1', $s))
        ->filter(fn($s) => strlen($s) > 3)
        ->values()
        ->toArray();
}

private function getAvailableFilters($businessId)
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

private function calculateDashboardMetrics($businessId, $dateRange)
{
    // Use existing methods to calculate
    $reviews = ReviewNew::where('business_id', $businessId)
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->get();
    
    $previousReviews = ReviewNew::where('business_id', $businessId)
        ->whereBetween('created_at', [
            $dateRange['start']->copy()->subDays(30),
            $dateRange['end']->copy()->subDays(30)
        ])
        ->get();
    
    $total = $reviews->count();
    $previousTotal = $previousReviews->count();
    
    return [
        'avg_overall_rating' => [
            'value' => round($reviews->avg('rate') ?? 0, 1),
            'change' => $this->calculatePercentageChange(
                $reviews->avg('rate') ?? 0,
                $previousReviews->avg('rate') ?? 0
            )
        ],
        'ai_sentiment_score' => [
            'value' => round(($reviews->avg('sentiment_score') ?? 0) * 10, 1),
            'max' => 10,
            'change' => $this->calculatePercentageChange(
                $reviews->avg('sentiment_score') ?? 0,
                $previousReviews->avg('sentiment_score') ?? 0
            )
        ],
        'total_reviews' => [
            'value' => $total,
            'change' => $this->calculatePercentageChange($total, $previousTotal)
        ],
        'positive_negative_ratio' => [
            'positive' => round(($reviews->where('sentiment_score', '>=', 0.7)->count() / max($total, 1)) * 100),
            'negative' => round(($reviews->where('sentiment_score', '<', 0.4)->count() / max($total, 1)) * 100)
        ],
        'staff_linked_reviews' => [
            'percentage' => round(($reviews->whereNotNull('staff_id')->count() / max($total, 1)) * 100),
            'count' => $reviews->whereNotNull('staff_id')->count(),
            'total' => $total
        ],
        'voice_reviews' => [
            'percentage' => round(($reviews->where('is_voice_review', true)->count() / max($total, 1)) * 100),
            'count' => $reviews->where('is_voice_review', true)->count(),
            'total' => $total
        ]
    ];
}

private function calculateRatingBreakdown($businessId, $dateRange)
{
    $reviews = ReviewNew::where('business_id', $businessId)
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->get();
    
    $total = $reviews->count();
    
    return [
        'excellent' => [
            'percentage' => round(($reviews->where('rate', 5)->count() / max($total, 1)) * 100),
            'count' => $reviews->where('rate', 5)->count()
        ],
        'good' => [
            'percentage' => round(($reviews->where('rate', 4)->count() / max($total, 1)) * 100),
            'count' => $reviews->where('rate', 4)->count()
        ],
        'average' => [
            'percentage' => round(($reviews->where('rate', 3)->count() / max($total, 1)) * 100),
            'count' => $reviews->where('rate', 3)->count()
        ],
        'poor' => [
            'percentage' => round(($reviews->whereIn('rate', [1, 2])->count() / max($total, 1)) * 100),
            'count' => $reviews->whereIn('rate', [1, 2])->count()
        ],
        'avg_rating' => round($reviews->avg('rate') ?? 0, 1)
    ];
}

private function getAiInsightsPanel($businessId, $dateRange)
{
    // Use existing AI suggestions and topics
    $reviews = ReviewNew::where('business_id', $businessId)
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->whereNotNull('ai_suggestions')
        ->get();
    
    // Extract common themes from existing AI suggestions
    $allSuggestions = $reviews->pluck('ai_suggestions')->flatten();
    $allTopics = $reviews->pluck('topics')->flatten();
    
    return [
        'summary' => $this->generateAiSummary($reviews),
        'detected_issues' => $this->extractIssuesFromSuggestions($allSuggestions),
        'opportunities' => $this->extractOpportunitiesFromSuggestions($allSuggestions),
        'predictions' => $this->generatePredictions($reviews)
    ];
}

private function getStaffPerformanceSnapshot($businessId, $dateRange)
{
    // Use existing staff suggestions and reviews
    $staffReviews = ReviewNew::with('staff')
        ->where('business_id', $businessId)
        ->whereNotNull('staff_id')
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->get()
        ->groupBy('staff_id');
    
    $staffData = [];
    
    foreach ($staffReviews as $staffId => $reviews) {
        if ($reviews->count() < 3) continue; // Minimum reviews
        
        $staff = $reviews->first()->staff;
        $avgRating = $reviews->avg('rate');
        $staffSuggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();
        
        $staffData[] = [
            'id' => $staffId,
            'name' => $staff->name,
            'rating' => round($avgRating, 1),
            'review_count' => $reviews->count(),
            'skill_gaps' => $this->extractSkillGapsFromSuggestions($staffSuggestions),
            'recommended_training' => $staffSuggestions->first() ?? 'General Training'
        ];
    }
    
    // Sort by rating
    usort($staffData, fn($a, $b) => $b['rating'] <=> $a['rating']);
    
    $top = array_slice($staffData, 0, 3);
    $needsImprovement = array_slice(array_reverse($staffData), 0, 3);
    
    return [
        'top_performing' => $top,
        'needs_improvement' => $needsImprovement
    ];
}

private function getReviewFeed($businessId, $dateRange, $limit = 10)
{
    return ReviewNew::with(['user', 'guest_user', 'staff', 'value.tag'])
        ->where('business_id', $businessId)
        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get()
        ->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rate . '/5',
                'author' => $review->user?->name ?? $review->guest_user?->full_name ?? 'Anonymous',
                'time_ago' => $review->created_at->diffForHumans(),
                'comment' => $review->comment,
                'staff_name' => $review->staff?->name,
                'tags' => $review->value->map(fn($v) => $v->tag->tag ?? null)->filter()->unique()->values()->toArray(),
                'is_voice' => $review->is_voice_review,
                'sentiment' => $this->getSentimentLabel($review->sentiment_score),
                'is_ai_flagged' => !empty($review->moderation_results['issues_found'] ?? [])
            ];
        });
}











    // ##################################################
    // This method is to store   ReviewValue
    // ##################################################

    public function store($businessId, $rate, Request $request)
    {

        ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $rate
        ])
            ->delete();

        $reviewValues = $request->reviewvalue;
        $raviewValue_array = [];
        foreach ($reviewValues as $reviewValue) {
            $reviewValue["business_id"] = $businessId;
            $reviewValue["rate"] = $rate;
            $createdReviewValue =  ReviewValue::create($reviewValue);
            array_push($raviewValue_array, $createdReviewValue);
        }

        return response($raviewValue_array, 201);
    }
    // ##################################################
    // This method is to get   ReviewValue
    // ##################################################

    /**
     *
     * @OA\Get(
     *      path="v1.0/review-new/get-values/{businessId}/{rate}",
     *      operationId="getReviewValues",
     *      tags={"review"},
     *   *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="rate",
     * in="path",
     * description="rate",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to get   Review Value",
     *      description="This method is to get   Review Value",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Review values retrieved successfully"),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */



    public function getReviewValues($businessId, $rate, Request $request)
    {
        // with
        $reviewValues = ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $rate,

        ])->get();


        return response([
            "success" => true,
            "message" => "Review values retrieved successfully",
            "data" => $reviewValues
        ], 200);
    }
    // ##################################################
    // This method is to get ReviewValue by id
    // ##################################################

    public function getreviewvalueById($businessId, Request $request)
    {
        // with
        $reviewValues = ReviewValue::where([
            "business_id" => $businessId
        ])
            ->first();


        return response($reviewValues, 200);
    }
    // ##################################################
    // This method is to get average
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/getavg/review/{businessId}/{start}/{end}",
     *      operationId="getAverage",
     *      tags={"z.unused"},
     *         security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="start",
     * in="path",
     * description="from date",
     * required=true,
     * example="2019-06-29"
     * ),
     *  @OA\Parameter(
     * name="end",
     * in="path",
     * description="to date",
     * required=true,
     * example="2026-06-29"
     * ),
     *      summary="This method is to get average",
     *      description="This method is to get average",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function  getAverage($businessId, $start, $end, Request $request)
    {
        // with
        $reviews = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->globalFilters()
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->get();

        $data["total"]   = $reviews->count();
        $data["one"]   = 0;
        $data["two"]   = 0;
        $data["three"] = 0;
        $data["four"]  = 0;
        $data["five"]  = 0;
        foreach ($reviews as $review) {
            switch ($review->rate) {
                case 1:
                    $data["one"] += 1;
                    break;
                case 2:
                    $data["two"] += 1;
                    break;
                case 3:
                    $data["three"] += 1;
                    break;
                case 4:
                    $data["four"] += 1;
                    break;
                case 5:
                    $data["five"] += 1;
                    break;
            }
        }


        return response($data, 200);
    }

    // ##################################################
    // This method is to store   ReviewValue2
    // ##################################################
    public function store2($businessId, Request $request)
    {

        ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $request->rate
        ])
            ->delete();
        $reviewValue = [
            "tag" => $request->tag,
            "rate" => $request->rate,
            "business_id" => $businessId
        ];

        $createdReviewValue =  ReviewValue::create($reviewValue);



        return response($createdReviewValue, 201);
    }
    // ##################################################
    // This method is to filter   Review
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/getreview/{businessId}/{rate}/{start}/{end}",
     *      operationId="filterReview",
     *      tags={"review"},
     *        security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="rate",
     * in="path",
     * description="rate",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="start",
     * in="path",
     * description="from date",
     * required=true,
     * example="2019-06-29"
     * ),
     *  @OA\Parameter(
     * name="end",
     * in="path",
     * description="to date",
     * required=true,
     * example="2026-06-29"
     * ),
     *      summary="This method is to filter   Review",
     *      description="This method is to filter   Review",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function  filterReview($businessId, $rate, $start, $end, Request $request)
    {
        // with
        $reviewValues = ReviewNew::where([
            "business_id" => $businessId,
            "rate" => $rate
        ])
            ->globalFilters()
            ->with("business", "value")
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->get();


        return response($reviewValues, 200);
    }
    // ##################################################
    // This method is to get review by business id
    // ##################################################

    /**
     *
     * @OA\Get(
     *      path="/review-new/getreviewAll/{businessId}",
     *      operationId="getReviewByBusinessId",
     *      tags={"review"},
     *        security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),

     *      summary="This method is to get review by business id",
     *      description="This method is to get review by business id",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function  getReviewByBusinessId($businessId, Request $request)
    {
        // with
        $reviewValue = ReviewNew::with("value")->where([
            "business_id" => $businessId,
        ])
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->get();


        return response($reviewValue, 200);
    }
    // ##################################################
    // This method is to get customer review
    // ##################################################


    /**
     *
     * @OA\Get(
     *      path="/review-new/getcustomerreview/{businessId}/{start}/{end}",
     *      operationId="getCustommerReview",
     *      tags={"review"},
     *        security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),

     *  @OA\Parameter(
     * name="start",
     * in="path",
     * description="from date",
     * required=true,
     * example="2019-06-29"
     * ),
     *  @OA\Parameter(
     * name="end",
     * in="path",
     * description="to date",
     * required=true,
     * example="2026-06-29"
     * ),
     *      summary="This method is to get customer review",
     *      description="This method is to get customer review",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */





    public function  getCustommerReview($businessId, $start, $end, Request $request)
    {
        // with
        $data["reviews"] = ReviewNew::where([
            "business_id" => $businessId,
        ])
            ->globalFilters()
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->get();
        $data["total"]   = $data["reviews"]->count();
        $data["one"]   = 0;
        $data["two"]   = 0;
        $data["three"] = 0;
        $data["four"]  = 0;
        $data["five"]  = 0;
        foreach ($data["reviews"]  as $reviewValue) {
            switch ($reviewValue->rate) {
                case 1:
                    $data["one"] += 1;
                    break;
                case 2:
                    $data["two"] += 1;
                    break;
                case 3:
                    $data["three"] += 1;
                    break;
                case 4:
                    $data["four"] += 1;
                    break;
                case 5:
                    $data["five"] += 1;
                    break;
            }
        }

        return response($data, 200);
    }

    // ##################################################
    // This method is to store review
    // ##################################################

    // ##################################################
    // Authenticated user review
    // ##################################################

    /**
     * @OA\Post(
     *      path="/review-new/{businessId}",
     *      operationId="storeReview",
     *      tags={"review"},
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      security={{"bearerAuth": {}}},
     *      summary="Store review by authenticated user",
     *      description="Store review with optional audio transcription and AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"description","rate","comment","values"},
     *              @OA\Property(property="description", type="string", example="Test"),
     *              @OA\Property(property="rate", type="string", example="2.5"),
     *              @OA\Property(property="comment", type="string", example="Not good"),
     *              @OA\Property(property="is_overall", type="string", example="is_overall"),
     *             @OA\Property(property="staff_id", type="integer", example="1"),
     * 
     *              @OA\Property(
     *                  property="values",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="question_id", type="integer", example=1),
     *                      @OA\Property(property="tag_id", type="integer", example=2),
     *                      @OA\Property(property="star_id", type="integer", example=4)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(response=201, description="Created successfully"),
     *      @OA\Response(response=400, description="Bad Request"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Unprocessable Content")
     * )
     */
    public function storeReview($businessId, Request $request)
    {
        $business = Business::findOrFail($businessId);
        $raw_text = $request->input('comment', '');

        if ($request->hasFile('audio')) {
            $raw_text = $this->transcribeAudio($request->file('audio')->getRealPath());
        }

        // Step 2: AI Moderation Pipeline
        $moderationResults = $this->aiModeration($raw_text);

        // Check if content should be blocked
        if ($moderationResults['should_block']) {
            return response([
                "success" => false,
                "message" => $moderationResults['action_message'],
                "moderation_results" => $moderationResults
            ], 400);
        }

        // Step 3: AI Sentiment Analysis
        $sentimentScore = $this->analyzeSentiment($raw_text);

        // Step 4: AI Topic Extraction
        $topics = $this->extractTopics($raw_text);

        // Step 5: AI Staff Performance Scoring
        $staffSuggestions = [];
        if ($request->staff_id) {
            $staffSuggestions = $this->analyzeStaffPerformance($raw_text, $request->staff_id, $businessId);
        }

        // Step 6: AI Recommendations Engine
        $aiSuggestions = $this->generateRecommendations($raw_text, $topics, $sentimentScore, $businessId);

        $review = ReviewNew::create([
            'survey_id' => $request->survey_id,
            'description' => $request->description,
            'business_id' => $businessId,
            'rate' => $request->rate,
            'user_id' => $request->user()->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            'emotion' => $this->detectEmotion($raw_text), // Keep existing emotion detection
            'key_phrases' => $this->extractKeyPhrases($raw_text),
            'sentiment_score' => $sentimentScore,
            'topics' => $topics,
            'moderation_results' => $moderationResults,
            'ai_suggestions' => $aiSuggestions,
            'staff_suggestions' => $staffSuggestions,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
        ]);

        $this->storeReviewValues($review, $request->values, $business);

        return response([
            "message" => "created successfully",
            "review_id" => $review->id,
            "ai_analysis" => [
                'sentiment_score' => $sentimentScore,
                'topics' => $topics,
                'moderation_action' => $moderationResults['action_taken']
            ]
        ], 201);
    }

    // ##################################################
    // Guest user review
    // ##################################################





    /**
     * @OA\Post(
     *      path="/review-new-guest/{businessId}",
     *      operationId="storeReviewByGuest",
     *      tags={"review"},
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      security={{"bearerAuth": {}}},
     *      summary="Store review by guest user",
     *      description="Store guest review with optional audio transcription and AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"guest_full_name","guest_phone","description","rate","comment","values"},
     *              @OA\Property(property="guest_full_name", type="string", example="Rifat"),
     *              @OA\Property(property="guest_phone", type="string", example="0177"),
     *              @OA\Property(property="description", type="string", example="Test"),
     *              @OA\Property(property="rate", type="string", example="2.5"),
     *              @OA\Property(property="comment", type="string", example="Not good"),
     *              @OA\Property(property="is_overall", type="string", example="is_overall"),
     * @OA\Property(property="latitude", type="number", example="23.8103"),
     *  @OA\Property(property="longitude", type="number", example="90.4125"),
     * @OA\Property(property="staff_id", type="number", example="1"),
     * 
     *              @OA\Property(
     *                  property="values",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="question_id", type="integer", example=1),
     *                      @OA\Property(property="tag_id", type="integer", example=2),
     *                      @OA\Property(property="star_id", type="integer", example=4)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(response=201, description="Created successfully"),
     *      @OA\Response(response=400, description="Bad Request"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Unprocessable Content")
     * )
     */
    // ##################################################
    // Guest user review - Updated with AI Pipeline
    // ##################################################

    public function storeReviewByGuest($businessId, Request $request)
    {
        $business = Business::findOrFail($businessId);
        $ip_address = $request->ip();

        // ✅ Step 1: IP restriction check
        if ($business->enable_ip_check) {
            $existing_review = ReviewNew::where('business_id', $businessId)
                ->where('ip_address', $ip_address)
                ->whereDate('created_at', now()->toDateString())
                ->globalFilters()
                ->orderBy('order_no', 'asc')
                ->first();

            if ($existing_review) {
                return response([
                    "message" => "You have already submitted a review today from this IP."
                ], 400);
            }
        }

        // ✅ Step 2: Location restriction check
        if ($business->enable_location_check) {
            $guest_lat = $request->input('latitude');
            $guest_lon = $request->input('longitude');

            if (!$guest_lat || !$guest_lon) {
                return response(["message" => "Location data required for review."], 400);
            }

            if ($business->latitude && $business->longitude) {
                $distance = $this->getDistanceMeters($guest_lat, $guest_lon, $business->latitude, $business->longitude);
                if ($distance > $business->review_distance_limit) {
                    return response([
                        "message" => "You are too far from the business location to review (limit {$business->review_distance_limit}m)."
                    ], 400);
                }
            }
        }

        $guest = GuestUser::create([
            'full_name' => request()->guest_full_name,
            'phone' => request()->guest_phone,
        ]);

        $raw_text = request()->input('comment', '');

        if (request()->hasFile('audio')) {
            $raw_text = $this->transcribeAudio(request()->file('audio')->getRealPath());
        }

        // AI Pipeline
        $moderationResults = $this->aiModeration($raw_text);

        if ($moderationResults['should_block']) {
            return response([
                "message" => $moderationResults['action_message'],
                "moderation_results" => $moderationResults
            ], 400);
        }

        $sentimentScore = $this->analyzeSentiment($raw_text);
        $topics = $this->extractTopics($raw_text);

        $staffSuggestions = [];
        if ($request->staff_id) {
            $staffSuggestions = $this->analyzeStaffPerformance($raw_text, $request->staff_id, $businessId);
        }

        $aiSuggestions = $this->generateRecommendations($raw_text, $topics, $sentimentScore, $businessId);

        $review = ReviewNew::create([
            'survey_id' => $request->survey_id,
            'description' => request()->description,
            'business_id' => $businessId,
            'rate' => request()->rate,
            'guest_id' => $guest->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            'emotion' => $this->detectEmotion($raw_text),
            'key_phrases' => $this->extractKeyPhrases($raw_text),
            'sentiment_score' => $sentimentScore,
            'topics' => $topics,
            'moderation_results' => $moderationResults,
            'ai_suggestions' => $aiSuggestions,
            'staff_suggestions' => $staffSuggestions,
            "ip_address" => request()->ip(),
            "is_overall" => request()->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
        ]);

        $this->storeReviewValues($review, $request->values, $business);

        return response([
            "message" => "created successfully",
            "review_id" => $review->id,
            "ai_analysis" => [
                'sentiment_score' => $sentimentScore,
                'topics' => $topics,
                'moderation_action' => $moderationResults['action_taken']
            ]
        ], 201);
    }

// ##################################################
// New AI Pipeline Methods
// ##################################################

    /**
     * Step 2: AI Moderation Pipeline
     */
    private function aiModeration($text)
    {
        $abusivePatterns = ['idiot', 'stupid', 'hate', 'terrible', 'awful', 'shit', 'fuck', 'asshole'];
        $hateSpeechIndicators = ['racist', 'sexist', 'discriminat', 'bigot'];
        $spamPatterns = ['http://', 'https://', 'www.', 'buy now', 'click here', 'discount', 'offer'];

        $issues = [];
        $severity = 0;

        // Check for abusive words
        foreach ($abusivePatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $issues[] = 'abusive_language';
                $severity += 1;
            }
        }

        // Check for hate speech
        foreach ($hateSpeechIndicators as $indicator) {
            if (stripos($text, $indicator) !== false) {
                $issues[] = 'hate_speech';
                $severity += 2;
            }
        }

        // Check for spam
        foreach ($spamPatterns as $pattern) {
            if (stripos($text, $pattern) !== false) {
                $issues[] = 'spam_content';
                $severity += 1;
            }
        }

        // Determine action based on severity
        $action = 'allow';
        $shouldBlock = false;
        $actionMessage = 'Content approved';

        if ($severity >= 3) {
            $action = 'block';
            $shouldBlock = true;
            $actionMessage = 'Content blocked due to inappropriate language';
        } elseif ($severity >= 2) {
            $action = 'flag_for_review';
            $actionMessage = 'Content flagged for admin review';
        } elseif ($severity >= 1) {
            $action = 'warn';
            $actionMessage = 'Content contains mild inappropriate language';
        }

        return [
            'issues_found' => $issues,
            'severity_score' => $severity,
            'action_taken' => $action,
            'should_block' => $shouldBlock,
            'action_message' => $actionMessage
        ];
    }

    /**
     * Step 3: AI Sentiment Analysis
     */
    private function analyzeSentiment($text)
    {
        $api_key = env('HF_API_KEY');

        // Using a simple sentiment analysis model
        $ch = curl_init("https://router.huggingface.co/hf-inference/models/cardiffnlp/twitter-roberta-base-sentiment-latest");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);

        // Convert to 0-1 scale
        if (isset($data[0])) {
            $scores = $data[0];
            if (isset($scores['label'])) {
                switch ($scores['label']) {
                    case 'positive':
                        return min(1.0, 0.7 + ($scores['score'] ?? 0) * 0.3);
                    case 'negative':
                        return max(0.0, 0.3 - ($scores['score'] ?? 0) * 0.3);
                    case 'neutral':
                        return 0.5;
                }
            }
        }

        return 0.5; // Default neutral
    }

    /**
     * Step 4: AI Topic Extraction
     */
    private function extractTopics($text)
    {
        $predefinedTopics = [
            'wait time',
            'cleanliness',
            'staff politeness',
            'product quality',
            'price issues',
            'service speed',
            'atmosphere',
            'food quality',
            'customer service',
            'waiting area',
            'billing process'
        ];

        $detectedTopics = [];
        $textLower = strtolower($text);

        foreach ($predefinedTopics as $topic) {
            if (strpos($textLower, strtolower($topic)) !== false) {
                $detectedTopics[] = $topic;
            }
        }

        return $detectedTopics;
    }

    /**
     * Step 5: AI Staff Performance Scoring
     */
    private function analyzeStaffPerformance($text, $staffId, $businessId)
    {
        $performanceIssues = [
            'communication' => ['understand', 'explain', 'listen', 'communication', 'rude', 'polite'],
            'service_speed' => ['slow', 'fast', 'wait', 'quick', 'delay', 'time'],
            'product_knowledge' => ['know', 'information', 'explain', 'helpful', 'knowledge'],
            'attitude' => ['friendly', 'rude', 'polite', 'nice', 'unprofessional']
        ];

        $textLower = strtolower($text);
        $weaknesses = [];

        foreach ($performanceIssues as $skill => $indicators) {
            $negativeCount = 0;

            foreach ($indicators as $indicator) {
                if (strpos($textLower, $indicator) !== false) {
                    $negativeCount++;
                }
            }

            if ($negativeCount > 0) {
                $weaknesses[] = $skill;
            }
        }

        return $this->generateStaffSuggestions($weaknesses);
    }

    private function generateStaffSuggestions($weaknesses)
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

    /**
     * Step 6: AI Recommendations Engine
     */
    private function generateRecommendations($text, $topics, $sentimentScore, $businessId)
    {
        $recommendations = [];

        if (in_array('wait time', $topics) && $sentimentScore < 0.4) {
            $recommendations[] = 'Consider adding additional staff during peak hours to reduce wait times';
        }

        if (in_array('cleanliness', $topics) && $sentimentScore < 0.4) {
            $recommendations[] = 'Implement more frequent cleaning schedules and staff training';
        }

        if (in_array('staff politeness', $topics) && $sentimentScore < 0.4) {
            $recommendations[] = 'Provide additional customer service training to staff';
        }

        if (in_array('price issues', $topics)) {
            $recommendations[] = 'Review pricing strategy and consider competitive analysis';
        }

        return $recommendations;
    }

    // ##################################################
    // Helper to store review values (question/star)
    private function storeReviewValues($review, $values, $business)
    {
        $rate = 0;
        $previousQuestionId = null;

        foreach ($values as $value) {
            if (!$previousQuestionId || $value['question_id'] != $previousQuestionId) {
                $rate += $value['star_id'];
                $previousQuestionId = $value['question_id'];
            }

            $value['review_id'] = $review->id;
            ReviewValueNew::create($value);
        }

        $review->rate = $rate;
        $review->save();


        if ($business) {
            $average_rating = ReviewNew::where('business_id', $business->id)
                ->globalFilters()


                ->avg('rate');

            if ($average_rating >= $business->threshold_rating) {
                $review->status = 'published';
            } else {
                $review->status = 'pending';
            }
            $review->save();
        }
    }
    private function getDistanceMeters($lat1, $lon1, $lat2, $lon2)
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

    // ##################################################
    // AI Helpers
    // ##################################################
    private function transcribeAudio($filePath)
    {
        $api_key = env('HF_API_KEY');
        $audio = file_get_contents($filePath);

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
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['text'] ?? '';
    }

    private function detectEmotion($text)
    {
        $api_key = env('HF_API_KEY');

        $ch = curl_init("https://router.huggingface.co/hf-inference/models/j-hartmann/emotion-english-distilroberta-base");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data[0]['label'] ?? null;
    }

    private function extractKeyPhrases($text)
    {
        $api_key = env('HF_API_KEY');

        $ch = curl_init("https://router.huggingface.co/hf-inference/models/ml6team/keyphrase-extraction-kbir-inspec");
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $api_key",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['inputs' => $text]),
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($result, true);
        return $data['keyphrases'] ?? [];
    }



    /**
     * @OA\Post(
     *      path="/v1.0/reviews/overall/ordering",
     *      operationId="orderOverallReviews",
     *      tags={"review"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Order overall reviews by specific sequence",
     *      description="Update the display order of overall reviews using order numbers",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"reviews"},
     *              @OA\Property(
     *                  property="reviews",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      required={"id", "order_no"},
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="order_no", type="integer", example=1)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Order updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Order updated successfully"),
     *              @OA\Property(property="ok", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function orderOverallReviews(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                // if (!$request->user()->hasPermissionTo('review_update')) {
                //     return response()->json([
                //         "message" => "You can not perform this action"
                //     ], 403);
                // }

                $payload_request = $request->validate([
                    'reviews' => 'required|array',
                    'reviews.*.id' => 'required|integer|exists:review_news,id',
                    'reviews.*.order_no' => 'required|integer|min:0'
                ]);

                foreach ($payload_request['reviews'] as $review) {
                    $item = ReviewNew::find($review['id']);
                    $item->update([
                        'order_no' => $review['order_no']
                    ]);
                }

                return response()->json([
                    'message' => 'Order updated successfully',
                    'ok' => true
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error updating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    // ##################################################
    // This method is to store question
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/review-new/create/questions",
     *      operationId="storeQuestion",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store question",
     *      description="This method is to store question",
     *
     *  @OA\RequestBody(
     *  * description="supported value is of type is 'star','emoji','numbers','heart'",
     *         required=true,
     *         @OA\JsonContent(
     *            required={"question","business_id","is_active"},
     *            @OA\Property(property="question", type="string", format="string",example="How was this?"),
     *  @OA\Property(property="business_id", type="number", format="number",example="1"),
     * *  @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *      *  *  @OA\Property(property="show_in_guest_user", type="boolean", format="boolean",example="1"),
     * *  *  @OA\Property(property="show_in_user", type="boolean", format="boolean",example="1"),
     * *  *  @OA\Property(property="survey_name", type="boolean", format="boolean",example="1"),
     *   * *  *  @OA\Property(property="survey_id", type="boolean", format="boolean",example="1"),
     * * *  @OA\Property(property="type", type="string", format="string",example="star"),
     *
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function storeQuestion(Request $request)
    {

        $question = [
            'question' => $request->question,
            'business_id' => $request->business_id,
            'is_active' => $request->is_active,
            'type' => !empty($request->type) ? $request->type : "star",
            'show_in_guest_user' => $request->show_in_guest_user,
            'show_in_user' => $request->show_in_user,
            'survey_name' => $request->survey_name,
            "is_overall" => $request->is_overall ?? 0,
        ];


        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
            $question["business_id"] = NULL;
        } else {

            $business =    Business::where(["id" => $request->business_id, "OwnerID" => $request->user()->id])->first();

            if (!$business) {
                return response()->json(["message" => "No Business Found"], 400);
            }
            if ($business->enable_question == true) {
                return response()->json(["message" => "question is enabled"], 400);
            }
        }

        $createdQuestion =    Question::create($question);
        $createdQuestion->info = "supported value is of type is 'star','emoji','numbers','heart'";

        if (request()->has('survey_id')) {
            SurveyQuestion::create([
                'survey_id' => request()->survey_id,
                'question_id' => $createdQuestion->id,
            ]);
        }

        return response($createdQuestion, 201);
    }
    // ##################################################
    // This method is to update question
    // ##################################################
    /**
     *
     * @OA\Put(
     *      path="/review-new/update/questions",
     *      operationId="updateQuestion",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update question",
     *      description="This method is to update question",
     *
     *  @OA\RequestBody(
     * description="supported value is of type is 'star','emoji','numbers','heart'",
     *         required=true,
     *         @OA\JsonContent(
     *            required={"question","is_active","id"},
     *  @OA\Property(property="id", type="number", format="number",example="1"),
     *            @OA\Property(property="question", type="string", format="string",example="was it good?"),
     *  *            @OA\Property(property="type", type="string", format="string",example="star"),
     *     *  *  @OA\Property(property="show_in_guest_user", type="boolean", format="boolean",example="1"),
     * *  *  @OA\Property(property="show_in_user", type="boolean", format="boolean",example="1"),
     *  * *  *  @OA\Property(property="survey_name", type="boolean", format="boolean",example="1"),
     * 

     *   @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function updateQuestion(Request $request)
    {
        $question = [
            'type' => $request->type,
            'question' => $request->question,
            'show_in_guest_user' => $request->show_in_guest_user,
            'show_in_user' => $request->show_in_user,
            'survey_name' => $request->survey_name,
            "is_active" => $request->is_active,
        ];
        $checkQuestion =    Question::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(Question::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();
        $updatedQuestion->info = "supported value is of type is 'star','emoji','numbers','heart'";

        return response($updatedQuestion, 200);
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/review-new/set-overall-question",
     *      operationId="setOverallQuestion",
     *      tags={"review.setting.question"},
     *      security={{"bearerAuth": {}}},
     *      summary="Set questions as overall and make all others non-overall",
     *      description="This method marks selected questions as overall and updates all other questions for the same business to non-overall.",
     *
     *  @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          required={"question_ids","business_id"},
     *          @OA\Property(
     *              property="question_ids",
     *              type="array",
     *              @OA\Items(type="integer", example=1),
     *              description="Array of question IDs to set as overall"
     *          ),
     *          @OA\Property(property="business_id", type="integer", example=1)
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Successful operation",
     *      @OA\JsonContent(
     *          @OA\Property(property="success", type="boolean", example=true),
     *          @OA\Property(property="message", type="string", example="Overall questions updated successfully"),
     *          @OA\Property(
     *              property="overall_questions",
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="question", type="string", example="What was your overall experience?"),
     *                  @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star"),
     *                  @OA\Property(property="business_id", type="integer", nullable=true, example=1),
     *                  @OA\Property(property="is_default", type="boolean", example=false),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="sentiment", type="string", enum={"positive","neutral","negative"}, nullable=true, example="positive"),
     *                  @OA\Property(property="is_overall", type="boolean", example=true),
     *                  @OA\Property(property="show_in_guest_user", type="boolean", example=true),
     *                  @OA\Property(property="show_in_user", type="boolean", example=true),
     *                  @OA\Property(property="survey_name", type="string", nullable=true, example="Customer Satisfaction"),
     * 
     * 
     * 
     *              )
     *          )
     *      )
     *  ),
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *      @OA\JsonContent(
     *          @OA\Property(property="message", type="string", example="Validation error")
     *      )
     *  ),
     *  @OA\Response(
     *      response=403,
     *      description="Forbidden",
     *      @OA\JsonContent(
     *          @OA\Property(property="message", type="string", example="Unauthorized")
     *      )
     *  ),
     *  @OA\Response(
     *      response=404,
     *      description="Not Found",
     *      @OA\JsonContent(
     *          @OA\Property(property="message", type="string", example="Business not found")
     *      )
     *  )
     * )
     */
    public function setOverallQuestion(SetOverallQuestionRequest $request): JsonResponse
    {
        // Already validated & passed ValidBusiness + ValidQuestion
        $data        = $request->validated();
        $businessId  = $data['business_id'];
        $questionIds = $data['question_ids'];

        // Perform the update within a transaction
        DB::transaction(function () use ($businessId, $questionIds) {
            // Reset all questions for this business
            Question::where('business_id', $businessId)
                ->update(['is_overall' => false]);

            // Mark selected questions as overall
            Question::where('business_id', $businessId)
                ->whereIn('id', $questionIds)
                ->update(['is_overall' => true]);
        });

        // Fetch updated overall questions
        $overallQuestions = Question::where('business_id', $businessId)
            ->where('is_overall', true)
            ->get();

        // send response
        return response()->json([
            'success'           => true,
            'message'           => 'Overall questions updated successfully.',
            'data' => $overallQuestions,
        ], 200);
    }



    // ##################################################
    // This method is to update question's active state
    // ##################################################

    /**
     *
     * @OA\Put(
     *      path="/review-new/update/active_state/questions",
     *      operationId="updateQuestionActiveState",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update question's active state",
     *      description="This method is to update question's active state",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"is_active","id"},
     *  @OA\Property(property="id", type="number", format="number",example="1"),
     *   @OA\Property(property="is_active", type="boolean", format="boolean",example="1"),
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function updateQuestionActiveState(Request $request)
    {
        $question = [
            "is_active" => $request->is_active,
        ];
        $checkQuestion =    Question::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(Question::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();


        return response($updatedQuestion, 200);
    }

    // ##################################################
    // This method is to get question
    // ##################################################


    /**
     *
     * @OA\Get(
     *      path="/v1.0/review-new/get/questions",
     *      operationId="getQuestion",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get question",
     *      description="This method is to get question",
     *
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getQuestion(Request $request)
    {
        $is_default = false;
        $businessId = !empty($request->business_id) ? $request->business_id : NULL;
        if ($request->user()->hasRole("superadmin")) {

            $is_default = true;
            $businessId = NULL;
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();
            if (!$business && !$request->user()->hasRole("superadmin")) {
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $is_default = true;

            // }
        }


        $query = Question::with(['surveys' => function ($q) {
            $q->select(
                "surveys.id",
                "name",
                "order_no"
            );
        }])->where(["business_id" => $businessId, "is_default" => $is_default])
            ->when($request->boolean("is_user"), function ($q) use ($request) {
                return $q->where("show_in_user", $request->boolean("is_user"));
            })
            ->when($request->boolean("exclude_user"), function ($q) use ($request) {
                return $q->where("show_in_user", false);
            })
            ->when($request->boolean("is_guest_user"), function ($q) use ($request) {
                return $q->where("show_in_guest_user", $request->boolean("is_guest_user"));
            })
            ->when($request->boolean("exclude_guest_user"), function ($q) use ($request) {
                return $q->where("show_in_guest_user", false);
            })
            ->when($request->filled("survey_name"), function ($query) use ($request) {
                $query->whereHas("surveys", function ($q) use ($request) {
                    $q->where("survey_name", $request->input("survey_name"));
                });
            })
            ->when($request->filled("ids"), function ($q) use ($request) {
                $ids = array_filter(array_map('intval', explode(",", $request->query("ids"))));
                return $q->whereIn("id", $ids);
            })
            ->when($request->filled("survey_id"), function ($q) use ($request) {
                return $q->whereHas("surveys", function ($q2) use ($request) {
                    $q2->where("id", $request->input("survey_id"));
                });
            });

        $questions =  $query->get();


        return response([
            "status" => true,
            "message" => "Questions fetched successfully",
            "data" => $questions
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-overall/customer",
     *      operationId="getQuestionAllUnauthorizedOverall",
     *      tags={"review.setting.question"},
     *
     * 
     *      summary="This method is to get all question without pagination",
     *      description="This method is to get all question without pagination",
     *
     * 
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     * 
     * *         @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="is_active",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function  getQuestionAllUnauthorizedOverall(Request $request)
    {
        $is_dafault = false;

        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        // Validate survey_id if provided
        if ($request->filled('survey_id')) {
            $survey = Survey::find($request->survey_id);
            if (!$survey) {
                return response("Survey not found", 404);
            }
        }

        // if ($business->enable_question == true) {
        //     $query =  Question::where(["is_default" => 1]);
        // }
        // else {
        $query =  Question::where(["business_id" => $request->business_id, "is_default" => 0])

            ->when(request()->filled("is_active"), function ($query) {
                $query->where("questions.is_active", request()->input("is_active"));
            })

            ->when(request()->filled("is_overall"), function ($query) {
                $query->when(request()->boolean("is_overall"), function ($query) {
                    $query->where("questions.is_overall", 1);
                }, function ($query) {
                    $query->where("questions.is_overall", 0);
                });
            })
            ->when(request()->filled('survey_id'), function ($query) {
                $query->whereHas('surveys', function ($q) {
                    $q->whereRaw('`surveys`.`id` = ?', [request()->input('survey_id')]);
                });
            });


        // }





        $questions =  $query->get();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            foreach ($question->question_stars as $key2 => $questionStar) {
                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);


                $data[$key1]["stars"][$key2]["tags"] = [];
                foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                    if ($starTag->question_id == $question->id) {

                        array_push($data[$key1]["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                    }
                }
            }
        }
        return response($data, 200);
    }




    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all/customer",
     *      operationId="getQuestionAllUnauthorized",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question without pagination",
     *      description="This method is to get all question without pagination",
     *
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     * *         @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="is_active",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getQuestionAllUnauthorized(Request $request)
    {

        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        // Validate survey_id if provided
        if ($request->filled('survey_id')) {
            $survey = Survey::with('questions')->find($request->survey_id);
            if (!$survey) {
                return response([
                    "status" => false,
                    "message" => 'Survey not found' . $request->survey_id
                ], 404);
            }
        }

        $query = Question::where([
            'business_id'        => $request->business_id,
            // 'is_default'         => 0,
            // 'show_in_guest_user' => $request->boolean('show_in_guest_user', true),
        ])
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('questions.is_active', $request->input('is_active'));
            })
            ->when($request->filled('is_overall'), function ($q) use ($request) {
                $q->when($request->boolean('is_overall'), function ($q) {
                    $q->where('questions.is_overall', 1);
                }, function ($q) {
                    $q->where('questions.is_overall', 0);
                });
            })
            ->when($request->filled('survey_id'), function ($q) use ($request) {
                $surveyId = $request->input('survey_id');
                $q->whereHas('surveys', function ($sub) use ($surveyId) {
                    $sub->where('surveys.id', $surveyId);
                });
            });





        $questions =  $query->get();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            foreach ($question->question_stars as $key2 => $questionStar) {
                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);


                $data[$key1]["stars"][$key2]["tags"] = [];
                foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                    if ($starTag->question_id == $question->id) {

                        array_push($data[$key1]["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                    }
                }
            }
        }
        return response([
            "status" => true,
            "message" => "Questions retrieved successfully",
            "data" => $data
        ], 200);
    }


















    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all",
     *      operationId="getQuestionAll",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question without pagination",
     *      description="This method is to get all question without pagination",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getQuestionAll(Request $request)
    {
        $is_dafault = false;
        if ($request->user()->hasRole("superadmin")) {

            $is_dafault = true;
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();

            if (!$business && !$request->user()->hasRole("superadmin")) {
                return response("No Business Found", 404);
            }
        }

        // Validate survey_id if provided
        if ($request->filled('survey_id')) {
            $survey = Survey::find($request->survey_id);
            if (!$survey) {
                return response("Survey not found", 404);
            }
        }


        $query =  Question::where(["business_id" => $request->business_id, "is_default" => $is_dafault])
            ->where(["show_in_user" => 1])
            ->when(request()->filled("is_overall"), function ($query) {
                $query->when(request()->boolean("is_overall"), function ($query) {
                    $query->where("questions.is_overall", 1);
                }, function ($query) {
                    $query->where("questions.is_overall", 0);
                });
            })
            ->when(request()->filled('survey_name'), function ($query) {
                $query->where('survey_name', request()->input('survey_name'));
            })
            ->when(request()->filled('survey_id'), function ($query) {
                $query->whereHas('surveys', function ($q) {
                    $q->whereRaw('`surveys`.`id` = ?', [request()->input('survey_id')]);
                });
            });



        $questions =  $query->get();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            foreach ($question->question_stars as $key2 => $questionStar) {
                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);


                $data[$key1]["stars"][$key2]["tags"] = [];
                foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                    if ($starTag->question_id == $question->id) {

                        array_push($data[$key1]["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                    }
                }
            }
        }
        return response($data, 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report",
     *      operationId="getQuestionAllReport",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question report",
     *      description="This method is to get all question report",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *    @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *    @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReport(Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        $query =  Question::where(["business_id" => $request->business_id, "is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            foreach ($question->question_stars as $key2 => $questionStar) {


                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where(
                        [
                            "review_news.business_id" => $business->id,
                            "question_id" => $question->id,
                            "star_id" => $questionStar->star->id,
                            "review_news.guest_id" => NULL

                        ]
                    );
                if (!empty($request->start_date) && !empty($request->end_date)) {

                    $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->whereBetween('review_news.created_at', [
                        $request->start_date,
                        $request->end_date
                    ]);
                }
                $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->get()
                    ->count();

                $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];
                $data[$key1]["stars"][$key2]["tag_ratings"] = [];
                if ($starCountTotalTimes > 0) {
                    $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                }


                foreach ($questionStar->star->star_tags as $key3 => $starTag) {


                    if ($starTag->question_id == $question->id) {

                        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.guest_id" => NULL
                                ]
                            );
                        if (!empty($request->start_date) && !empty($request->end_date)) {

                            $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                                $request->start_date,
                                $request->end_date
                            ]);
                        }

                        $starTag->tag->count = $starTag->tag->count->get()->count();
                        if ($starTag->tag->count > 0) {
                            array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                        }


                        $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "star_id" => $questionStar->star->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.guest_id" => NULL
                                ]
                            );
                        if (!empty($request->start_date) && !empty($request->end_date)) {

                            $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                                $request->start_date,
                                $request->end_date
                            ]);
                        }
                        $starTag->tag->total = $starTag->tag->total->get()->count();

                        if ($starTag->tag->total > 0) {
                            unset($starTag->tag->count);
                            array_push($data[$key1]["stars"][$key2]["tag_ratings"], json_decode(json_encode($starTag->tag)));
                        }
                    }
                }
            }


            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
        }





        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {

            $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                    "review_news.guest_id" => NULL
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");
            if (!empty($request->start_date) && !empty($request->end_date)) {

                $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
        }
        if ($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;
        } else {
            $data2["total_rating"] = 0;
        }

        $data2["total_comment"] = ReviewNew::with("user", "guest_user")
            ->globalFilters()
            ->where([
                "business_id" => $business->id,
                "guest_id" => NULL,
            ])
            ->orderBy('order_no', 'asc')
            ->whereNotNull("comment");
        if (!empty($request->start_date) && !empty($request->end_date)) {

            $data2["total_comment"] = $data2["total_comment"]->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $data2["total_comment"] = $data2["total_comment"]->get();

        return response([
            "part1" =>  $data2,
            "part2" =>  $data
        ], 200);
    }
    // ##################################################
    // This method is to get question  by id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions/{id}",
     *      operationId="getQuestionById",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get question by id",
     *      description="This method is to get question by id",
     *
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="question Id",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function   getQuestionById($id, Request $request)
    {
        $questions =    Question::where(["id" => $id])
            ->first();


        if (!$questions) {
            return response([
                "message" => "No question found"
            ], 404);
        }
        $data =  json_decode(json_encode($questions), true);

        foreach ($questions->question_stars as $key2 => $questionStar) {
            $data["stars"][$key2] = json_decode(json_encode($questionStar->star), true);


            $data["stars"][$key2]["tags"] = [];
            foreach ($questionStar->star->star_tags as $key3 => $starTag) {

                if ($starTag->question_id == $questions->id) {

                    array_push($data["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                }
            }
        }
        return response($data, 200);
    }
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions/{id}/{businessId}",
     *      operationId="getQuestionById2",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get question by id",
     *      description="This method is to get question by id",
     *
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="question Id",
     *         required=false,
     *      ),
     *   *         @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="businessId",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function   getQuestionById2($id, $businessId, Request $request)
    {
        $questions =    Question::where(["id" => $id, "business_id" => $businessId])
            ->first();


        if (!$questions) {
            return response([
                "message" => "No question found"
            ], 404);
        }
        $data =  json_decode(json_encode($questions), true);

        foreach ($questions->question_stars as $key2 => $questionStar) {
            $data["stars"][$key2] = json_decode(json_encode($questionStar->star), true);


            $data["stars"][$key2]["tags"] = [];
            foreach ($questionStar->star->star_tags as $key3 => $starTag) {

                if ($starTag->question_id == $questions->id) {

                    array_push($data["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                }
            }
        }
        return response($data, 200);
    }

    // ##################################################
    // This method is to delete question by id
    // ##################################################
    /**
     *
     * @OA\Delete(
     *      path="/review-new/delete/questions/{id}",
     *      operationId="deleteQuestionById",
     *      tags={"review.setting.question"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete question by id",
     *      description="This method is to delete question by id",
     *        @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="question Id",
     *         required=false,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   deleteQuestionById($id, Request $request)
    {
        $questions =    Question::where(["id" => $id])
            ->delete();

        return response(["message" => "ok"], 200);
    }
    // ##################################################
    // This method is to store tag
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/review-new/create/tags",
     *      operationId="storeTag",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store tag",
     *      description="This method is to store tag",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"tag","business_id"},
     *            @OA\Property(property="tag", type="string", format="string",example="How was this?"),
     *  @OA\Property(property="business_id", type="number", format="number",example="1"),

     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function storeTag(Request $request)
    {
        $question = [
            'tag' => $request->tag,
            'business_id' => $request->business_id,
            'is_active' => $request->is_active,
        ];
        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();
            if (!$business) {
                return response()->json(["message" => "No Business Found"]);
            }
            if ($business->enable_question == true) {
                return response()->json(["message" => "question is enabled"]);
            }
        }



        $createdQuestion =    Tag::create($question);


        return response($createdQuestion, 201);




        return response($createdQuestion, 201);
    }
    /**
     *
     * @OA\Post(
     *      path="/v1.0/review-new/create/tags/multiple/{businessId}",
     *      operationId="storeTagMultiple",
     *      tags={"review.setting.tag"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Store multiple tags for a business",
     *      description="Create multiple tags at once for a specific business. Checks for duplicate tags and validates business ownership.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1"
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"tags"},
     *              @OA\Property(
     *                  property="tags",
     *                  type="array",
     *                  @OA\Items(type="string"),
     *                  example={"Excellent Service", "Great Food", "Clean Environment"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Tags created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Tags created successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="tag", type="string", example="Excellent Service"),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(property="is_default", type="boolean", example=false),
     *                      @OA\Property(property="is_active", type="boolean", example=true)
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="You do not own this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=409,
     *          description="Duplicate tags found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Duplicate tags found"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="duplicate_indexes",
     *                      type="array",
     *                      @OA\Items(type="integer"),
     *                      example={0, 2}
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function storeTagMultiple($businessId, StoreTagMultipleRequest $request)
    {
        // VALIDATE REQUEST (business ownership already checked in request)
        $validated = $request->validated();

        // GET UNIQUE TAGS
        $uniqueTags = collect($validated['tags'])->unique()->values()->all();

        // CHECK FOR DUPLICATES
        $duplicateIndexes = [];
        $isSuperAdmin = $request->user()->hasRole("superadmin");

        foreach ($uniqueTags as $index => $tagName) {
            if ($isSuperAdmin) {
                // Check if default tag already exists
                $existingTag = Tag::where([
                    "business_id" => NULL,
                    "tag" => $tagName,
                    "is_default" => 1
                ])->first();

                if ($existingTag) {
                    $duplicateIndexes[] = $index;
                }
            } else {
                // Check if business-specific tag exists
                $existingTag = Tag::where([
                    "business_id" => $businessId,
                    "is_default" => 0,
                    "tag" => $tagName
                ])->first();

                // Also check if default tag exists
                if (!$existingTag) {
                    $existingTag = Tag::where([
                        "business_id" => NULL,
                        "is_default" => 1,
                        "tag" => $tagName
                    ])->first();
                }

                if ($existingTag) {
                    $duplicateIndexes[] = $index;
                }
            }
        }

        // RETURN ERROR IF DUPLICATES FOUND
        if (count($duplicateIndexes) > 0) {
            return response()->json([
                "success" => false,
                "message" => "Duplicate tags found",
                "data" => [
                    "duplicate_indexes" => $duplicateIndexes
                ]
            ], 409);
        }

        // CREATE TAGS
        $createdTags = [];

        foreach ($uniqueTags as $tagName) {
            $tagData = [
                'tag' => $tagName,
                'is_default' => $isSuperAdmin,
                'business_id' => $isSuperAdmin ? NULL : $businessId
            ];

            $createdTag = Tag::create($tagData);
            $createdTags[] = $createdTag;
        }

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Tags created successfully",
            "data" => $createdTags
        ], 201);
    }

    // ##################################################
    // This method is to update tag
    // ##################################################
    /**
     *
     * @OA\Put(
     *      path="/review-new/update/tags",
     *      operationId="updateTag",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update tag",
     *      description="This method is to update tag",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"tag","id"},
     *            @OA\Property(property="tag", type="string", format="string",example="How was this?"),
     *  @OA\Property(property="id", type="number", format="number",example="1"),

     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function updateTag(Request $request)
    {

        $question = [
            'tag' => $request->tag,
            'is_active' => $request->is_active
        ];
        $checkQuestion =    Tag::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(Tag::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();


        return response($updatedQuestion, 200);
    }
    // ##################################################
    // This method is to get tag
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags",
     *      operationId="getTag",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag",
     *      description="This method is to get tag",
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      *         @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="is_active",
     *         required=false,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getTag(Request $request)
    {

        $is_dafault = false;
        $businessId = $request->business_id;

        if ($request->user()->hasRole("superadmin")) {
            $is_dafault = true;
            $businessId = NULL;
            $query =  Tag::where(["business_id" => NULL, "is_default" => true])
                ->when(request()->filled("is_active"), function ($query) {
                    $query->where("tags.is_active", request()->input("is_active"));
                });
        } else {
            $business =    Business::where(["id" => $request->business_id])->first();
            if (!$business && !$request->user()->hasRole("superadmin")) {
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $is_dafault = true;
            // }
            $query =  Tag::where(["business_id" => $businessId, "is_default" => 0])
                ->orWhere(["business_id" => NULL, "is_default" => 1])
                ->when(request()->filled("is_active"), function ($query) {
                    $query->where("tags.is_active", request()->input("is_active"));
                });;
        }



        $questions =  $query->get();


        return response($questions, 200);
    }
    // ##################################################
    // This method is to get tag  by id.
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags/{id}",
     *      operationId="getTagById",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag  by id",
     *      description="This method is to get tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getTagById($id, Request $request)
    {
        $questions =    Tag::where(["id" => $id])
            ->first();
        if (!$questions) {
            return response([
                "message" => "No Tag Found"
            ], 404);
        }
        return response($questions, 200);
    }
    /**
     *
     * @OA\Get(
     *      path="/review-new/get/tags/{id}/{reataurantId}",
     *      operationId="getTagById2",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get tag  by id",
     *      description="This method is to get tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
     *      ),
     * *         @OA\Parameter(
     *         name="reataurantId",
     *         in="path",
     *         description="reataurantId",
     *         required=false,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   getTagById2($id, $reataurantId, Request $request)
    {
        $questions =    Tag::where(["id" => $id, "business_id" => $reataurantId])
            ->first();
        if (!$questions) {
            return response([
                "message" => "No Tag Found"
            ], 404);
        }
        return response($questions, 200);
    }

    // ##################################################
    // This method is to delete tag by id
    // ##################################################

    /**
     *
     * @OA\Delete(
     *      path="/review-new/delete/tags/{id}",
     *      operationId="deleteTagById",
     *      tags={"review.setting.tag"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete tag  by id",
     *      description="This method is to delete tag  by id",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="tag Id",
     *         required=false,
     *      ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function   deleteTagById($id, Request $request)
    {
        $tag =    Tag::where(["id" => $id])
            ->first();
        $tagId = $tag->id;

        if ($request->user()->hasRole("superadmin") &&  $tag->is_default == 1) {
            StarTag::where(["tag_id" => $tagId])->delete();
            $tag->delete();
            ReviewValueNew::where([
                'tag_id' => $tagId
            ])
                ->delete();
        } else  if (!$request->user()->hasRole("superadmin") &&  $tag->is_default == 0) {
            StarTag::where(["tag_id" => $tagId])->delete();
            $tag->delete();
            ReviewValueNew::where([
                'tag_id' => $tagId
            ])
                ->delete();
        }



        return response(["message" => "ok"], 200);
    }
    // ##################################################
    // This method is to store star
    // ##################################################
    public function storeStar(Request $request)
    {
        return "this api is closed by the developer";
        $question = [
            'value' => $request->value,
            // 'business_id' => $request->business_id
        ];
        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
        } else {
            $question["is_default"] = false;
            // $business =    Business::where(["id" => $request->business_id])->first();
            // if(!$business){
            //     return response()->json(["message" => "No Business Found"]);
            // }
            // if ($business->enable_question == true) {
            //     return response()->json(["message" => "question is enabled"]);
            // }
        }



        $createdQuestion =    Star::create($question);


        return response($createdQuestion, 201);
    }
    // ##################################################
    // This method is to update star
    // ##################################################
    public function updateStar(Request $request)
    {
        return "this api is closed by the developer";
        $question = [
            'value' => $request->value
        ];
        $checkQuestion =    Star::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(Star::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();


        return response($updatedQuestion, 200);
    }
    // ##################################################
    // This method is to get star
    // ##################################################
    public function   getStar(Request $request)
    {
        return   Star::where()->paginate(10);
        if ($request->user()->hasRole("superadmin")) {

            $questions =  Star::where(["is_default" => true])->paginate(10);

            return response($questions, 200);
        }
        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }
        // if ($business->enable_question == true) {
        //     $questions =  Star::where(["is_default" => true])->paginate(10);

        // return response($questions, 200);
        // }

        $query =  Star::where(["is_default" => false]);

        $questions =  $query->paginate(10);

        return response($questions, 200);
    }
    // ##################################################
    // This method is to get star by id
    // ##################################################
    public function   getStarById($id, Request $request)
    {
        $questions =    Star::where(["id" => $id])
            ->first();
        return response($questions, 200);
    }
    public function   deleteStarById($id, Request $request)
    {
        return "this api is closed by the developer";
        $questions =    Star::where(["id" => $id])
            ->delete();
        return response(["message" => "ok"], 200);
    }
    // ##################################################
    // This method is to store star tag
    // ##################################################
    public function storeStarTag(Request $request)
    {

        $question = [
            'question_id' => $request->question_id,
            'tag_id' => $request->tag_id,
            'star_id' => $request->star_id,
        ];
        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
        }
        $createdQuestion =    StarTagQuestion::create($question);


        return response($createdQuestion, 201);
    }
    // ##################################################
    // This method is to update star tag
    // ##################################################
    public function updateStarTag(Request $request)
    {
        $question = [
            'question_id' => $request->question_id,
            'tag_id' => $request->tag_id,
            'star_id' => $request->star_id,
        ];
        $checkQuestion =    StarTagQuestion::where(["id" => $request->id])->first();
        if ($checkQuestion->is_default == true && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "you can not update the question. you are not a super admin"]);
        }
        $updatedQuestion =    tap(StarTagQuestion::where(["id" => $request->id]))->update(
            $question
        )
            // ->with("somthing")

            ->first();


        return response($updatedQuestion, 200);
    }
    // ##################################################
    // This method is to get star tag
    // ##################################################
    public function   getStarTag(Request $request)
    {
        $query =  StarTagQuestion::where(["question_id" => $request->question_id])
            ->with("question", "star", "tag");
        if ($request->user()->hasRole("superadmin")) {
            $query->where(["is_default" => true]);
        }
        $business =    Business::where(["id" => $request->business_id])->first();
        $query->where(["is_default" => false]);
        // if ($business->enable_question == true) {
        //     $query->where(["is_default" => true]);
        // }
        $questions =  $query->get();


        return response($questions, 200);
    }
    // ##################################################
    // This method is to get star tag by id
    // ##################################################
    public function   getStarTagById($id, Request $request)
    {

        $questions =    StarTagQuestion::where(["id" => $id])
            ->with("question", "star", "tag")
            ->first();
        return response($questions, 200);
    }
    // ##################################################
    // This method is to get report
    // ##################################################
    public function   getSelectedTagCount($businessId, Request $request)
    {

        $questions =    Question::where(["business_id" => $businessId])
            ->get();
        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            foreach ($question->question_stars as $key2 => $questionStar) {
                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);
                $data[$key1]["stars"][$key2]["star_count"]  =  ReviewValueNew::where([
                    "question_id" => $question->id,
                    "star_id" => $questionStar->star->id,

                ])->count();


                foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                    if ($starTag->question_id == $question->id) {
                        $data[$key1]["stars"][$key2]["tags"][$key3] = json_decode(json_encode($starTag->tag), true);
                        // $data[$key1]["stars"][$key2]["tags"][$key3]["search"] = [
                        //     "question_id"=>$question->id,
                        //     "star_id"=> $questionStar->star->id,
                        //     "tag_id"=> $starTag->tag->id

                        // ];

                        $data[$key1]["stars"][$key2]["tags"][$key3]["tag_count"]  =  ReviewValueNew::where([
                            "question_id" => $question->id,
                            "star_id" => $questionStar->star->id,
                            "tag_id" => $starTag->tag->id

                        ])->count();
                    }
                }
            }
        }
        return response($data, 200);
    }
    // ##################################################
    // This method is to get report
    // ##################################################
    public function   getSelectedTagCountByQuestion($questionId, Request $request)
    {

        $question =    Question::where(["id" => $questionId])
            ->first();

        $data =  json_decode(json_encode($question), true);


        foreach ($question->question_stars as $key2 => $questionStar) {
            $data["stars"][$key2] = json_decode(json_encode($questionStar->star), true);
            $data["stars"][$key2]["star_count"]  =  ReviewValueNew::where([
                "question_id" => $question->id,
                "star_id" => $questionStar->star->id,

            ])->count();

            foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                if ($starTag->question_id == $question->id) {
                    $data["stars"][$key2]["tags"][$key3] = json_decode(json_encode($starTag->tag), true);
                    // $data["stars"][$key2]["tags"][$key3]["search"] = [
                    //     "question_id"=>$question->id,
                    //     "star_id"=> $questionStar->star->id,
                    //     "tag_id"=> $starTag->tag->id

                    // ];

                    $data["stars"][$key2]["tags"][$key3]["tag_count"]  =  ReviewValueNew::where([
                        "question_id" => $question->id,
                        "star_id" => $questionStar->star->id,
                        "tag_id" => $starTag->tag->id

                    ])->count();
                }
            }
        }


        return response($data, 200);
    }


    // ##################################################
    // This method is to delete star tag by id
    // ##################################################
    public function   deleteStarTagById($id, Request $request)
    {
        $questions =    StarTagQuestion::where(["id" => $id])
            ->delete();
        return response(["message" => "ok"], 200);
    }
    // ##################################################
    // This method is to create question and all other thing
    // ##################################################


    // public function storeOwnerQuestion(Request $request)
    // {
    //     $question = [
    //         'question' => $request->question,
    //         'business_id' => $request->business_id,
    //         'is_active'=>$request->is_active,
    //     ];
    //     if ($request->user()->hasRole("superadmin")) {
    //         $question["is_default"] = true;
    //     }else {
    //         $business =    Business::where(["id" => $request->business_id])->first();
    //         if ($business->enable_question == true) {
    //             return response()->json(["message" => "question is enabled"]);
    //         }
    //     }

    //     $createdQuestion =    Question::create($question);

    //     foreach($request->stars as $requestStar){
    //         $star = [
    //             'value' => $requestStar->value,
    //             'question_id' => $createdQuestion->id,
    //             'business_id' => $request->business_id
    //         ];
    //         if ($request->user()->hasRole("superadmin")) {
    //             $star["is_default"] = true;
    //         }
    //         $createdStar =    Star::create($star);
    //     }


    // }




    /**
     *
     * @OA\Post(
     *      path="/review-new/owner/create/questions",
     *      operationId="storeOwnerQuestion",
     *      tags={"review.setting.link"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store question",
     *      description="This method is to store question.",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"question_id","stars"},

     *  @OA\Property(property="question_id", type="number", format="number",example="1"),
     *  @OA\Property(property="stars", type="string", format="array",example={
     *
     * { "star_id":"2",
     *
     * "tags":{
     * {"tag_id":"2"},
     * {"tag_id":"2"}
     * }
     *
     * }
     *
     *
     * }
     *
     * ),
     *
     *
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function storeOwnerQuestion(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $question_id = $request->question_id;
            foreach ($request->stars as $requestStar) {


                QusetionStar::create([
                    "question_id" => $question_id,
                    "star_id" => $requestStar["star_id"]
                ]);


                foreach ($requestStar["tags"] as $tag) {


                    StarTag::create([
                        "question_id" => $question_id,
                        "tag_id" => $tag["tag_id"],
                        "star_id" => $requestStar["star_id"]
                    ]);
                }
            }

            return response(["message" => "ok"], 201);
        });
    }


    /**
     *
     * @OA\Post(
     *      path="/review-new/owner/update/questions",
     *      operationId="updateOwnerQuestion",
     *      tags={"review.setting.link"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update question",
     *      description="This method is to update question",
     *             @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="method",
     *         required=false,
     * example="PATCH"
     *      ),
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"question_id","stars"},

     *  @OA\Property(property="question_id", type="number", format="number",example="1"),
     *  @OA\Property(property="stars", type="string", format="array",example={
     *  {* "star_id":"2",
     * "tags":{
     * {"tag_id":"2"},
     * {"tag_id":"2"}
     *
     * }
     *
     * }
     * }
     *
     * ),
     *
     *
     *
     *
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function updateOwnerQuestion(Request $request)
    {

        return DB::transaction(function () use ($request) {
            $question_id = $request->question_id;

            $starIds = collect($request->stars)->pluck('star_id')->toArray();


            QusetionStar::where([
                'question_id' => $question_id,
            ])
                ->whereNotIn('star_id', $starIds)
                ->delete();



            foreach ($request->stars as $requestStar) {

                if (!(QusetionStar::where([
                    "question_id" => $question_id,
                    "star_id" => $requestStar["star_id"]
                ])->exists())) {
                    QusetionStar::create([
                        "question_id" => $question_id,
                        "star_id" => $requestStar["star_id"]
                    ]);
                }

                $starTagIds = collect($requestStar["tags"])->pluck('tag_id')->toArray();

                StarTag::where([
                    "question_id"  => $question_id,
                    "star_id" => $requestStar["star_id"]
                ])
                    ->whereNotIn('tag_id', $starTagIds)
                    ->delete();

                foreach ($requestStar["tags"] as $tag) {

                    if (!(StarTag::where([
                        "question_id" => $question_id,
                        "tag_id" => $tag["tag_id"],
                        "star_id" => $requestStar["star_id"]
                    ])->exists())) {
                        StarTag::create([
                            "question_id" => $question_id,
                            "tag_id" => $tag["tag_id"],
                            "star_id" => $requestStar["star_id"]
                        ]);
                    }
                }
            }

            return response(["message" => "ok"], 201);
        });
    }






    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report/guest",
     *      operationId="getQuestionAllReportGuest",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question report guest",
     *      description="This method is to get all question report guest",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *    @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *    @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportGuest(Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        $query =  Question::where(["business_id" => $request->business_id, "is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            foreach ($question->question_stars as $key2 => $questionStar) {


                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where(
                        [
                            "review_news.business_id" => $business->id,
                            "question_id" => $question->id,
                            "star_id" => $questionStar->star->id,
                            "review_news.user_id" => NULL,

                        ]
                    );
                if (!empty($request->start_date) && !empty($request->end_date)) {

                    $data[$key1]["stars"][$key2]["stars_count"]  = $data[$key1]["stars"][$key2]["stars_count"]->whereBetween('review_news.created_at', [
                        $request->start_date,
                        $request->end_date
                    ]);
                }
                $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]
                    ->get()
                    ->count();

                $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];
                $data[$key1]["stars"][$key2]["tag_ratings"] = [];
                if ($starCountTotalTimes > 0) {
                    $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                }



                foreach ($questionStar->star->star_tags as $key3 => $starTag) {





                    if ($starTag->question_id == $question->id) {




                        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.user_id" => NULL
                                ]
                            );
                        if (!empty($request->start_date) && !empty($request->end_date)) {

                            $starTag->tag->count  = $starTag->tag->count->whereBetween('review_news.created_at', [
                                $request->start_date,
                                $request->end_date
                            ]);
                        }
                        $starTag->tag->count = $starTag->tag->count->get()->count();

                        if ($starTag->tag->count > 0) {
                            array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                        }

                        $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "star_id" => $questionStar->star->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.user_id" => NULL
                                ]
                            );
                        if (!empty($request->start_date) && !empty($request->end_date)) {

                            $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                                $request->start_date,
                                $request->end_date
                            ]);
                        }
                        $starTag->tag->total = $starTag->tag->total->get()->count();
                        if ($starTag->tag->total > 0) {
                            unset($starTag->tag->count);
                            array_push($data[$key1]["stars"][$key2]["tag_ratings"], json_decode(json_encode($starTag->tag)));
                        }
                    }
                }
            }


            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
        }





        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {

            $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                    "review_news.user_id" => NULL
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");
            if (!empty($request->start_date) && !empty($request->end_date)) {

                $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
        }
        if ($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;
        } else {
            $data2["total_rating"] = 0;
        }







        $data2["total_comment"] = ReviewNew::with("user", "guest_user")->where([
            "business_id" => $business->id,
            "user_id" => NULL,
        ])
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->whereNotNull("comment");
        if (!empty($request->start_date) && !empty($request->end_date)) {

            $data2["total_comment"] = $data2["total_comment"]->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $data2["total_comment"] = $data2["total_comment"]->get();






        return response([
            "part1" =>  $data2,
            "part2" =>  $data
        ], 200);
    }


































































    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report/unauthorized",
     *      operationId="getQuestionAllReportUnauthorized",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question report unauthorized",
     *      description="This method is to get all question report unauthorized",
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportUnauthorized(Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        $query =  Question::where(["business_id" => $request->business_id, "is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            foreach ($question->question_stars as $key2 => $questionStar) {


                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where(
                        [
                            "review_news.business_id" => $business->id,
                            "question_id" => $question->id,
                            "star_id" => $questionStar->star->id,
                            "review_news.guest_id" => NULL

                        ]
                    )
                    ->get()
                    ->count();

                $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];

                if ($starCountTotalTimes > 0) {
                    $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                }




                foreach ($questionStar->star->star_tags as $key3 => $starTag) {





                    if ($starTag->question_id == $question->id) {



                        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.guest_id" => NULL
                                ]
                            )->get()->count();





                        if ($starTag->tag->count > 0) {
                            array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                        }
                    }
                }
            }


            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
        }





        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {

            $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                    "review_news.guest_id" => NULL
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id")
                ->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
        }
        if ($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;
        } else {
            $data2["total_rating"] = 0;
        }

        $data2["total_comment"] = ReviewNew::with("user", "guest_user")->where([
            "business_id" => $business->id,
            "guest_id" => NULL,
        ])
            ->globalFilters()
            ->whereNotNull("comment")
            ->orderBy('order_no', 'asc')
            ->get();

        return response([
            "part1" =>  $data2,
            // "part2" =>  $data
        ], 200);
    }




























































































    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report/guest/unauthorized",
     *      operationId="getQuestionAllReportGuestUnauthorized",
     *      tags={"review.setting.question"},

     *      summary="This method is to get all question report guest unauthorized",
     *      description="This method is to get all question report guest unauthorized",

     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportGuestUnauthorized(Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        $query =  Question::where(["business_id" => $request->business_id, "is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

        $data =  json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {

            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            foreach ($question->question_stars as $key2 => $questionStar) {


                $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where(
                        [
                            "review_news.business_id" => $business->id,
                            "question_id" => $question->id,
                            "star_id" => $questionStar->star->id,
                            "review_news.user_id" => NULL

                        ]
                    )
                    ->get()
                    ->count();

                $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];

                if ($starCountTotalTimes > 0) {
                    $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                }




                foreach ($questionStar->star->star_tags as $key3 => $starTag) {





                    if ($starTag->question_id == $question->id) {




                        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where(
                                [
                                    "review_news.business_id" => $business->id,
                                    "question_id" => $question->id,
                                    "tag_id" => $starTag->tag->id,
                                    "review_news.user_id" => NULL
                                ]
                            )->get()->count();


                        if ($starTag->tag->count > 0) {
                            array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                        }
                    }
                }
            }


            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
        }





        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {

            $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $business->id,
                    "star_id" => $star->id,
                    "review_news.user_id" => NULL
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id")
                ->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
        }
        if ($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;
        } else {
            $data2["total_rating"] = 0;
        }







        $data2["total_comment"] = ReviewNew::with("user", "guest_user")->where([
            "business_id" => $business->id,
            "user_id" => NULL,
        ])
            ->globalFilters()
            ->whereNotNull("comment")
            ->orderBy('order_no', 'asc')
            ->get();






        return response([
            "part1" =>  $data2,
            "part2" =>  $data
        ], 200);
    }






    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report/guest/quantum",
     *      operationId="getQuestionAllReportGuestQuantum",
     *      tags={"review.setting.question"},
     *   *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get all question report guest and only business owner of the business can view this ",
     *      description="This method is to get all question report guest and only business owner of the business can view this ",

     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *     @OA\Parameter(
     *         name="quantum",
     *         in="query",
     *         description="quantum",
     *         required=false,
     *      ),
     *      @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="period",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportGuestQuantum(Request $request)
    {


        $business =    Business::where([
            "id" => $request->business_id,
            "OwnerID" => $request->user()->id
        ])->first();
        if (!$business) {
            return response("No Business Found or you are not the owner of the business", 404);
        }
        $data = [];

        $period = 0;
        for ($i = 0; $i < $request->quantum; $i++) {
            $totalCount = 0;
            $ttotalRating = 0;

            foreach (Star::get() as $star) {

                $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $business->id,
                        "star_id" => $star->id,
                        "review_news.user_id" => NULL
                    ])
                    ->whereBetween(
                        'review_news.created_at',
                        [now()->subDays(($request->period + $period))->startOfDay(), now()->subDays($period)->endOfDay()]
                    )
                    ->distinct("review_value_news.review_id", "review_value_news.question_id")
                    ->count();

                $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

                $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
            }
            if ($totalCount > 0) {
                $data2["total_rating"] = $totalCount / $ttotalRating;
            } else {
                $data2["total_rating"] = 0;
            }

            // $data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
            //     "business_id" => $business->id,
            //     "user_id" => NULL,
            // ])
            // ->whereNotNull("comment")
            // ->count();
            array_push($data, $data2);
            $period +=  $request->period + $period;
        }







        return response([
            "data" =>  $data,

        ], 200);
    }




    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report/quantum",
     *      operationId="getQuestionAllReportQuantum",
     *      tags={"review.setting.question"},
     *   *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get all question report.  and only business owner of the business can view this ",
     *      description="This method is to get all question report. and only business owner of the business can view this ",

     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *  *         @OA\Parameter(
     *         name="quantum",
     *         in="query",
     *         description="quantum",
     *         required=false,
     *      ),
     *  *         @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="period",
     *         required=false,
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportQuantum(Request $request)
    {


        $business =    Business::where([
            "id" => $request->business_id,
            "OwnerID" => $request->user()->id
        ])->first();
        if (!$business) {
            return response("No Business Found or you are not the owner of the business", 404);
        }
        $data = [];

        $period = 0;
        for ($i = 0; $i < $request->quantum; $i++) {
            $totalCount = 0;
            $ttotalRating = 0;

            foreach (Star::get() as $star) {

                $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $business->id,
                        "star_id" => $star->id,
                        "review_news.guest_id" => NULL
                    ])
                    ->whereBetween(
                        'review_news.created_at',
                        [now()->subDays(($request->period + $period))->startOfDay(), now()->subDays($period)->endOfDay()]
                    )
                    ->distinct("review_value_news.review_id", "review_value_news.question_id")
                    ->count();

                $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

                $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
            }
            if ($totalCount > 0) {
                $data2["total_rating"] = $totalCount / $ttotalRating;
            } else {
                $data2["total_rating"] = 0;
            }

            // $data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
            //     "business_id" => $business->id,
            //     "user_id" => NULL,
            // ])
            // ->whereNotNull("comment")
            // ->count();
            array_push($data, $data2);
            $period +=  $request->period + $period;
        }







        return response([
            "data" =>  $data,

        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report-by-user/{perPage}",
     *      operationId="getQuestionAllReportByUser",
     *      tags={"review"},

     *      summary="This method is to get all question report by user",
     *      description="This method is to get all question report by user",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  *         @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage Id",
     *         required=true,
     *      ),
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *    @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *    @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportByUser($perPage, Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }
        $usersQuery = User::leftjoin('review_news', 'users.id', '=', 'review_news.user_id')
            ->leftjoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
            ->leftjoin('questions', 'review_value_news.question_id', '=', 'questions.id')

            ->where([
                "review_news.business_id" => $business->id
            ])
            ->havingRaw('COUNT(review_news.id) > 0')
            ->havingRaw('COUNT(review_value_news.question_id) > 0')
            ->havingRaw('COUNT(questions.id) > 0')
            ->groupBy("users.id")
            ->select("users.*", "review_news.created_at as review_created_at");
        if (!empty($request->start_date) && !empty($request->end_date)) {

            $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $users = $usersQuery->paginate($perPage);

        for ($i = 0; $i < count($users->items()); $i++) {
            $query =  Question::leftjoin('review_value_news', 'questions.id', '=', 'review_value_news.question_id')
                ->leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "questions.business_id" => $request->business_id,
                    "questions.is_default" => false,
                    "review_news.user_id" => $users->items()[$i]->id
                ])
                ->groupBy("questions.id")
                ->select("questions.*");

            $questions =  $query->get();



            $data =  json_decode(json_encode($questions), true);
            foreach ($questions as $key1 => $question) {

                $tags_rating = [];
                $starCountTotal = 0;
                $starCountTotalTimes = 0;
                foreach ($question->question_stars as $key2 => $questionStar) {


                    $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                    $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                        ->where(
                            [
                                "review_news.business_id" => $business->id,
                                "question_id" => $question->id,
                                "star_id" => $questionStar->star->id,
                                "review_news.guest_id" => NULL,
                                "review_news.user_id" => $users->items()[$i]->id
                            ]
                        );
                    if (!empty($request->start_date) && !empty($request->end_date)) {

                        $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->whereBetween('review_news.created_at', [
                            $request->start_date,
                            $request->end_date
                        ]);
                    }
                    $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->get()
                        ->count();

                    $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                    $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];
                    $data[$key1]["stars"][$key2]["tag_ratings"] = [];
                    if ($starCountTotalTimes > 0) {
                        $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                    }


                    foreach ($questionStar->star->star_tags as $key3 => $starTag) {


                        if ($starTag->question_id == $question->id) {

                            $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                                ->where(
                                    [
                                        "review_news.business_id" => $business->id,
                                        "question_id" => $question->id,
                                        "tag_id" => $starTag->tag->id,
                                        "review_news.guest_id" => NULL,
                                        "review_news.user_id" => $users->items()[$i]->id
                                    ]
                                );
                            if (!empty($request->start_date) && !empty($request->end_date)) {

                                $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                                    $request->start_date,
                                    $request->end_date
                                ]);
                            }

                            $starTag->tag->count = $starTag->tag->count->get()->count();
                            if ($starTag->tag->count > 0) {
                                array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                            }


                            $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                                ->where(
                                    [
                                        "review_news.business_id" => $business->id,
                                        "question_id" => $question->id,
                                        "star_id" => $questionStar->star->id,
                                        "tag_id" => $starTag->tag->id,
                                        "review_news.guest_id" => NULL,
                                        "review_news.user_id" => $users->items()[$i]->id
                                    ]
                                );
                            if (!empty($request->start_date) && !empty($request->end_date)) {

                                $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                                    $request->start_date,
                                    $request->end_date
                                ]);
                            }
                            $starTag->tag->total = $starTag->tag->total->get()->count();

                            if ($starTag->tag->total > 0) {
                                unset($starTag->tag->count);
                                array_push($data[$key1]["stars"][$key2]["tag_ratings"], json_decode(json_encode($starTag->tag)));
                            }
                        }
                    }
                }


                $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
            }





            $totalCount = 0;
            $ttotalRating = 0;

            foreach (Star::get() as $star) {

                $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $business->id,
                        "star_id" => $star->id,
                        "review_news.guest_id" => NULL,
                        "review_news.user_id" => $users->items()[$i]->id
                    ])
                    ->distinct("review_value_news.review_id", "review_value_news.question_id");
                if (!empty($request->start_date) && !empty($request->end_date)) {

                    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
                        $request->start_date,
                        $request->end_date
                    ]);
                }
                $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

                $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

                $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
            }
            if ($totalCount > 0) {
                $data2["total_rating"] = $totalCount / $ttotalRating;
            } else {
                $data2["total_rating"] = 0;
            }

            $data2["total_comment"] = ReviewNew::with("user", "guest_user")->where([
                "business_id" => $business->id,
                "guest_id" => NULL,
                "review_news.user_id" => $users->items()[$i]->id
            ])
                ->globalFilters()
                ->orderBy('order_no', 'asc')
                ->whereNotNull("comment");
            if (!empty($request->start_date) && !empty($request->end_date)) {

                $data2["total_comment"] = $data2["total_comment"]->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            $data2["total_comment"] = $data2["total_comment"]->get();

            $users->items()[$i]["review_info"] = [
                "part1" =>  $data2,
                "part2" =>  $data
            ];
        }
        return response()->json($users, 200);
    }



    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-report-by-user-guest/{perPage}",
     *      operationId="getQuestionAllReportByUserGuest",
     *      tags={"review"},

     *      summary="This method is to get all question report by user guest",
     *      description="This method is to get all question report by user guest",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  *         @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage Id",
     *         required=false,
     *      ),
     *         @OA\Parameter(
     *         name="business_id",
     *         in="query",
     *         description="business Id",
     *         required=false,
     *      ),
     *    @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="start_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *    @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="end_date",
     *         required=false,
     * * example="2023-06-29"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *           @OA\Response(
     *          response=201,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getQuestionAllReportByUserGuest($perPage, Request $request)
    {


        $business =    Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }
        $usersQuery = GuestUser::leftjoin('review_news', 'guest_users.id', '=', 'review_news.guest_id')
            ->leftjoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
            ->leftjoin('questions', 'review_value_news.question_id', '=', 'questions.id')

            ->where([
                "review_news.business_id" => $business->id
            ])
            ->havingRaw('COUNT(review_news.id) > 0')
            ->havingRaw('COUNT(review_value_news.question_id) > 0')
            ->havingRaw('COUNT(questions.id) > 0')
            ->groupBy("guest_users.id",)
            ->select(
                "guest_users.*",

                "review_news.created_at as review_created_at"
            );

        if (!empty($request->start_date) && !empty($request->end_date)) {

            $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $users = $usersQuery->paginate($perPage);


        for ($i = 0; $i < count($users->items()); $i++) {
            $query =  Question::leftjoin('review_value_news', 'questions.id', '=', 'review_value_news.question_id')
                ->leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "questions.business_id" => $request->business_id,
                    "is_default" => false,
                    "review_news.guest_id" => $users->items()[$i]->id
                ])
                ->groupBy("questions.id")
                ->select("questions.*");

            $questions =  $query->get();



            $data =  json_decode(json_encode($questions), true);
            foreach ($questions as $key1 => $question) {

                $tags_rating = [];
                $starCountTotal = 0;
                $starCountTotalTimes = 0;
                foreach ($question->question_stars as $key2 => $questionStar) {


                    $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);

                    $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                        ->where(
                            [
                                "review_news.business_id" => $business->id,
                                "question_id" => $question->id,
                                "star_id" => $questionStar->star->id,
                                "review_news.guest_id" => $users->items()[$i]->id,
                                "review_news.user_id" => NULL
                            ]
                        );
                    if (!empty($request->start_date) && !empty($request->end_date)) {

                        $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->whereBetween('review_news.created_at', [
                            $request->start_date,
                            $request->end_date
                        ]);
                    }
                    $data[$key1]["stars"][$key2]["stars_count"] = $data[$key1]["stars"][$key2]["stars_count"]->get()
                        ->count();

                    $starCountTotal += $data[$key1]["stars"][$key2]["stars_count"] * $questionStar->star->value;

                    $starCountTotalTimes += $data[$key1]["stars"][$key2]["stars_count"];
                    $data[$key1]["stars"][$key2]["tag_ratings"] = [];
                    if ($starCountTotalTimes > 0) {
                        $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
                    }


                    foreach ($questionStar->star->star_tags as $key3 => $starTag) {


                        if ($starTag->question_id == $question->id) {

                            $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                                ->where(
                                    [
                                        "review_news.business_id" => $business->id,
                                        "question_id" => $question->id,
                                        "tag_id" => $starTag->tag->id,
                                        "review_news.guest_id" => $users->items()[$i]->id,
                                        "review_news.user_id" => NULL
                                    ]
                                );
                            if (!empty($request->start_date) && !empty($request->end_date)) {

                                $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                                    $request->start_date,
                                    $request->end_date
                                ]);
                            }

                            $starTag->tag->count = $starTag->tag->count->get()->count();
                            if ($starTag->tag->count > 0) {
                                array_push($tags_rating, json_decode(json_encode($starTag->tag)));
                            }


                            $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                                ->where(
                                    [
                                        "review_news.business_id" => $business->id,
                                        "question_id" => $question->id,
                                        "star_id" => $questionStar->star->id,
                                        "tag_id" => $starTag->tag->id,
                                        "review_news.guest_id" => $users->items()[$i]->id,
                                        "review_news.user_id" => NULL
                                    ]
                                );
                            if (!empty($request->start_date) && !empty($request->end_date)) {

                                $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                                    $request->start_date,
                                    $request->end_date
                                ]);
                            }
                            $starTag->tag->total = $starTag->tag->total->get()->count();

                            if ($starTag->tag->total > 0) {
                                unset($starTag->tag->count);
                                array_push($data[$key1]["stars"][$key2]["tag_ratings"], json_decode(json_encode($starTag->tag)));
                            }
                        }
                    }
                }


                $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
            }





            $totalCount = 0;
            $ttotalRating = 0;

            foreach (Star::get() as $star) {

                $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $business->id,
                        "star_id" => $star->id,
                        "review_news.guest_id" => $users->items()[$i]->id,
                        "review_news.user_id" => NULL
                    ])
                    ->distinct("review_value_news.review_id", "review_value_news.question_id");
                if (!empty($request->start_date) && !empty($request->end_date)) {

                    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
                        $request->start_date,
                        $request->end_date
                    ]);
                }
                $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

                $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

                $ttotalRating += $data2["star_" . $star->value . "_selected_count"];
            }
            if ($totalCount > 0) {
                $data2["total_rating"] = $totalCount / $ttotalRating;
            } else {
                $data2["total_rating"] = 0;
            }

            $data2["total_comment"] = ReviewNew::with("user", "guest_user")->where([
                "business_id" => $business->id,
                "guest_id" => $users->items()[$i]->id,
                "review_news.user_id" => NULL
            ])
                ->globalFilters()
                ->orderBy('order_no', 'asc')
                ->whereNotNull("comment");
            if (!empty($request->start_date) && !empty($request->end_date)) {

                $data2["total_comment"] = $data2["total_comment"]->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            $data2["total_comment"] = $data2["total_comment"]->get();

            $users->items()[$i]["review_info"] = [
                "part1" =>  $data2,
                "part2" =>  $data
            ];
        }

        return response()->json($users, 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/review-new/rating-analysis/{businessId}",
     *      operationId="getAverageRatingClient",
     *   tags={"review_management.client"},
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="from date",
     * required=false,
     * example="2019-06-29"
     * ),
     *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="to date",
     * required=false,
     * example="2026-06-29"
     * ),
     *      summary="This method is to get average",
     *      description="This method is to get average",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */


    public function  getAverageRatingClient($businessId, Request $request)
    {
        // with
        $reviews = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->get();

        $data["total_reviews"]   = $reviews->count();
        $data['rating']["one"]   = 0;
        $data['rating']["two"]   = 0;
        $data['rating']["three"] = 0;
        $data['rating']["four"]  = 0;
        $data['rating']["five"]  = 0;
        foreach ($reviews as $review) {
            switch ($review->rate) {
                case 1:
                    $data['rating']["one"] += 1;
                    break;
                case 2:
                    $data['rating']["two"] += 1;
                    break;
                case 3:
                    $data['rating']["three"] += 1;
                    break;
                case 4:
                    $data['rating']["four"] += 1;
                    break;
                case 5:
                    $data['rating']["five"] += 1;
                    break;
            }
        }


        return response([
            "success" => true,
            "message" => "Average rating retrieved successfully",
            "data" => $data
        ], 200);
    }



    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/review-new/{businessId}",
     *      operationId="getReviewByBusinessIdClient",
     *      tags={"review_management.client"},
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),

     *      summary="This method is to get review by business id",
     *      description="This method is to get review by business id",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function  getReviewByBusinessIdClient($businessId, Request $request)
    {
        // with
        $reviewValue = ReviewNew::with([
            "value",
            "user",
            "guest_user",
            "survey"
        ])->where([
            "business_id" => $businessId,
        ])
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->get();


        return response([
            "success" => true,
            "message" => "Reviews retrieved successfully",
            "data" => $reviewValue
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/review-new/{reviewId}",
     *      operationId="getReviewById",
     *      tags={"review_management"},
     *      security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="reviewId",
     * in="path",
     * description="reviewId",
     * required=true,
     * example="1"
     * ),

     *      summary="This method is to get review by review id",
     *      description="This method is to get review by review id",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *        @OA\Response(
     *          response=422,
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function  getReviewById($reviewId, Request $request)
    {
        // with
        $reviewValue = ReviewNew::with([
            "value",
            "user",
            "guest_user",
            "survey"
        ])->find($reviewId);


        return response([
            "success" => true,
            "message" => "Reviews retrieved successfully",
            "data" => $reviewValue
        ], 200);
    }

        // ##################################################
    // Make Reviews Private by IDs
    // ##################################################

    /**
     * @OA\Put(
     *      path="/v1.0/client/reviews/make-private/{ids}",
     *      operationId="makeReviewsPrivate",
     *      tags={"review_management.client"},
     *      summary="Make reviews private by comma-separated IDs",
     *      description="Mark multiple reviews as private by providing comma-separated review IDs",
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          required=true,
     *          description="Comma-separated review IDs (e.g., 1,2,3)",
     *          example="1,2,3"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Reviews updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Reviews marked as private successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="updated_count", type="integer", example=2),
     *                  @OA\Property(property="updated_ids", type="array", @OA\Items(type="integer")),
     *                  @OA\Property(property="invalid_ids", type="array", @OA\Items(type="integer"))
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - Invalid IDs format",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid IDs format")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="No valid reviews found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="No valid reviews found")
     *          )
     *      )
     * )
     */
    public function makeReviewsPrivate($ids)
    {
        // Validate and parse comma-separated IDs
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No IDs provided'
            ], 400);
        }

        // Split the comma-separated string into an array
        $idArray = array_map('trim', explode(',', $ids));
        
        // Filter out non-numeric values
        $numericIds = array_filter($idArray, function($id) {
            return is_numeric($id) && $id > 0;
        });

        if (empty($numericIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IDs format. Please provide comma-separated numeric IDs.'
            ], 400);
        }

        // Convert to integers
        $numericIds = array_map('intval', $numericIds);

        // Find existing reviews with the provided IDs
        $existingReviews = ReviewNew::whereIn('id', $numericIds)->get();
        
        if ($existingReviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid reviews found with the provided IDs',
                'data' => [
                    'requested_ids' => $numericIds,
                    'updated_count' => 0,
                    'updated_ids' => [],
                    'invalid_ids' => $numericIds
                ]
            ], 404);
        }

        // Get the IDs that were found
        $foundIds = $existingReviews->pluck('id')->toArray();
        
        // Get the IDs that were not found
        $invalidIds = array_diff($numericIds, $foundIds);

        // Return error response if no valid reviews found
        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid reviews found with the provided IDs',
                'data' => [
                    'requested_ids' => $numericIds,
                    'valid_ids' => $foundIds,
                    'invalid_ids' => $invalidIds
                ]
            ], 404);
        }

        // Update the reviews to mark them as private
        $updatedCount = ReviewNew::whereIn('id', $foundIds)
            ->update(['is_private' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Reviews marked as private successfully',
            'data' => [
                'updated_count' => $updatedCount,
                'updated_ids' => $foundIds,
            ]
        ], 200);
    }

    // ##################################################
    // Update Guest User Email by Review IDs
    // ##################################################

    /**
     * @OA\Put(
     *      path="/v1.0/client/reviews/update-guest-email/{ids}",
     *      operationId="updateGuestEmailsByReviews",
     *      tags={"review_management.client"},
     *      summary="Update guest user email by comma-separated review IDs",
     *      description="Update email for guest users associated with the provided review IDs",
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          required=true,
     *          description="Comma-separated review IDs (e.g., 1,2,3)",
     *          example="1,2,3"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email"},
     *              @OA\Property(property="email", type="string", format="email", example="guest@example.com")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Guest emails updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Guest user emails updated successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="updated_count", type="integer", example=2),
     *                  @OA\Property(property="updated_guest_ids", type="array", @OA\Items(type="integer")),
     *                  @OA\Property(property="review_ids", type="array", @OA\Items(type="integer"))
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - Invalid input",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid IDs format or email required")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="No valid reviews with guest users found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="No valid reviews with guest users found")
     *          )
     *      )
     * )
     */
    public function updateGuestEmailsByReviews($ids, Request $request)
    {
        // Validate email in request
        $validated = $request->validate([
            'email' => 'required|email|max:255'
        ]);

        // Validate and parse comma-separated IDs
        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No review IDs provided'
            ], 400);
        }

        // Split the comma-separated string into an array
        $idArray = array_map('trim', explode(',', $ids));
        
        // Filter out non-numeric values
        $numericIds = array_filter($idArray, function($id) {
            return is_numeric($id) && $id > 0;
        });

        if (empty($numericIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IDs format. Please provide comma-separated numeric IDs.'
            ], 400);
        }

        // Convert to integers
        $numericIds = array_map('intval', $numericIds);

        // Find reviews with guest users
        $reviews = ReviewNew::whereIn('id', $numericIds)
            ->whereNotNull('guest_id')
            ->with('guest_user')
            ->get();
        
        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid reviews with guest users found for the provided IDs',
                'data' => [
                    'requested_ids' => $numericIds,
                    'updated_count' => 0,
                    'updated_guest_ids' => [],
                    'review_ids' => []
                ]
            ], 404);
        }

        // Get unique guest IDs from the reviews
        $guestIds = $reviews->pluck('guest_id')->unique()->toArray();
        
               // Get the IDs that were not found
        $invalidIds = array_diff($numericIds, $guestIds);

        // Return error response if no valid reviews found
        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid reviews found with the provided IDs',
                'data' => [
                    'requested_ids' => $numericIds,
                    'valid_ids' => $guestIds,
                    'invalid_ids' => $invalidIds
                ]
            ], 404);
        }

        // Update email for all guest users
        $updatedCount = GuestUser::whereIn('id', $guestIds)
            ->update(['email' => $validated['email']]);

        return response()->json([
            'success' => true,
            'message' => 'Guest user emails updated successfully',
            'data' => [
                'updated_count' => $updatedCount,
                'updated_guest_ids' => $guestIds,
                'review_ids' => $reviews->pluck('id')->toArray(),
                'email' => $validated['email']
            ]
        ], 200);
    }
}
