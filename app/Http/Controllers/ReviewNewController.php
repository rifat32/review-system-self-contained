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

use App\Models\User;

use Exception;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReviewNewController extends Controller
{

    /**
     * @OA\Put(
     *      path="/v1.0/reviews/{reviewId}/reply",
     *      operationId="updateReviewReply",
     *      tags={"review_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Update reply content for a review",
     *      description="Allows business owner to update the reply content of a review.",
     *      @OA\Parameter(
     *          name="reviewId",
     *          in="path",
     *          required=true,
     *          description="Review ID",
     *          example="1"
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"reply_content"},
     *              @OA\Property(property="reply_content", type="string", example="Thank you for your feedback.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Reply updated successfully"
     *      )
     * )
     */
    public function updateReviewReply($reviewId, Request $request)
    {
        $request->validate([
            'reply_content' => 'required|string'
        ]);

        $review = ReviewNew::find($reviewId);

        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check if user is the business owner
        $business = Business::find($review->business_id);
        if ($business->OwnerID != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->update([
            'reply_content' => $request->reply_content,
            'responded_at' => now()
        ]);

        return response()->json([
            'message' => 'Reply updated successfully',
            'reply_content' => $review->reply_content
        ], 200);
    }

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
            $duration = getAudioDuration($audioFile->getRealPath());

            // Transcribe the audio using existing method
            $transcribedText = transcribeAudio($audioFile->getRealPath());

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
     *      tags={"dashboard_management"},
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
        $dateRange = getDateRangeByPeriod($period);



        // Calculate metrics using existing methods
        $metrics = calculateDashboardMetrics($businessId, $dateRange);

        // Get rating breakdown using existing getAverage method logic
        $ratingBreakdown = extractRatingBreakdown(
            ReviewNew::withCalculatedRating()
                ->globalFilters(0, $businessId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->get()
        );

         // Get tags breakdown (NEW)
    $tagsBreakdown = extractTagsBreakdown($businessId, $dateRange);

        // Get AI insights using existing AI pipeline
        $aiInsights = getAiInsightsPanel($businessId, $dateRange);

        // Get staff performance using existing staff suggestions
        $staffPerformance = getStaffPerformanceSnapshot($businessId, $dateRange);

        // Get recent reviews feed
        $reviewFeed = getReviewFeed($businessId, $dateRange);

        // Get available filters
        $filters = getAvailableFilters($businessId);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'metrics' => $metrics,
                'rating_breakdown' => $ratingBreakdown,
                'tags_breakdown' => $tagsBreakdown,
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
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating()
            ->get();

        $data = extractRatingBreakdown($reviews);

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
            ->globalFilters(0, $businessId)
            ->with("business", "value")
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating()
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
            ->globalFilters(0, $businessId)
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating()
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
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating()
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
            // Use calculated_rating from the query instead of recalculating
            $rating = (float) $review->calculated_rating;

            if ($rating > 0) {
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
     *      tags={"review_management"},
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      security={{"bearerAuth": {}}},
     *      summary="Store review by authenticated user",
     *      description="Store review with AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"description","rate","comment","values"},
     *              @OA\Property(property="description", type="string", example="Test"),
     *              @OA\Property(property="rate", type="string", example="2.5"),
     *              @OA\Property(property="comment", type="string", example="Not good"),
     *              @OA\Property(property="is_overall", type="boolean", example=true),
     *              @OA\Property(property="staff_id", type="integer", example="1"),
     *              @OA\Property(property="branch_id", type="integer", example="1"),
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
            'business_services' => 'nullable|array',
            'business_services.*.business_service_id' => 'required|exists:business_services,id',
            'business_services.*.business_area_id' => 'required|exists:business_areas,id',
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
        //         'voice_duration' => getAudioDuration($request->file('audio')->getRealPath()),
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
            'rate' => null,
            'user_id' => $request->user()->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
            "branch_id" => $request->branch_id ?? null,
            "is_voice_review" => $request->is_voice_review ?? false,
            "is_ai_processed" => 0,

            "business_area_id" => $request->business_area_id ?? null,
            "business_service_id" => $request->business_service_id ?? null,

        ];

        // Add voice data if present
        // if ($voiceData) {
        //     $reviewData = array_merge($reviewData, $voiceData);
        // }
        $averageRating = collect($request->values)
            ->pluck('star_id')
            ->filter()
            ->avg();

        $review = ReviewNew::create($reviewData);
        storeReviewValues($review, $request->values, $business);

        $businessServicesData = [];
        if (is_array($request->business_services) && count($request->business_services) > 0) {
            foreach ($request->business_services as $service) {
                $businessServicesData[$service['business_service_id']] = [
                    'business_area_id' => $service['business_area_id']
                ];
            }
        }

        $review->business_services()->sync($businessServicesData);


        $responseData = [
            "success" => true,
            "message" => "created successfully",
            "averageRating" => $averageRating,
            "review_id" => $review->id,
            "review" => $review,

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
     *      tags={"review_management"},
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      security={{"bearerAuth": {}}},
     *      summary="Store review by guest user",
     *      description="Store guest review with AI analysis",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"guest_full_name","guest_phone","description","rate","comment","values"},
     *              @OA\Property(property="guest_full_name", type="string", example="Rifat"),
     *              @OA\Property(property="guest_phone", type="string", example="0177"),
     *              @OA\Property(property="description", type="string", example="Test"),
     *              @OA\Property(property="rate", type="string", example="2.5"),
     *              @OA\Property(property="comment", type="string", example="Not good"),
     *              @OA\Property(property="is_overall", type="boolean", example=true),
     *              @OA\Property(property="is_voice_review", type="boolean", example=false),
     *              @OA\Property(property="latitude", type="number", example="23.8103"),
     *              @OA\Property(property="longitude", type="number", example="90.4125"),
     *              @OA\Property(property="staff_id", type="number", example="1"),
     *              @OA\Property(property="branch_id", type="number", example="1"),
     *              @OA\Property(
     *                  property="business_services",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="business_service_id", type="integer", example=1),
     *                      @OA\Property(property="business_area_id", type="integer", example=1)
     *                  ),
     *                  description="Array of business services with their area IDs"
     *              ),
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
            'business_services' => 'present|array',
            'business_services.*.business_service_id' => 'required|exists:business_services,id',
            'business_services.*.business_area_id' => 'required|exists:business_areas,id',
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
                $distance = getDistanceMeters($guest_lat, $guest_lon, $business->latitude, $business->longitude);
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
        //         'voice_duration' => getAudioDuration($request->file('audio')->getRealPath()),
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
            'rate' => null,
            'guest_id' => $guest->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
            "branch_id" => $request->branch_id ?? null,
            "is_voice_review" => $request->is_voice_review  ? true : false,
            "is_ai_processed" => 0,
            "business_area_id" => $request->business_area_id ?? null,
            "business_service_id" => $request->business_service_id ?? null,
        ];




        // Add voice data if present
        // if ($voiceData) {
        //     $reviewData = array_merge($reviewData, $voiceData);
        // }

        $review = ReviewNew::create($reviewData);
        storeReviewValues($review, $request->values, $business);

        // Attach business services with their respective business_area_id

        $businessServicesData = [];
        foreach ($request->business_services as $service) {
            $businessServicesData[$service['business_service_id']] = [
                'business_area_id' => $service['business_area_id']
            ];
        }
        $review->business_services()->sync($businessServicesData);


        $average_rating = collect($request->values)
            ->pluck('star_id')
            ->filter()
            ->avg();

        $responseData = [
            "message" => "created successfully",
            "averageRating" => $average_rating,
            "review_id" => $review->id,
            "review" => $review,
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

    private function fetchAndFormatQuestions(Request $request, bool $isUnauthorized, bool $isOverallSpecific = false)
    {
        $businessId = $request->business_id;

        // --- 1. Business and User Validation ---
        $isDefault = false;
        if (!$isUnauthorized && $request->user()->hasRole("superadmin")) {
            $isDefault = true;
            // Superadmin can bypass business check if requesting default questions
        } else {
            $business = Business::where(["id" => $businessId])->first();
            if (!$business) {
                return response("No Business Found", 404);
            }
        }

        // --- 2. Survey Validation ---
        if ($request->filled('survey_id')) {
            $survey = Survey::find($request->survey_id);
            if (!$survey) {
                // Return different responses based on the original methods' logic
                if ($isUnauthorized && $request->route()->getName() === 'getQuestionAllUnauthorized') {
                    return response([
                        "status" => false,
                        "message" => 'Survey not found' . $request->survey_id
                    ], 404);
                }
                return response("Survey not found", 404);
            }
        }

        // --- 3. Initial Query Setup ---
        $query = Question::query();

        // Specific constraints based on the original methods
        if ($isUnauthorized) {
            // getQuestionAllUnauthorizedOverall had a 'is_default' constraint
            if ($isOverallSpecific) {
                $query->where(["business_id" => $businessId, "is_default" => 0]);
            } else {
                // getQuestionAllUnauthorized did not have the 'is_default' constraint
                $query->where(["business_id" => $businessId]);
            }
        } else {
            // getQuestionAll logic
            $query->where(["business_id" => $businessId, "is_default" => $isDefault])
                ->where(["show_in_user" => 1]); // This was unique to getQuestionAll
        }


        // --- 4. Applying Dynamic Query Filters (Common Logic) ---

        // is_active filter (only present in Unauthorized and OverallUnauthorized)
        if ($isUnauthorized && $request->filled("is_active")) {
            $query->where("questions.is_active", $request->input("is_active"));
        }

        // is_overall filter (Common)
        $query->when($request->filled("is_overall"), function ($q) use ($request) {
            $q->when($request->boolean("is_overall"), function ($sub) {
                $sub->where("questions.is_overall", 1);
            }, function ($sub) {
                $sub->where("questions.is_overall", 0);
            });
        });

        // survey_name filter (only present in getQuestionAll)
        if (!$isUnauthorized && $request->filled('survey_name')) {
            $query->where('survey_name', $request->input('survey_name'));
        }

        // survey_id filter (Common, but slight difference in syntax)
        $query->when($request->filled('survey_id'), function ($q) use ($request) {
            $surveyId = $request->input('survey_id');
            // Using a consistent eloquent syntax to find questions related to the survey
            $q->whereHas('surveys', function ($sub) use ($surveyId) {
                $sub->where('surveys.id', $surveyId);
            });
        });


        $questions = $query->get();

        // --- 5. Data Formatting (The bulk of the duplication) ---
        $data = json_decode(json_encode($questions), true);
        foreach ($questions as $key1 => $question) {
            $data[$key1]["stars"] = []; // Initialize the stars array
            foreach ($question->question_stars as $key2 => $questionStar) {
                $starData = json_decode(json_encode($questionStar->star), true);
                $starData["tags"] = [];

                foreach ($questionStar->star->star_tags as $starTag) {
                    if ($starTag->question_id == $question->id) {
                        array_push($starData["tags"], json_decode(json_encode($starTag->tag), true));
                    }
                }
                $data[$key1]["stars"][$key2] = $starData;
            }
        }

        // --- 6. Final Response (Handling different return formats) ---
        if ($request->route()->getName() === 'getQuestionAllUnauthorized') {
            return response([
                "status" => true,
                "message" => "Questions retrieved successfully",
                "data" => $data
            ], 200);
        }

        // Default response for getQuestionAllUnauthorizedOverall and getQuestionAll
        return response($data, 200);
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

    public function getQuestionAllUnauthorizedOverall(Request $request)
    {
        // $isUnauthorized = true, $isOverallSpecific = true (to enforce is_default=0)
        return $this->fetchAndFormatQuestions($request, true, true);
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
    public function getQuestionAllUnauthorized(Request $request)
    {

        // $isUnauthorized = true, $isOverallSpecific = false (no is_default=0 constraint)
        return $this->fetchAndFormatQuestions($request, true, false);
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
    public function getQuestionAll(Request $request)
    {

        // $isUnauthorized = false
        return $this->fetchAndFormatQuestions($request, false);
    }

    /**
     * Common logic to fetch and format review report data for questions.
     *
     * @param Request $request
     * @param string $userTypeFilter 'user' or 'guest' to determine the filter column (guest_id/user_id).
     * @param bool $applyDateRange Whether to apply start_date and end_date filters.
     * @return array|\Illuminate\Http\Response The formatted report data or an error response.
     */
    private function fetchQuestionReportData(Request $request, string $userTypeFilter, bool $applyDateRange)
    {
        $businessId = $request->business_id;

        $business = Business::where(["id" => $businessId])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        $isGuestFilter = ($userTypeFilter === 'guest');
        $idColumnToFilter = $isGuestFilter ? 'user_id' : 'guest_id';
        $filterValue = NULL; // We are filtering where the respective ID is NULL

        // 1. Fetch Questions (Part 2 Data)
        $questionQuery = Question::where(["business_id" => $businessId, "is_default" => false]);
        $questions = $questionQuery->get();
        $data = json_decode(json_encode($questions), true);

        // Date range query scope helper
        $applyDateRangeScope = function ($query) use ($request, $applyDateRange) {
            if ($applyDateRange && $request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            return $query;
        };

        // 2. Calculate Question-Level Metrics
        foreach ($questions as $key1 => $question) {
            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            $data[$key1]["rating"] = 0; // Initialize rating

            foreach ($question->question_stars as $key2 => $questionStar) {
                $star = $questionStar->star;
                $data[$key1]["stars"][$key2] = json_decode(json_encode($star), true);
                $data[$key1]["stars"][$key2]["tag_ratings"] = [];

                // --- A. Star Count Calculation ---
                $starCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $businessId,
                        "question_id" => $question->id,
                        "star_id" => $star->id,
                        "review_news." . $idColumnToFilter => $filterValue,
                    ]);

                $starCountQuery = $applyDateRangeScope($starCountQuery);
                $starsCount = $starCountQuery->count();

                $data[$key1]["stars"][$key2]["stars_count"] = $starsCount;

                $starCountTotal += $starsCount * $star->value;
                $starCountTotalTimes += $starsCount;

                // --- B. Tag Ratings Calculation ---
                foreach ($star->star_tags as $starTag) {
                    if ($starTag->question_id == $question->id) {
                        $tag = $starTag->tag;

                        // Calculate 'count' (Total reviews for this tag on this question across all stars)
                        $tagCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "tag_id" => $tag->id,
                                "review_news." . $idColumnToFilter => $filterValue,
                            ]);
                        $tagCountQuery = $applyDateRangeScope($tagCountQuery);
                        $tagCount = $tagCountQuery->count();

                        if ($tagCount > 0) {
                            $tagData = json_decode(json_encode($tag), true);
                            $tagData['count'] = $tagCount;

                            // Add to overall tags_rating list if it's new
                            if (!collect($tags_rating)->contains('id', $tag->id)) {
                                array_push($tags_rating, $tagData);
                            }
                        }

                        // Calculate 'total' (Total reviews for this tag AND this specific star on this question)
                        $tagTotalQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "star_id" => $star->id,
                                "tag_id" => $tag->id,
                                "review_news." . $idColumnToFilter => $filterValue,
                            ]);
                        $tagTotalQuery = $applyDateRangeScope($tagTotalQuery);
                        $tagTotal = $tagTotalQuery->count();

                        if ($tagTotal > 0) {
                            $tagTotalData = json_decode(json_encode($tag), true);
                            $tagTotalData['total'] = $tagTotal;
                            array_push($data[$key1]["stars"][$key2]["tag_ratings"], $tagTotalData);
                        }
                    }
                }
            }

            // Calculate final question rating
            if ($starCountTotalTimes > 0) {
                $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
            }
            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique('id')->toArray());
        }

        // 3. Calculate Overall Metrics (Part 1 Data)
        $data2 = [];
        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {
            $starCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "star_id" => $star->id,
                    "review_news." . $idColumnToFilter => $filterValue,
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");

            $starCountQuery = $applyDateRangeScope($starCountQuery);
            $selectedCount = $starCountQuery->count();

            $data2["star_" . $star->value . "_selected_count"] = $selectedCount;

            $totalCount += $selectedCount * $star->value;
            $ttotalRating += $selectedCount;
        }

        // Calculate final overall rating
        $data2["total_rating"] = ($ttotalRating > 0) ? ($totalCount / $ttotalRating) : 0;

        // 4. Fetch Total Comments
        $commentQuery = ReviewNew::with("user", "guest_user")
            ->where([
                "business_id" => $businessId,
                $idColumnToFilter => $filterValue,
            ])
            ->globalFilters(0, $businessId)
            ->orderBy('order_no', 'asc')
            ->whereNotNull("comment")
            ->withCalculatedRating();

        if ($applyDateRange && $request->filled('start_date') && $request->filled('end_date')) {
            $commentQuery = $commentQuery->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $data2["total_comment"] = $commentQuery->get();

        return [
            "part1" => $data2,
            "part2" => $data
        ];
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

        // Filter by registered users (guest_id is NULL), Apply date range
        $reportData = $this->fetchQuestionReportData($request, 'user', true);

        if ($reportData instanceof \Illuminate\Http\Response) {
            return $reportData; // Handle error response
        }

        return response([
            "part1" => $reportData["part1"],
            "part2" => $reportData["part2"]
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

        // Filter by guest users (user_id is NULL), Apply date range
        $reportData = $this->fetchQuestionReportData($request, 'guest', true);

        if ($reportData instanceof \Illuminate\Http\Response) {
            return $reportData; // Handle error response
        }

        return response([
            "part1" => $reportData["part1"],
            "part2" => $reportData["part2"]
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
        // Filter by registered users (guest_id is NULL), DO NOT apply date range
        $reportData = $this->fetchQuestionReportData($request, 'user', false);

        if ($reportData instanceof \Illuminate\Http\Response) {
            return $reportData; // Handle error response
        }

        // Note: The original unauthorized method only returned "part1", I've preserved this anomaly.
        return response([
            "part1" => $reportData["part1"],
            // "part2" is omitted to match the original function's return structure
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
        // Parameters: userTypeFilter = 'guest' (user_id = NULL), applyDateRange = false
        $reportData = $this->fetchQuestionReportData($request, 'guest', false);

        if ($reportData instanceof \Illuminate\Http\Response) {
            return $reportData; // Handle error response from helper (e.g., "No Business Found")
        }

        return response([
            "part1" => $reportData["part1"],
            "part2" => $reportData["part2"]
        ], 200);
    }


    /**
     * Common logic to calculate overall star ratings across multiple time quanta.
     *
     * @param Request $request
     * @param string $idColumnToFilter The column to filter for NULL (e.g., 'guest_id' for user reviews, 'user_id' for guest reviews).
     * @return array
     */
    private function calculateQuantumReport(Request $request, string $idColumnToFilter): array
    {
        $businessId = $request->business_id;
        $quantum = (int) $request->quantum;
        $periodDays = (int) $request->period;

        $reportData = [];
        $currentPeriodOffset = 0;

        for ($i = 0; $i < $quantum; $i++) {
            $totalCount = 0;
            $ttotalRating = 0; // Total times a star was selected

            // Determine the start and end dates for the current quantum period
            $endDate = now()->subDays($currentPeriodOffset)->endOfDay();
            // The logic in the original code for subDays was unusual: now()->subDays(($request->period + $period))
            // I will simplify the logic to standard sequential period subtraction for cleaner analysis,
            // but will use the original logic if it's crucial:

            // Original logic:
            // $startDate = now()->subDays(($periodDays + $currentPeriodOffset))->startOfDay();

            // Simplified sequential logic for a fixed period size (which is usually what 'period' implies):
            $startDate = $endDate->copy()->subDays($periodDays)->startOfDay();


            $data2 = [];

            foreach (Star::get() as $star) {
                $selectedCount = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $businessId,
                        "star_id" => $star->id,
                        "review_news." . $idColumnToFilter => NULL
                    ])
                    ->whereBetween('review_news.created_at', [$startDate, $endDate])
                    ->distinct("review_value_news.review_id", "review_value_news.question_id")
                    ->count();

                $data2["star_" . $star->value . "_selected_count"] = $selectedCount;

                $totalCount += $selectedCount * $star->value;
                $ttotalRating += $selectedCount;
            }

            // Calculate final total rating for this period
            $data2["total_rating"] = ($ttotalRating > 0) ? ($totalCount / $ttotalRating) : 0;
            $data2['start_date'] = $startDate->toDateString();
            $data2['end_date'] = $endDate->toDateString();

            array_push($reportData, $data2);

            // Update the offset for the next period
            // Note: The original logic for period update was: $period +=  $request->period + $period;
            // This is incorrect for sequential periods (e.g., Period 1: days 0-7, Period 2: days 8-15).
            // It seems to be calculating $period += 7 + 0 => 7, then $period += 7 + 7 => 14, then $period += 7 + 14 => 21, etc.
            // I will assume the intent was to move by the period size ($periodDays) and correct the offset logic:
            $currentPeriodOffset += $periodDays;
        }

        return $reportData;
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

        // 1. Authorization Check (OwnerID)
        $business = Business::where([
            "id" => $request->business_id,
            "OwnerID" => $request->user()->id
        ])->first();
        if (!$business) {
            return response("No Business Found or you are not the owner of the business", 404);
        }

        // 2. Quantum Calculation (Guest reviews: user_id = NULL)
        $data = $this->calculateQuantumReport($request, 'user_id');

        return response([
            "data" => $data,
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

        // 1. Authorization Check (OwnerID)
        $business = Business::where([
            "id" => $request->business_id,
            "OwnerID" => $request->user()->id
        ])->first();
        if (!$business) {
            return response("No Business Found or you are not the owner of the business", 404);
        }

        // 2. Quantum Calculation (Registered User reviews: guest_id = NULL)
        $data = $this->calculateQuantumReport($request, 'guest_id');

        return response([
            "data" => $data,
        ], 200);
    }

    /**
     * Common logic to calculate Question-Level Metrics and Overall Metrics for a specific user/guest.
     * This is the refactored inner loop content for the ReportByUser methods.
     *
     * @param Request $request
     * @param int $entityId The ID of the current User or GuestUser.
     * @param string $entityType 'user' or 'guest' to determine the filter columns.
     * @param object $business The Business model instance.
     * @return array ['part1' => array, 'part2' => array]
     */
    private function fetchUserReportMetrics(Request $request, int $entityId, string $entityType, $business): array
    {
        $businessId = $business->id;
        $isGuestType = ($entityType === 'guest');

        // Determine the filtering pair based on entityType
        $primaryIdColumn = $isGuestType ? 'guest_id' : 'user_id';
        $secondaryIdColumn = $isGuestType ? 'user_id' : 'guest_id';
        $filterValue = $entityId;
        $secondaryFilterValue = NULL;

        // Date range query scope helper (reused from previous refactoring)
        $applyDateRangeScope = function ($query) use ($request) {
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);
            }
            return $query;
        };

        // --- 1. Fetch Questions (Part 2 Data) for this User/Guest ---
        // This query fetches questions related to the user's reviews, then filters them.
        $questionQuery = Question::leftjoin('review_value_news', 'questions.id', '=', 'review_value_news.question_id')
            ->leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "questions.business_id" => $businessId,
                "questions.is_default" => false,
                "review_news." . $primaryIdColumn => $filterValue // Filter by the specific user/guest ID
            ])
            ->groupBy("questions.id")
            ->select("questions.*");

        $questions = $questionQuery->get();
        $data = json_decode(json_encode($questions), true);

        // 2. Calculate Question-Level Metrics (Part 2)
        foreach ($questions as $key1 => $question) {
            $tags_rating = [];
            $starCountTotal = 0;
            $starCountTotalTimes = 0;
            $data[$key1]["rating"] = 0;

            foreach ($question->question_stars as $key2 => $questionStar) {
                $star = $questionStar->star;
                $data[$key1]["stars"][$key2] = json_decode(json_encode($star), true);
                $data[$key1]["stars"][$key2]["tag_ratings"] = [];

                // --- A. Star Count Calculation ---
                $starCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where([
                        "review_news.business_id" => $businessId,
                        "question_id" => $question->id,
                        "star_id" => $star->id,
                        "review_news." . $primaryIdColumn => $filterValue, // Primary filter (User/Guest ID)
                        "review_news." . $secondaryIdColumn => $secondaryFilterValue, // Secondary filter (NULL check)
                    ]);

                $starsCount = $applyDateRangeScope($starCountQuery)->count();
                $data[$key1]["stars"][$key2]["stars_count"] = $starsCount;

                $starCountTotal += $starsCount * $star->value;
                $starCountTotalTimes += $starsCount;

                // --- B. Tag Ratings Calculation (Count & Total) ---
                foreach ($star->star_tags as $starTag) {
                    if ($starTag->question_id == $question->id) {
                        $tag = $starTag->tag;

                        // Tag 'count' (Total reviews for this tag on this question across all stars by this entity)
                        $tagCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "tag_id" => $tag->id,
                                "review_news." . $primaryIdColumn => $filterValue,
                                "review_news." . $secondaryIdColumn => $secondaryFilterValue,
                            ]);
                        $tagCount = $applyDateRangeScope($tagCountQuery)->count();

                        if ($tagCount > 0) {
                            $tagData = json_decode(json_encode($tag), true);
                            $tagData['count'] = $tagCount;
                            if (!collect($tags_rating)->contains('id', $tag->id)) {
                                array_push($tags_rating, $tagData);
                            }
                        }

                        // Tag 'total' (Total reviews for this tag AND specific star by this entity)
                        $tagTotalQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "star_id" => $star->id,
                                "tag_id" => $tag->id,
                                "review_news." . $primaryIdColumn => $filterValue,
                                "review_news." . $secondaryIdColumn => $secondaryFilterValue,
                            ]);
                        $tagTotal = $applyDateRangeScope($tagTotalQuery)->count();

                        if ($tagTotal > 0) {
                            $tagTotalData = json_decode(json_encode($tag), true);
                            $tagTotalData['total'] = $tagTotal;
                            array_push($data[$key1]["stars"][$key2]["tag_ratings"], $tagTotalData);
                        }
                    }
                }
            }

            if ($starCountTotalTimes > 0) {
                $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
            }
            $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique('id')->toArray());
        }

        // 3. Calculate Overall Metrics (Part 1 Data)
        $data2 = [];
        $totalCount = 0;
        $ttotalRating = 0;

        foreach (Star::get() as $star) {
            $starCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "star_id" => $star->id,
                    "review_news." . $primaryIdColumn => $filterValue,
                    "review_news." . $secondaryIdColumn => $secondaryFilterValue,
                ])
                ->distinct("review_value_news.review_id", "review_value_news.question_id");

            $selectedCount = $applyDateRangeScope($starCountQuery)->count();
            $data2["star_" . $star->value . "_selected_count"] = $selectedCount;

            $totalCount += $selectedCount * $star->value;
            $ttotalRating += $selectedCount;
        }

        $data2["total_rating"] = ($ttotalRating > 0) ? ($totalCount / $ttotalRating) : 0;

        // 4. Fetch Total Comments
        $commentQuery = ReviewNew::with("user", "guest_user")
            ->where([
                "business_id" => $businessId,
                $primaryIdColumn => $filterValue,
                $secondaryIdColumn => $secondaryFilterValue,
            ])
            ->globalFilters(0, $businessId)
            ->orderBy('order_no', 'asc')
            ->whereNotNull("comment")
            ->withCalculatedRating();

        $data2["total_comment"] = $applyDateRangeScope($commentQuery)->get();

        return [
            "part1" => $data2,
            "part2" => $data
        ];
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


        $business = Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        // 1. Fetch Paginated Users
        $usersQuery = User::leftjoin('review_news', 'users.id', '=', 'review_news.user_id')
            ->leftjoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
            ->leftjoin('questions', 'review_value_news.question_id', '=', 'questions.id')
            ->where(["review_news.business_id" => $business->id])
            ->havingRaw('COUNT(review_news.id) > 0')
            ->havingRaw('COUNT(review_value_news.question_id) > 0')
            ->havingRaw('COUNT(questions.id) > 0')
            ->groupBy("users.id")
            ->select("users.*", "review_news.created_at as review_created_at");

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $users = $usersQuery->paginate($perPage);

        // 2. Loop and Calculate Report for Each User (Refactored)
        foreach ($users->items() as $user) {
            $reviewInfo = $this->fetchUserReportMetrics($request, $user->id, 'user', $business);
            $user->review_info = $reviewInfo;
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
        $business = Business::where(["id" => $request->business_id])->first();
        if (!$business) {
            return response("No Business Found", 404);
        }

        // 1. Fetch Paginated Guest Users
        $usersQuery = GuestUser::leftjoin('review_news', 'guest_users.id', '=', 'review_news.guest_id')
            ->leftjoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
            ->leftjoin('questions', 'review_value_news.question_id', '=', 'questions.id')
            ->where(["review_news.business_id" => $business->id])
            ->havingRaw('COUNT(review_news.id) > 0')
            ->havingRaw('COUNT(review_value_news.question_id) > 0')
            ->havingRaw('COUNT(questions.id) > 0')
            ->groupBy("guest_users.id") // Added missing 'guest_users.id' to align with the original GROUP BY logic
            ->select("guest_users.*", "review_news.created_at as review_created_at");

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }
        $users = $usersQuery->paginate($perPage);

        // 2. Loop and Calculate Report for Each Guest User (Refactored)
        foreach ($users->items() as $guestUser) {
            $reviewInfo = $this->fetchUserReportMetrics($request, $guestUser->id, 'guest', $business);
            $guestUser->review_info = $reviewInfo;
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
        $query = ReviewNew::where('business_id', $businessId)
            ->where(function ($q) {
                $q->where('is_private', 0)
                    ->orWhereNull('is_private');
            })
            ->globalFilters(0, $businessId)
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating();

        // Apply date filters if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $reviews = $query->get();

        $data["total_reviews"] = $reviews->count();

        // Initialize counters with more detailed breakdown
        $ratingCounts = [
            'five_star' => 0,    // 4.5-5.0
            'four_star' => 0,    // 4.0-4.49
            'three_star' => 0,   // 3.0-3.99
            'two_star' => 0,     // 2.0-2.99
            'one_star' => 0,     // 0-1.99
            'exact_ratings' => [
                '1' => 0,
                '2' => 0,
                '3' => 0,
                '4' => 0,
                '5' => 0
            ]
        ];

        $totalRating = 0;
        $validReviews = 0;
        $positiveReviews = 0; // 4+ stars
        $neutralReviews = 0;  // 3 stars
        $negativeReviews = 0; // 1-2 stars

        foreach ($reviews as $review) {
            $rating = $review->calculated_rating;

            // Only count reviews with valid ratings (> 0)
            if ($rating > 0) {
                $totalRating += $rating;
                $validReviews++;

                // Count by star ranges (for detailed breakdown)
                if ($rating >= 4.5) {
                    $ratingCounts['five_star']++;
                } elseif ($rating >= 4.0) {
                    $ratingCounts['four_star']++;
                } elseif ($rating >= 3.0) {
                    $ratingCounts['three_star']++;
                } elseif ($rating >= 2.0) {
                    $ratingCounts['two_star']++;
                } else {
                    $ratingCounts['one_star']++;
                }

                // Count by rounded integer ratings (for traditional star display)
                $roundedRating = round($rating);
                if (isset($ratingCounts['exact_ratings'][$roundedRating])) {
                    $ratingCounts['exact_ratings'][$roundedRating]++;
                }

                // Count for sentiment analysis
                if ($rating >= 4) {
                    $positiveReviews++;
                } elseif ($rating == 3) {
                    $neutralReviews++;
                } else {
                    $negativeReviews++;
                }
            }
        }

        $data['rating'] = $ratingCounts;
        $data['avg_rating'] = $validReviews > 0 ? round($totalRating / $validReviews, 1) : 0;

        // Add detailed statistics
        $data['statistics'] = [
            'total_reviews' => $reviews->count(),
            'reviews_with_ratings' => $validReviews,
            'positive_reviews' => $positiveReviews,
            'neutral_reviews' => $neutralReviews,
            'negative_reviews' => $negativeReviews,
            'positive_percentage' => $validReviews > 0 ? round(($positiveReviews / $validReviews) * 100, 1) : 0,
            'csat_score' => $validReviews > 0 ? round(($positiveReviews / $validReviews) * 100, 1) : 0,
            'nps_score' => $validReviews > 0
                ? round((($positiveReviews / $validReviews) - ($negativeReviews / $validReviews)) * 100, 1)
                : 0
        ];

        // Add rating distribution for charts
        $data['distribution'] = [
            'labels' => ['5 Star', '4 Star', '3 Star', '2 Star', '1 Star'],
            'values' => [
                $ratingCounts['five_star'],
                $ratingCounts['four_star'],
                $ratingCounts['three_star'],
                $ratingCounts['two_star'],
                $ratingCounts['one_star']
            ],
            'percentages' => $validReviews > 0 ? [
                round(($ratingCounts['five_star'] / $validReviews) * 100, 1),
                round(($ratingCounts['four_star'] / $validReviews) * 100, 1),
                round(($ratingCounts['three_star'] / $validReviews) * 100, 1),
                round(($ratingCounts['two_star'] / $validReviews) * 100, 1),
                round(($ratingCounts['one_star'] / $validReviews) * 100, 1)
            ] : [0, 0, 0, 0, 0]
        ];

        // Add date range info if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $data['date_range'] = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ];
        }

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
        ])

            ->globalFilters(1, $businessId)

            ->where("business_id", $businessId)
            ->when($request->has('is_private'), function ($q) use ($request) {
                $isPrivate = $request->input('is_private');
                if ($isPrivate == 0) {
                    $q->where(function ($subQ) {
                        $subQ->where('is_private', 0)
                            ->orWhereNull('is_private');
                    });
                } else {
                    $q->where('is_private', $isPrivate);
                }
            })
            ->withCalculatedRating();

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
                $query->orderByRaw('calculated_rating DESC NULLS LAST');
                break;
            case 'lowest_rating':
                $query->orderByRaw('calculated_rating ASC NULLS LAST');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

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
        ])->withCalculatedRating()
            ->where("id", $reviewId)
            ->first();

        if (!$reviewValue) {
            return response([
                "success" => false,
                "message" => "No Review Found",
                "data" => null
            ], 404);
        }


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
        $existingReviews = ReviewNew::whereIn('id', $numericIds)
            ->get();

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
