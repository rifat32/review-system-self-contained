<?php

namespace App\Http\Controllers;

use App\Models\GuestUser;
use App\Models\Question;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\Survey;
use App\Models\Star;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use App\Services\Review\ReviewService;
use App\Services\AIProcessor\AIProcessorService;
use App\Services\AIProcessor\OpenAIProcessorService;
use App\Services\Review\ReviewMetricsService;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ReviewNewController extends Controller
{
    private OpenAIProcessorService $openAIProcessorService;

    public function __construct(
        AIProcessorService $aiProcessorService,
        ReviewService $reviewService,
        ReviewMetricsService $reviewMetricsService,
        DashboardService $dashboardService,
        OpenAIProcessorService $openAIProcessorService
    ) {
        $this->aiProcessorService = $aiProcessorService;
        $this->reviewService = $reviewService;
        $this->reviewMetricsService = $reviewMetricsService;
        $this->dashboardService = $dashboardService;
        $this->openAIProcessorService = $openAIProcessorService;
    }

    /**
     * Calculate average star value from request values
     */
    private function calculateAverageStarValueFromRequest(array $values): ?float
    {
        $starIds = collect($values)
            ->pluck('star_id')
            ->filter()
            ->unique()
            ->values();

        if ($starIds->isEmpty()) {
            return null;
        }

        $starValuesById = Star::whereIn('id', $starIds)->pluck('value', 'id');

        $ratings = collect($values)
            ->pluck('star_id')
            ->filter()
            ->map(fn($starId) => $starValuesById[$starId] ?? null)
            ->filter();

        return $ratings->isNotEmpty() ? round($ratings->avg(), 1) : null;
    }

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
            $duration = $this->aiProcessorService->getAudioDuration($audioFile->getRealPath());

            // Transcribe the audio using existing method
            $transcribedText = $this->aiProcessorService->transcribeAudio($audioFile->getRealPath(), $task);

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
        } catch (Exception $e) {
            throw $e;
        }
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
     *              required={"description","rate","comment","values", "source"},
     *              @OA\Property(property="source", type="string", example="web"),
     *              @OA\Property(property="description", type="string", example="Test"),
     *              @OA\Property(property="rate", type="string", example="2.5"),
     *              @OA\Property(property="comment", type="string", example="Not good"),
     *              @OA\Property(property="is_overall", type="boolean", example=true),
     *              @OA\Property(property="staff_id", type="integer", example="1"),
     *              @OA\Property(property="branch_id", type="integer", example="1"),
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
     *                      @OA\Property(
     *                          property="tag_ids",
     *                          type="array",
     *                          @OA\Items(type="integer", example=2),
     *                          description="Array of tag IDs (many-to-many relationship)"
     *                      ),
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
            'source' => ['required', 'string', 'in:web,app'],
            'description' => 'nullable|string',
            'staff_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($businessId) {
                    $query->whereHas('branches', function ($q) use ($businessId) {
                        $q->where('business_id', $businessId);
                    });
                })
            ],
            "branch_id" => [
                'nullable',
                Rule::exists('branches', 'id')->where('business_id', $businessId)
            ],
            'comment' => 'nullable|string',
            'is_overall' => 'required|boolean',
            'values' => 'required|array',
            'values.*.question_id' => 'required|integer|exists:questions,id',
            'values.*.tag_ids' => 'present|array',
            'values.*.tag_ids.*' => 'integer|exists:tags,id',
            'values.*.star_id' => 'nullable|integer|exists:stars,id',
            'business_services' => 'nullable|array',
            'business_services.*.business_service_id' => [
                'required',
                Rule::exists('business_services', 'id')->where('business_id', $businessId)
            ],
            'business_services.*.business_area_id' => [
                'required',
                Rule::exists('business_areas', 'id')->where('business_id', $businessId)
            ],
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,ogg,mp4|max:10240',
            "is_voice_review" => 'required|boolean',
        ]);

        $business = Business::findOrFail($businessId);
        $raw_text = $request->input('comment', '');

        $reviewData = [
            'survey_id' => $request->survey_id,
            'description' => $request->description,
            'business_id' => $businessId,
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
            'source' => $request->input('source'),
        ];

        if ($request->hasFile('audio')) {
            $folder_path = "business_{$business->OwnerID}/business_{$businessId}/voice-reviews";
            $file = $request->file('audio');
            $filename = $file->hashName();
            $file->storeAs($folder_path, $filename, 'public');
            $reviewData['audio'] = $filename;
        }

        $averageRating = $this->calculateAverageStarValueFromRequest($request->values);

        $review = DB::transaction(function () use ($reviewData, $request, $business) {
            $review = ReviewNew::create($reviewData);
            $this->reviewService->storeReviewValues($review, $request->values, $business);

            if (!empty($request->business_services)) {
                $businessServicesData = [];
                foreach ($request->business_services as $service) {
                    $businessServicesData[$service['business_service_id']] = [
                        'business_area_id' => $service['business_area_id']
                    ];
                }
                $review->business_services()->sync($businessServicesData);
            }

            return $review;
        });


        $responseData = [
            "success" => true,
            "message" => "created successfully",
            "averageRating" => $averageRating,
            "review_id" => $review->id,
            "review" => $review,

        ];

        return response($responseData, 201);
    }

    // ##################################################
// Updated storeReviewByGuest method with audio support
// ##################################################

    /**
     * @OA\Post(
     *      path="/v1.0/review-new-guest/{businessId}",
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
     *              required={"guest_full_name","guest_phone","description","rate","comment","values","source"},
     *              @OA\Property(property="guest_full_name", type="string", example="Rifat"),
     *              @OA\Property(property="guest_phone", type="string", example="0177"),
     *              @OA\Property(property="source", type="string", example="web"),
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
     *                      @OA\Property(
     *                          property="tag_ids",
     *                          type="array",
     *                          @OA\Items(type="integer", example=2),
     *                          description="Array of tag IDs (many-to-many relationship)"
     *                      ),
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
            'source' => ['required', 'string', 'in:web,app'],
            'guest_full_name' => 'nullable|string',
            'guest_phone' => 'nullable|string',
            'description' => 'nullable|string',
            'staff_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(function ($query) use ($businessId) {
                    $query->whereHas('branches', function ($q) use ($businessId) {
                        $q->where('business_id', $businessId);
                    });
                })
            ],
            'branch_id' => [
                'nullable',
                Rule::exists('branches', 'id')->where('business_id', $businessId)
            ],
            'comment' => 'nullable|string',
            'is_overall' => 'required|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'values' => 'required|array',
            'values.*.question_id' => 'required|integer|exists:questions,id',
            'values.*.tag_ids' => 'present|array',
            'values.*.tag_ids.*' => 'integer|exists:tags,id',
            'values.*.star_id' => 'nullable|integer|exists:stars,id',
            'business_services' => 'nullable|array',
            'business_services.*.business_service_id' => [
                'required',
                Rule::exists('business_services', 'id')->where('business_id', $businessId)
            ],
            'business_services.*.business_area_id' => [
                'required',
                Rule::exists('business_areas', 'id')->where('business_id', $businessId)
            ],
            'audio' => 'nullable|file|mimes:mp3,wav,m4a,ogg,mp4|max:10240',
            "is_voice_review" => 'required|boolean',
        ]);

        $business = Business::findOrFail($businessId);
        $ip_address = $request->ip();

        // ✅ Step 1: IP restriction check
        if ($business->enable_ip_check) {
            $existing_review = ReviewNew::where('business_id', $businessId)
                ->where('ip_address', $ip_address)
                ->whereDate('created_at', now()->toDateString())
                ->orderBy('created_at', 'asc')
                ->first();

            if ($existing_review) {
                throw new BadRequestHttpException("You have already submitted a review today from this IP.");
            }
        }

        // ✅ Step 2: Location restriction check
        if ($business->enable_location_check) {
            $guest_lat = $request->input('latitude');
            $guest_lon = $request->input('longitude');


            if (!$guest_lat || !$guest_lon) {
                throw new BadRequestHttpException(
                    'We could not detect your location. Please enable location access in your browser or device settings and try again.'
                );
            }

            $target_lat = $business->latitude;
            $target_lon = $business->longitude;
            $location_name = "business location";

            if ($request->filled('branch_id')) {
                $branch = \App\Models\Branch::find($request->branch_id);
                if ($branch && $branch->lat && $branch->long) {
                    $target_lat = $branch->lat;
                    $target_lon = $branch->long;
                    $location_name = "branch location";
                }
            }

            if ($target_lat && $target_lon) {
                $distance = getDistanceMeters($guest_lat, $guest_lon, $target_lat, $target_lon);
                if ($distance > $business->review_distance_limit) {
                    $distance_away = round($distance - $business->review_distance_limit);
                    $current_distance = round($distance);
                    return response([
                        "message" => "You are too far from the {$location_name} to review (limit {$business->review_distance_limit}m). You are currently {$current_distance}m away.",
                        "distance" => $distance,
                        "limit" => $business->review_distance_limit,
                        "distance_away" => $distance_away
                    ], 400);
                }
            }
        }

        $guest = GuestUser::create([
            'full_name' => $request->guest_full_name ?? 'Anonymous',
            'phone' => $request->guest_phone,
        ]);

        // Ensure guest was created successfully
        if (!$guest || !$guest->id) {
            return response([
                "message" => "Failed to create guest user. Please try again."
            ], 500);
        }

        $raw_text = $request->input('comment', '');


        $reviewData = [
            'survey_id' => $request->survey_id,
            'description' => $request->description,
            'business_id' => $businessId,
            'guest_id' => $guest->id,
            'comment' => $raw_text,
            'raw_text' => $raw_text,
            "ip_address" => $request->ip(),
            "is_overall" => $request->is_overall ?? 0,
            "staff_id" => $request->staff_id ?? null,
            "branch_id" => $request->branch_id ?? null,
            "is_voice_review" => $request->is_voice_review ? true : false,
            "is_ai_processed" => 0,
            "business_area_id" => $request->business_area_id ?? null,
            "business_service_id" => $request->business_service_id ?? null,
            'source' => $request->input('source'),
        ];

        if ($request->hasFile('audio')) {
            $business = Business::findOrFail($businessId);
            $folder_path = "business_{$business->OwnerID}/business_{$businessId}/voice-reviews";
            $file = $request->file('audio');
            $filename = $file->hashName();
            $file->storeAs($folder_path, $filename, 'public');
            $reviewData['audio'] = $filename;
        }

        $review = DB::transaction(function () use ($reviewData, $request, $business) {
            $review = ReviewNew::create($reviewData);
            $this->reviewService->storeReviewValues($review, $request->values, $business);

            if (!empty($request->business_services)) {
                $businessServicesData = [];
                foreach ($request->business_services as $service) {
                    $businessServicesData[$service['business_service_id']] = [
                        'business_area_id' => $service['business_area_id']
                    ];
                }
                $review->business_services()->sync($businessServicesData);
            }

            return $review;
        });


        $average_rating = $this->calculateAverageStarValueFromRequest($request->values);

        $responseData = [
            "message" => "created successfully",
            "averageRating" => $average_rating,
            "review_id" => $review->id,
            "review" => $review,
        ];

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

                        // Calculate 'count' (Total reviews for this

                        // Fixed: Join through pivot table for
                        $tagCountQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->leftjoin('review_value_tag', 'review_value_news.id', '=', 'review_value_tag.review_value_id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "review_value_tag.tag_id" => $tag->id, // Changed to pivot table column
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

                        // Calculate 'total' (Total reviews for this


                        // Fixed: Join through pivot table for many-to-many relationship
                        $tagTotalQuery = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                            ->leftjoin('review_value_tag', 'review_value_news.id', '=', 'review_value_tag.review_value_id')
                            ->where([
                                "review_news.business_id" => $businessId,
                                "question_id" => $question->id,
                                "star_id" => $star->id,
                                "review_value_tag.tag_id" => $tag->id, // Changed to pivot table column
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
            ->globalReviewFilters(0)
            ->filterByDateRange()
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
            ->globalReviewFilters(1)
            ->filterByDateRange()
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
                $highThreshold = \App\Services\Rule\RuleEngineService::getCsatThreshold();
                $neutralThreshold = \App\Services\Rule\RuleEngineService::getNeutralRatingThreshold();

                if ($rating >= $highThreshold) {
                    $positiveReviews++;
                } elseif ($rating >= $neutralThreshold) {
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

            ->globalReviewFilters(1)
            ->filterByDateRange()

            ->where("business_id", $businessId)
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
                // ISNULL returns 1 for null, 0 for not null.
                // Ordering by it first ensures nulls go to the bottom.
                $query->orderByRaw('ISNULL(calculated_rating) ASC, calculated_rating DESC');
                break;
            case 'lowest_rating':
                $query->orderByRaw('ISNULL(calculated_rating) ASC, calculated_rating ASC');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $result = retrieve_data($query);

        // Mask user name for private reviews: set first_Name to "Anonymous" and last_Name to null
        if ($result['data'] instanceof \Illuminate\Support\Collection) {
            $result['data'] = $result['data']->map(function ($review) {
                if (!empty($review->is_private) && isset($review->user) && $review->user) {
                    if (is_object($review->user)) {
                        $review->user->first_Name = 'Anonymous';
                        $review->user->last_Name = null;
                    } elseif (is_array($review->user)) {
                        $review->user['first_Name'] = 'Anonymous';
                        $review->user['last_Name'] = null;
                    }
                }
                return $review;
            });
        } else {
            foreach ($result['data'] as $key => $review) {
                $isPrivate = is_object($review) ? (bool) ($review->is_private ?? false) : (bool) ($review['is_private'] ?? false);
                if ($isPrivate) {
                    if (is_object($review) && isset($review->user) && $review->user) {
                        if (is_object($review->user)) {
                            $review->user->first_Name = 'Anonymous';
                            $review->user->last_Name = null;
                        } elseif (is_array($review->user)) {
                            $review->user['first_Name'] = 'Anonymous';
                            $review->user['last_Name'] = null;
                        }
                        $result['data'][$key] = $review;
                    } elseif (is_array($review) && isset($review['user']) && $review['user']) {
                        if (is_array($review['user'])) {
                            $review['user']['first_Name'] = 'Anonymous';
                            $review['user']['last_Name'] = null;
                            $result['data'][$key] = $review;
                        }
                    }
                }
            }
        }

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

    public function getReviewById($reviewId, Request $request)
    {

        // with
        $reviewValue = ReviewNew::with([
            "value.question",
            "value.tags",
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
        $numericIds = array_values(array_unique(array_map('intval', array_filter($idArray, fn($id) => ctype_digit((string) $id) && (int) $id > 0))));

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

    /**
     * @OA\Get(
     *      path="/v1.0/reviews",
     *      operationId="getAllReviews",
     *      tags={"review_management"},
     *      security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Get all reviews",
     *      description="Retrieve all reviews across all businesses with filtering and pagination - Admin only",
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          required=false,
     *          description="Page number"
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="Items per page"
     *      ),
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          required=false,
     *          description="Sort option",
     *          @OA\Schema(
     *              type="string",
     *              enum={"newest", "oldest", "highest_rating", "lowest_rating"}
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          required=false,
     *          description="Filter reviews from this date"
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          required=false,
     *          description="Filter reviews until this date"
     *      ),
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Time period filter (e.g., last_7_days, last_30_days, last_90_days, this_month, last_month, this_year)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="last_30_days"
     *      ),
     *      @OA\Parameter(
     *          name="sentiment_score",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by sentiment score. ref : compliment=positive, complaint=negative",
     *          @OA\Schema(
     *              type="string",
     *              enum={ "positive", "neutral", "negative"}
     *          ),
     *          example="positive"
     *      ),
     *      @OA\Parameter(
     *          name="meets_threshold",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by threshold status (1 for csat score reviews that meet threshold, 0 for flagged reviews that don't meet threshold)",
     *          @OA\Schema(
     *              type="integer",
     *              enum={0, 1}
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="staff_id",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by specific staff ID",
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="staff_ids",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by multiple staff IDs (comma-separated, e.g., 1,2,3)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="branch_ids",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by multiple branch IDs (comma-separated, e.g., 1,2,3)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="has_staff",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by presence of staff reference (1 = has staff, 0 = no staff)",
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="is_overall",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by overall status (1 = overall reviews, 0 = non-overall reviews, not specified = all reviews)",
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="topics",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by topic main_category name (case-sensitive exact match)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="Food Quality"
     *      ),
     *      @OA\Parameter(
     *          name="review_ids",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by specific review IDs (comma-separated, e.g., 1,2,3)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="is_voice_review",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by voice review status (1 = voice reviews, 0 = text reviews)",
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="question_category_id",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by parent question category ID",
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="question_sub_category_id",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by question sub-category ID",
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *          example=5
     *      ),
     *      @OA\Parameter(
     *          name="business_area_id",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by business area ID",
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="business_service_id",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by business service ID",
     *          @OA\Schema(
     *              type="integer"
     *          ),
     *          example=3
     *      ),
     *      @OA\Parameter(
     *          name="survey_ids",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by specific survey IDs (comma-separated, e.g., 1,2,3)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="tag_ids",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by specific tag IDs associated with review values (comma-separated, e.g., 1,2,3)",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="rating",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by specific calculated_rating (e.g., 1,2,3,4,5). If 1, it will fetch reviews with calculated_rating in range 1: 0 to 1.9 and 2: 2 to 2.9 and 3: 3 to 3.9 and 4: 4 to 4.9 and 5: 5",
     *          @OA\Schema(
     *              type="string"
     *          ),
     *          example="1"
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
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Admin access required",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function getAllReviews(Request $request)
    {
        // Check if user is admin/superadmin
        // if (!$request->user()->hasRole(['superadmin', 'admin'])) {
        //     return response([
        //         "success" => false,
        //         "message" => "Access denied. Admin privileges required."
        //     ], 403);
        // }

        $businessId = $request->user()->business_id;



        $query = ReviewNew::with([
            "value.question",
            "value.tags",
            "value",
            "user",
            "guest_user",
            "survey",
        ])->where("business_id", $businessId)
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->withCalculatedRating();

        // Sorting logic
        $sortBy = $request->get('sort_by', 'newest');

        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'highest_rating':
                // IS NULL returns 1 for null, 0 for not null.
                // Ordering by it first ensures nulls go to the bottom.
                $query->orderByRaw('ISNULL(calculated_rating) ASC, calculated_rating DESC');
                break;
            case 'lowest_rating':
                $query->orderByRaw('ISNULL(calculated_rating) ASC, calculated_rating ASC');
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
}
