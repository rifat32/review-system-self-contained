<?php

namespace App\Http\Controllers;

use App\Models\GuestUser;
use App\Models\Question;
use App\Models\QuestionStar;
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
use getID3;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReviewNewController extends Controller
{


    // ##################################################
// Transcribe Voice File to Text
// ##################################################

    /**
     * @OA\Post(
     *      path="/v1.0/voice/transcribe",
     *      operationId="transcribeVoice",
     *      tags={"voice"},
     *      security={{"bearerAuth": {}}},
     *      summary="Transcribe voice file to text",
     *      description="Upload a voice file and get transcribed text using AI speech recognition",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"audio"},
     *                  @OA\Property(
     *                      property="audio",
     *                      type="string",
     *                      format="binary",
     *                      description="Audio file to transcribe (mp3, wav, m4a, ogg)"
     *                  ),
     *                  @OA\Property(
     *                      property="language",
     *                      type="string",
     *                      description="Language code for transcription (optional, defaults to auto-detect)",
     *                      example="en"
     *                  ),
     *                  @OA\Property(
     *                      property="task",
     *                      type="string",
     *                      description="Task type: transcribe or translate",
     *                      enum={"transcribe", "translate"},
     *                      example="transcribe"
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful transcription",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Transcription completed successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="text", type="string", example="This is the transcribed text from the audio file."),
     *                  @OA\Property(property="language", type="string", example="en"),
     *                  @OA\Property(property="duration", type="number", example=12.5),
     *                  @OA\Property(property="file_size", type="integer", example=102400),
     *                  @OA\Property(property="mime_type", type="string", example="audio/mpeg")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid audio file or file too large")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Entity",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to process audio file")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Transcription service unavailable")
     *          )
     *      )
     * )
     */
    public function transcribeVoice(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'audio' => 'required|file|mimes:mp3,wav,m4a,ogg,mp4,flac,aac|max:51200', // 50MB max
                'language' => 'nullable|string|max:10',
                'task' => 'nullable|in:transcribe,translate'
            ]);

            $audioFile = $request->file('audio');
            $language = $request->input('language', 'en');
            $task = $request->input('task', 'transcribe');

            // Get audio file info
            $fileSize = $audioFile->getSize();
            $mimeType = $audioFile->getMimeType();

            // Get audio duration
            $duration = $this->getAudioDuration($audioFile->getRealPath());

            // Transcribe the audio using existing method
            $transcribedText = $this->transcribeAudio($audioFile->getRealPath());

            // If transcription is empty, try alternative method
            // if (empty($transcribedText)) {
            //     $transcribedText = $this->fallbackTranscribeAudio($audioFile);
            // }

            // If still empty, return error
            if (empty($transcribedText)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not transcribe audio. Please try again with a clearer audio file.',
                    'data' => [
                        'detected' => false,
                        'duration' => $duration,
                        'file_info' => [
                            'size' => $fileSize,
                            'type' => $mimeType
                        ]
                    ]
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transcription completed successfully',
                'data' => [
                    'text' => $transcribedText,
                    'language' => $language,
                    'duration' => $duration,
                    'file_size' => $fileSize,
                    'mime_type' => $mimeType,
                    'task' => $task,
                    'word_count' => str_word_count($transcribedText)
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to transcribe audio file. Please try again.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }


// ##################################################
// Get Overall Business Dashboard Data
// ##################################################

    /**
     * @OA\Get(
     *      path="/v1.0/reviews/overall-dashboard/{businessId}",
     *      operationId="getOverallDashboardData",
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
    public function getOverallDashboardData($businessId, Request $request)
    {
        $filterable_fields = [
            "last_30_days",
            "last_7_days",
            "this_month",
            "last_month"
        ];



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
// Update Business Settings
// ##################################################

    /**
     * @OA\Put(
     *      path="/v1.0/businesses/{businessId}/review-settings",
     *      operationId="updatedReviewSettings",
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
    public function updatedReviewSettings($businessId, Request $request)
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

            'export_settings' => 'json'
        ]);

        $business->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review settings updated successfully',
            'data' => $business->only([
                'id',
                'enable_detailed_survey',

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
            $getID3 = new getID3();
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
        // Calculate average rating from ReviewValue
        $avgRating = $this->calculateAverageRatingForReviews($reviews);
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
        // Get reviews with their values
        $reviews = ReviewNew::with(['value'])
            ->globalFilters(1, $businessId)
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        $previousReviews = ReviewNew::with(['value'])
            ->globalFilters(1, $businessId)
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [
                $dateRange['start']->copy()->subDays(30),
                $dateRange['end']->copy()->subDays(30)
            ])
            ->get();

        $total = $reviews->count();
        $previousTotal = $previousReviews->count();

        // Calculate current period ratings
        $currentAvgRating = $this->calculateAverageRatingForReviews($reviews);

        // Calculate previous period ratings
        $previousAvgRating = $this->calculateAverageRatingForReviews($previousReviews);

        // Calculate sentiment scores (still from ReviewNew)
        $current_sentiment_score = $reviews->avg('sentiment_score') ?? 0;
        $previous_sentiment_score = $previousReviews->avg('sentiment_score') ?? 0;

        return [
            'avg_overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $this->calculatePercentageChange(
                    $currentAvgRating,
                    $previousAvgRating
                )
            ],
            'ai_sentiment_score' => [
                'value' => round($current_sentiment_score * 10, 1),
                'max' => 10,
                'change' => $this->calculatePercentageChange(
                    $current_sentiment_score,
                    $previous_sentiment_score
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
            ->globalFilters(1, $businessId)
            ->get();

        // Get review IDs for bulk calculation
        $reviewIds = $reviews->pluck('id')->toArray();

        // Calculate ratings in bulk
        $ratings = $this->calculateBulkRatings($reviewIds);



        // Initialize counters
        $excellent = 0;
        $good = 0;
        $average = 0;
        $poor = 0;
        $totalRating = 0;
        $validReviews = 0;

        // Count ratings based on calculated values
        foreach ($reviews as $review) {
            $rating = $ratings->get($review->id);

            if ($rating !== null) {
                $totalRating += $rating;
                $validReviews++;

                switch (true) {
                    case $rating == 5:
                        $excellent++;
                        break;
                    case $rating == 4:
                        $good++;
                        break;
                    case $rating == 3:
                        $average++;
                        break;
                    case $rating <= 2:
                        $poor++;
                        break;
                }
            }
        }

        // Calculate average rating
        $avgRating = $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0;

        return [
            'excellent' => [
                'percentage' => $validReviews > 0 ? round(($excellent / $validReviews) * 100) : 0,
                'count' => $excellent
            ],
            'good' => [
                'percentage' => $validReviews > 0 ? round(($good / $validReviews) * 100) : 0,
                'count' => $good
            ],
            'average' => [
                'percentage' => $validReviews > 0 ? round(($average / $validReviews) * 100) : 0,
                'count' => $average
            ],
            'poor' => [
                'percentage' => $validReviews > 0 ? round(($poor / $validReviews) * 100) : 0,
                'count' => $poor
            ],
            'avg_rating' => $avgRating
        ];
    }

    private function getAiInsightsPanel($businessId, $dateRange)
    {
        // Use existing AI suggestions and topics
        $reviews = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('ai_suggestions')
            ->globalFilters(1, $businessId)
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
        $staffReviews = ReviewNew::with('staff', 'value')
            ->where('business_id', $businessId)
            ->globalFilters(1, $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->groupBy('staff_id');

        $staffData = [];

        foreach ($staffReviews as $staffId => $reviews) {
            if ($reviews->count() < 3) continue; // Minimum reviews

            $staff = $reviews->first()->staff;

            // Calculate average rating from ReviewValue
            $avgRating = $this->calculateAverageRatingForReviews($reviews);

            $staff_suggestions = $reviews->pluck('staff_suggestions')->flatten()->unique();

            $staffData[] = [
                'id' => $staffId,
                'name' => $staff->name,
                'rating' => round($avgRating, 1),
                'review_count' => $reviews->count(),
                'skill_gaps' => $this->extractSkillGapsFromSuggestions($staff_suggestions),
                'recommended_training' => $staff_suggestions->first() ?? 'General Training'
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
        $reviews = ReviewNew::with(['user', 'guest_user', 'staff', 'value.tag', 'value'])
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->orderBy('created_at', 'desc')
            ->globalFilters(1, $businessId)
            ->limit($limit)
            ->get();

        return $reviews->map(function ($review) {
            // Calculate rating on the fly
            $calculatedRating = $this->calculateRatingFromReviewValues($review->id);

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
     * @OA\Get(
     *   path="/v1.0/review-new/values/{businessId}/{rate}",
     *   operationId="getReviewValue",
     *   tags={"review"},
     *   security={{"bearerAuth":{}}},
     *   summary="This method is to get Review Value",
     *   description="This method is to get Review Value",
     *
     *   @OA\Parameter(
     *     name="businessId",
     *     in="path",
     *     required=true,
     *     description="businessId",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Parameter(
     *     name="rate",
     *     in="path",
     *     required=true,
     *     description="rate",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Review values retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(type="object")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=400, description="Bad Request"),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Not Found"),
     *   @OA\Response(response=422, description="Unprocessable Content")
     * )
     */


    public function getReviewValue($businessId, $rate, Request $request)
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
     *      operationId="getAverages",
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
    public function getAverages($businessId, $start, $end, Request $request)
    {
        // Get reviews with their values
        $reviews = ReviewNew::with(['value'])
            ->where("business_id", $businessId)
            ->globalFilters(1, $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->get();

        $data["total"] = $reviews->count();
        $data["one"] = 0;
        $data["two"] = 0;
        $data["three"] = 0;
        $data["four"] = 0;
        $data["five"] = 0;

        foreach ($reviews as $review) {
            // Calculate rating from ReviewValue for each review
            $rating = $this->calculateRatingFromReviewValues($review->id);

            if ($rating) {
                switch (round($rating)) {
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
        }

        // Calculate average rating
        $totalReviews = $data["total"];
        $weightedSum = ($data["one"] * 1) + ($data["two"] * 2) + ($data["three"] * 3) +
            ($data["four"] * 4) + ($data["five"] * 5);

        $data["average_rating"] = $totalReviews > 0 ? round($weightedSum / $totalReviews, 1) : 0;

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
     *      operationId="filterReviews",
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


    public function  filterReviews($businessId, $rate, $start, $end, Request $request)
    {
        // with
        $reviewValues = ReviewNew::where([
            "business_id" => $businessId,
            "rate" => $rate
        ])
            ->globalFilters(1, $businessId)
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
     *      operationId="reviewByBusinessId",
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

    public function  reviewByBusinessId($businessId, Request $request)
    {
        // with
        $reviewValue = ReviewNew::with("value")->where([
            "business_id" => $businessId,
        ])
            ->globalFilters(1, $businessId)
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



    public function getCustommerReview($businessId, $start, $end, Request $request)
    {
        // Get reviews with their values
        $reviews = ReviewNew::with(['value'])
            ->where("business_id", $businessId)
            ->globalFilters(1, $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->get();

        $data["reviews"] = $reviews;
        $data["total"] = $reviews->count();

        // Initialize counters
        $data["one"] = 0;
        $data["two"] = 0;
        $data["three"] = 0;
        $data["four"] = 0;
        $data["five"] = 0;

        foreach ($reviews as $review) {
            // Calculate rating from ReviewValue for each review
            $rating = $this->calculateRatingFromReviewValues($review->id);

            if ($rating) {
                switch (round($rating)) {
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
        }

        return response($data, 200);
    }

  // ##################################################
// Updated createReview method with audio support
// ##################################################

    /**
     * @OA\Post(
     *      path="/v1.0/review-new/{businessId}",
     *      operationId="createReview",
     *      tags={"review"},
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      security={{"bearerAuth": {}}},
     *      summary="Store review by authenticated user with optional audio",
     *      description="Store review with optional audio transcription and AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"description","rate","comment","values"},
     *                  @OA\Property(property="description", type="string", example="Test"),
     *                  @OA\Property(property="rate", type="string", example="2.5"),
     *                  @OA\Property(property="comment", type="string", example="Not good"),
     *                  @OA\Property(property="is_overall", type="boolean", example=true),
     *                  @OA\Property(property="staff_id", type="integer", example="1"),
     *                 @OA\Property(property="branch_id", type="integer", example="1"),
     *                  @OA\Property(property="audio", type="string", format="binary", description="Optional audio file"),
     *                  @OA\Property(
     *                      property="values",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="question_id", type="integer", example=1),
     *                          @OA\Property(property="tag_id", type="integer", example=2),
     *                          @OA\Property(property="star_id", type="integer", example=4)
     *                      )
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
    public function createReview($businessId, Request $request)
    {
        $request->validate([
            'description' => 'nullable|string',
            'rate' => 'required|numeric|min:1|max:5',
            'staff_id' => 'nullable|exists:users,id',
            "branch_id" => 'nullable|exists:branches,id',
            'comment' => 'nullable|string',
            'is_overall' => 'required|boolean',
            'values' => 'required|array',
            'values.*.question_id' => 'required|integer',
            'values.*.tag_id' => 'nullable|integer',
            'values.*.star_id' => 'nullable|integer',
            // 'audio' => 'nullable|file|mimes:mp3,wav,m4a,ogg|max:10240',
            "is_voice_review" => 'required|boolean',
        ]);

        $business = Business::findOrFail($businessId);
        $raw_text = $request->input('comment', '');

        // Voice review handling
        // $voiceData = null;

        // if ($request->hasFile('audio')) {
        //     $audioPath = $request->file('audio')->store('voice-reviews', 'public');
        //     $audioUrl = Storage::url($audioPath);
        //     $raw_text = $this->transcribeAudio($request->file('audio')->getRealPath());

        //     $voiceData = [
        //         'is_voice_review' => true,
        //         'voice_url' => $audioUrl,
        //         'voice_duration' => $this->getAudioDuration($request->file('audio')->getRealPath()),
        //         'transcription_metadata' => [
        //             'audio_path' => $audioPath,
        //             'file_size' => $request->file('audio')->getSize(),
        //             'mime_type' => $request->file('audio')->getMimeType(),
        //         ]
        //     ];
        // }


      


        // Check if content should be blocked
        // if ($moderationResults['should_block']) {
        //     return response([
        //         "success" => false,
        //         "message" => $moderationResults['action_message'],
        //         "moderation_results" => $moderationResults
        //     ], 400);
        // }

      

       

        $reviewData = [
            'survey_id' => $request->survey_id,
            'description' => $request->description,
            'business_id' => $businessId,
            'rate' => 5,
            'user_id' => $request->user()->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
            "branch_id" => $request->branch_id ?? null,
            "is_voice_review" => $request->is_voice_review ?? false,
            "is_ai_processed" => 0,

        ];

        // Add voice data if present
        // if ($voiceData) {
        //     $reviewData = array_merge($reviewData, $voiceData);
        // }

        $review = ReviewNew::create($reviewData);
        $this->storeReviewValues($review, $request->values, $business);

        $responseData = [
            "message" => "created successfully",
            "review_id" => $review->id,
           
        ];

        // Add voice info if present
        // if ($voiceData) {
        //     $responseData['voice_info'] = [
        //         'voice_url' => $voiceData['voice_url'],
        //         'duration' => $voiceData['voice_duration'],
        //         'transcription' => $raw_text
        //     ];
        // }

        return response($responseData, 201);
    }

// ##################################################
// Updated storeReviewByGuest method with audio support
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
     *      summary="Store review by guest user with optional audio",
     *      description="Store guest review with optional audio transcription and AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  required={"guest_full_name","guest_phone","description","rate","comment","values"},
     *                  @OA\Property(property="guest_full_name", type="string", example="Rifat"),
     *                  @OA\Property(property="guest_phone", type="string", example="0177"),
     *                  @OA\Property(property="description", type="string", example="Test"),
     *                  @OA\Property(property="rate", type="string", example="2.5"),
     *                  @OA\Property(property="comment", type="string", example="Not good"),
     *                  @OA\Property(property="is_overall", type="boolean", example=true),
     *                  @OA\Property(property="latitude", type="number", example="23.8103"),
     *                  @OA\Property(property="longitude", type="number", example="90.4125"),
     *                  @OA\Property(property="staff_id", type="number", example="1"),
     *                 @OA\Property(property="branch_id", type="number", example="1"),
     *                  @OA\Property(property="audio", type="string", format="binary", description="Optional audio file"),
     *                  @OA\Property(
     *                      property="values",
     *                      type="array",
     *                      @OA\Items(
     *                          @OA\Property(property="question_id", type="integer", example=1),
     *                          @OA\Property(property="tag_id", type="integer", example=2),
     *                          @OA\Property(property="star_id", type="integer", example=4)
     *                      )
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
    public function storeReviewByGuest($businessId, Request $request)
    {
        $request->validate([
            'guest_full_name' => 'nullable|string',
            'guest_phone' => 'nullable|string',
            'description' => 'nullable|string',
            'rate' => 'required|numeric|min:1|max:5',
            'staff_id' => 'nullable|exists:users,id',
            'branch_id' => 'nullable|exists:branches,id',
            'comment' => 'nullable|string',
            'is_overall' => 'required|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'values' => 'required|array',
            'values.*.question_id' => 'required|integer',
            'values.*.tag_id' => 'nullable|integer',
            'values.*.star_id' => 'nullable|integer',
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,ogg|max:10240',
            "is_voice_review" => 'required|boolean',
        ]);

        $business = Business::findOrFail($businessId);
        $ip_address = $request->ip();

        // ✅ Step 1: IP restriction check
        if ($business->enable_ip_check) {
            $existing_review = ReviewNew::where('business_id', $businessId)
                ->where('ip_address', $ip_address)
                ->whereDate('created_at', now()->toDateString())
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
            'full_name' => $request->guest_full_name ?? 'anonymous',
            'phone' => $request->guest_phone,
        ]);

        $raw_text = $request->input('comment', '');

       

        // Voice review handling
        // $voiceData = null;
        // if ($request->hasFile('audio')) {
        //     $audioPath = $request->file('audio')->store('voice-reviews', 'public');
        //     $audioUrl = Storage::url($audioPath);
        //     $raw_text = $this->transcribeAudio($request->file('audio')->getRealPath());

        //     $voiceData = [
        //         'is_voice_review' => true,
        //         'voice_url' => $audioUrl,
        //         'voice_duration' => $this->getAudioDuration($request->file('audio')->getRealPath()),
        //         'transcription_metadata' => [
        //             'audio_path' => $audioPath,
        //             'file_size' => $request->file('audio')->getSize(),
        //             'mime_type' => $request->file('audio')->getMimeType(),
        //         ]
        //     ];
        // }

       
       

        $reviewData = [
            'survey_id' => $request->survey_id,
            'description' => $request->description,
            'business_id' => $businessId,
            'rate' => 5,
            'guest_id' => $guest->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
            "branch_id" => $request->branch_id ?? null,
            "is_voice_review" => $request->is_voice_review  ? true : false,
            "is_ai_processed" => 0
        ];

        // Add voice data if present
        // if ($voiceData) {
        //     $reviewData = array_merge($reviewData, $voiceData);
        // }

        $review = ReviewNew::create($reviewData);
        $this->storeReviewValues($review, $request->values, $business);

        $responseData = [
            "message" => "created successfully",
            "review" => $review,
            "review_id" => $review->id,
        ];

        
        // Add voice info if present
        // if ($voiceData) {
        //     $responseData['voice_info'] = [
        //         'voice_url' => $voiceData['voice_url'],
        //         'duration' => $voiceData['voice_duration'],
        //         'transcription' => $raw_text
        //     ];
        // }

        return response($responseData, 201);
    }




    // ##################################################
    // New AI Pipeline Methods
    // ##################################################


   

    

  // ##################################################
// New Helper Methods for Calculating Ratings from ReviewValue
// ##################################################

    /**
     * Calculate average rating for a single review from ReviewValue data
     */
    private function calculateRatingFromReviewValues($reviewId)
    {
        $reviewValues = ReviewValueNew::where('review_id', $reviewId)->get();

        if ($reviewValues->isEmpty()) {
            return null;
        }

        // Get unique questions to avoid double counting
        $uniqueQuestions = $reviewValues->pluck('question_id')->unique();

        $totalRating = 0;
        $questionCount = 0;

        foreach ($uniqueQuestions as $questionId) {
            $questionValues = $reviewValues->where('question_id', $questionId);

            // Get the star rating for this question
            $starValue = Star::where('id', $questionValues->first()->star_id)->value('value') ?? 0;
            // Cast to float to ensure numeric value
            $totalRating += (float) $starValue;
            $questionCount++;
        }

        // Round to 1 decimal place
        return $questionCount > 0 ? round($totalRating / $questionCount, 1) : null;
    }

    /**
     * Calculate average rating for multiple reviews from ReviewValue data
     */
    private function calculateAverageRatingForReviews($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $totalRating = 0;
        $validReviews = 0;

        foreach ($reviews as $review) {
            $rating = $this->calculateRatingFromReviewValues($review->id);
            if ($rating !== null) {
                $totalRating += $rating;
                $validReviews++;
            }
        }

        return $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0;
    }


    /**
     * Optimized method to calculate ratings for multiple reviews in one query
     */
    private function calculateBulkRatings($reviewIds)
    {
        if (empty($reviewIds)) {
            return collect();
        }

        $ratings = ReviewValueNew::join('stars as s', 'review_value_news.star_id', '=', 's.id')
            ->whereIn('review_value_news.review_id', $reviewIds)
            ->select(
                'review_value_news.review_id',
                'review_value_news.question_id',
                's.value as star_value'
            )
            ->orderBy('review_value_news.review_id')
            ->orderBy('review_value_news.question_id')
            ->get();

        // Group by review_id and calculate average per review
        $result = [];

        foreach ($ratings->groupBy('review_id') as $reviewId => $questionRatings) {
            // Get unique questions for this review
            $uniqueQuestions = $questionRatings->unique('question_id');
            $totalRating = $uniqueQuestions->sum('star_value');
            $questionCount = $uniqueQuestions->count();

            $result[$reviewId] = $questionCount > 0
                ? round($totalRating / $questionCount, 1)
                : null;
        }

        return collect($result);
    }
    // ##################################################
    // Helper to store review values (question/star)
    private function storeReviewValues($review, $values, $business)
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


    /**
     *
     * @OA\Get(
     *      path="/review-new/get/questions-all-overall/customer",
     *      operationId="getQuestionAllUnauthorizedOverall",
     *      tags={"z.unused"},
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
     *      tags={"z.unused"},

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
     *      tags={"z.unused"},

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
            ->globalFilters(1, $business->id)
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
            ->globalFilters(1, $business->id)
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
            ->globalFilters(1, $business->id)
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
            ->globalFilters(1, $business->id)
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
                ->globalFilters(1, $business->id)
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
                ->globalFilters(1, $business->id)
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


    public function getAverageRatingClient($businessId, Request $request)
    {
        $reviews = ReviewNew::with('value')
            ->where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('is_private', 0)
                    ->orWhereNull('is_private');
            })
            ->globalFilters(1, $businessId)
            ->orderBy('order_no', 'asc')
            ->get();


        $data["total_reviews"] = $reviews->count();

        // Get review IDs for bulk calculation
        $reviewIds = $reviews->pluck('id')->toArray();

        // Calculate ratings in bulk (more efficient)
        $ratings = $this->calculateBulkRatings($reviewIds);

        // Initialize counters
        $ratingCounts = [
            'one' => 0,
            'two' => 0,
            'three' => 0,
            'four' => 0,
            'five' => 0
        ];

        $totalRating = 0;
        $validReviews = 0;

        foreach ($reviews as $review) {
            $rating = $ratings->get($review->id);

            if ($rating !== null) {
                $totalRating += $rating;
                $validReviews++;

                switch (round($rating)) {
                    case 1:
                        $ratingCounts['one'] += 1;
                        break;
                    case 2:
                        $ratingCounts['two'] += 1;
                        break;
                    case 3:
                        $ratingCounts['three'] += 1;
                        break;
                    case 4:
                        $ratingCounts['four'] += 1;
                        break;
                    case 5:
                        $ratingCounts['five'] += 1;
                        break;
                }
            }
        }

        $data['rating'] = $ratingCounts;
        $data['avg_rating'] = $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0;

        return response([
            "success" => true,
            "message" => "Average rating retrieved successfully",
            "data" => $data
        ], 200);
    }



    /**
     * @OA\Get(
     *      path="/v1.0/client/review-new/{businessId}",
     *      operationId="getReviewByBusinessIdClient",
     *      tags={"review_management.client"},
     *      summary="Get reviews by business id for client",
     *      description="Get reviews by business id with filtering and sorting",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          description="Business ID",
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          required=false,
     *          description="Page number",
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="Items per page",
     *          example=10
     *      ),
     *      @OA\Parameter(
     *          name="is_private",
     *          in="query",
     *          required=false,
     *          description="Filter by privacy status (0 for public, 1 for private)",
     *          example=""
     *      ),
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          required=false,
     *          description="Sort option",
     *          @OA\Schema(
     *              type="string",
     *              enum={"newest", "oldest", "highest_rating", "lowest_rating"}
     *          ),
     *          example="newest"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Reviews retrieved successfully"),
     *              @OA\Property(property="meta", type="object"),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function getReviewByBusinessIdClient($businessId, Request $request)
    {
        $query = ReviewNew::with([
            "value",
            "user",
            "guest_user",
            "survey"
        ])->where([
            "business_id" => $businessId,
        ])
            ->when($request->has('is_private'), function ($q) use ($request) {
                $isPrivate = $request->get('is_private', 0);
                if ($isPrivate == 0) {
                    // For public reviews, include both is_private = 0 and is_private = null
                    $q->where(function ($subQ) {
                        $subQ->where('is_private', 0)
                            ->orWhereNull('is_private');
                    });
                } else {
                    // For private reviews, only is_private = 1
                    $q->where('is_private', $isPrivate);
                }
            })
            ->globalFilters(1, $businessId);

        // Sorting logic
        $sortBy = $request->get('sort_by');

        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'highest_rating':
                $query->orderBy('rate', 'desc');
                break;
            case 'lowest_rating':
                $query->orderBy('rate', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        // $reviewValue = $query->get();

        $result = retrieve_data($query);

        return response([
            "success" => true,
            "message" => "Reviews retrieved successfully",
            "meta" => $result['meta'],
            "data" => $result['data']
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
            "value.question",
            "value.tag",
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
        $numericIds = array_filter($idArray, function ($id) {
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
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            // add unique if needed:
            // 'email' => 'required|email|max:255|unique:guest_users,email',
        ]);

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No review IDs provided'
            ], 400);
        }

        $idArray = array_map('trim', explode(',', $ids));
        $numericIds = array_values(array_unique(array_map('intval', array_filter($idArray, fn($id) => ctype_digit((string)$id) && (int)$id > 0))));

        if (empty($numericIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid IDs format. Please provide comma-separated numeric IDs.'
            ], 400);
        }

        $reviews = ReviewNew::whereIn('id', $numericIds)
            ->whereNotNull('guest_id')
            ->get(['id', 'guest_id']);

        if ($reviews->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid reviews with guest users found for the provided IDs',
                'data' => [
                    'requested_review_ids' => $numericIds,
                    'updated_count' => 0,
                    'updated_guest_ids' => [],
                    'found_review_ids' => []
                ]
            ], 404);
        }

        $foundReviewIds = $reviews->pluck('id')->unique()->values()->all();
        $missingReviewIds = array_values(array_diff($numericIds, $foundReviewIds));

        $guestIds = $reviews->pluck('guest_id')->unique()->values()->all();

        $updatedCount = GuestUser::whereIn('id', $guestIds)
            ->update(['email' => $validated['email']]);

        return response()->json([
            'success' => true,
            'message' => 'Guest user emails updated successfully',
            'data' => [
                'updated_count' => $updatedCount,
                'updated_guest_ids' => $guestIds,
                'found_review_ids' => $foundReviewIds,
                'missing_review_ids' => $missingReviewIds,
                'email' => $validated['email']
            ]
        ], 200);
    }
}
