<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionRequest;
use App\Http\Requests\SetOverallQuestionRequest;
use App\Models\Business;
use App\Models\Question;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{

    // ##################################################
    // This method is to get all questions
    // ##################################################



    /**
     * Get all review questions
     *
     * @OA\Get(
     *      path="/v1.0/questions",
     *      operationId="getAllQuestions",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Retrieve review questions",
     *      description="Fetches review questions based on user role and filters.\n\n• Superadmin: Gets all questions (default + all businesses)\n• Business Owner: Gets default questions + their own business questions\n• Use query parameters to filter results (all optional)",
     *
     *      @OA\Parameter(
     *          name="business_id",
     *          in="query",
     *          required=false,
     *          description="Filter questions by business ID. Ignored for superadmin.",
     *          example=3,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="is_active",
     *          in="query",
     *          required=false,
     *          description="Filter by active status",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="is_user",
     *          in="query",
     *          required=false,
     *          description="Show only questions visible to registered users (show_in_user = true)",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="exclude_user",
     *          in="query",
     *          required=false,
     *          description="Exclude questions visible to registered users (show_in_user = false)",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="is_guest_user",
     *          in="query",
     *          required=false,
     *          description="Show only questions visible to guest users",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="exclude_guest_user",
     *          in="query",
     *          required=false,
     *          description="Exclude questions visible to guest users",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="survey_id",
     *          in="query",
     *          required=false,
     *          description="Filter questions attached to a specific survey",
     *          example=7,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="survey_name",
     *          in="query",
     *          required=false,
     *          description="Filter questions by survey name (partial match)",
     *          example="Post-Service Feedback",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="ids",
     *          in="query",
     *          required=false,
     *          description="Comma-separated list of question IDs",
     *          example="1,5,8",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Questions retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Questions retrieved successfully."),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=5),
     *                      @OA\Property(property="question", type="string", example="How likely are you to recommend us?"),
     *                      @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star"),
     *                      @OA\Property(property="is_active", type="boolean", example=true),
     *                      @OA\Property(property="is_default", type="boolean", example=false),
     *                      @OA\Property(property="show_in_user", type="boolean", example=true),
     *                      @OA\Property(property="show_in_guest_user", type="boolean", example=false),
     *                      @OA\Property(property="business_id", type="integer", nullable=true, example=3),
     *                      @OA\Property(
     *                          property="surveys",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="id", type="integer", example=2),
     *                              @OA\Property(property="name", type="string", example="Checkout Survey"),
     *                              @OA\Property(property="order_no", type="integer", example=1)
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=404, description="Business not found")
     * )
     */
    public function getAllQuestions(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Question::with(['surveys' => fn($q) => $q->select('surveys.id', 'name', 'order_no')]);

        if ($user->hasRole('superadmin')) {
            // Superadmin sees ALL questions
            // No business_id or is_default filter
        } else {
            // Regular user: default questions + their business questions
            $businessId = $request->filled('business_id') ? $request->business_id : $user->business?->id;

            $business = Business::find($businessId);

            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business not found.'
                ], 404);
            }

            $query->where(function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->orWhere('is_default', true);
            });
        }

        // Apply all filters
        $query->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('is_user'), fn($q) => $q->where('show_in_user', true))
            ->when($request->boolean('exclude_user'), fn($q) => $q->where('show_in_user', false))
            ->when($request->boolean('is_guest_user'), fn($q) => $q->where('show_in_guest_user', true))
            ->when($request->boolean('exclude_guest_user'), fn($q) => $q->where('show_in_guest_user', false))
            ->when($request->filled('survey_id'), fn($q) => $q->whereHas('surveys', fn($sq) => $sq->where('id', $request->survey_id)))
            ->when($request->filled('survey_name'), fn($q) => $q->whereHas('surveys', fn($sq) => $sq->where('name', 'like', "%{$request->survey_name}%")))
            ->when($request->filled('ids'), fn($q) => $q->whereIn('id', array_filter(explode(',', $request->ids), 'is_numeric')));

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully.',
            'data'    => $query->get()
        ]);
    }

    // ##################################################
    // This method is to get question by id
    // ##################################################
    /**
     * Get a specific review question by ID
     *
     * @OA\Get(
     *      path="/v1.0/questions/{id}",
     *      operationId="questionById",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Get a specific review question",
     *      description="Retrieves a single review question by ID. Access depends on user role.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="Question ID",
     *          example=1
     *      ),
     *
     *      @OA\Response(response=200, description="Question retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Question retrieved successfully."),
     *              @OA\Property(property="data", ref="#/components/schemas/Question")
     *          )
     *      ),
     *      @OA\Response(response=404, description="Question not found"),
     *      @OA\Response(response=403, description="Forbidden - No access to this question"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function questionById(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $question = Question::with(['surveys' => fn($q) => $q->select('surveys.id', 'name', 'order_no')])->find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.'
            ], 404);
        }

        // Check permissions
        if (!$user->hasRole('superadmin')) {
            // Business owner: can only access default questions or their own business questions
            if (!$question->is_default && !in_array($question->business_id, $user->businesses()->pluck('id')->toArray())) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this question.'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Question retrieved successfully.',
            'data'    => $question
        ], 200);
    }

    // ##################################################
    // This method is to delete questions
    // ##################################################
    /**
     * Delete review questions
     *
     * @OA\Delete(
     *      path="/v1.0/questions/{ids}",
     *      operationId="deleteQuestion",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Delete review questions",
     *      description="Deletes one or more review questions. IDs can be comma-separated. Superadmin can delete default questions. Business owners can only delete their own business questions.",
     *
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          required=true,
     *          description="Question IDs to delete (comma-separated for multiple)",
     *          example="1,2,3"
     *      ),
     *
     *      @OA\Response(response=200, description="Questions deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Questions deleted successfully."),
     *              @OA\Property(property="deleted_count", type="integer", example=2)
     *          )
     *      ),
     *      @OA\Response(response=400, description="Bad request - Invalid IDs or no access to questions"),
     *      @OA\Response(response=404, description="One or more questions not found"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function deleteQuestion(string $ids, Request $request): JsonResponse
    {
        $user = $request->user();

        // Parse IDs (comma-separated)
        $idArray = array_filter(array_map('intval', explode(',', $ids)));

        if (empty($idArray)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid question IDs provided.'
            ], 400);
        }

        // Get all questions to verify they exist and check permissions
        $questions = Question::whereIn('id', $idArray)->get();

        // Check if all requested IDs exist
        $foundIds = $questions->pluck('id')->toArray();
        $missingIds = array_diff($idArray, $foundIds);

        if (!empty($missingIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Questions not found: ' . implode(', ', $missingIds)
            ], 404);
        }

        // Check permissions for each question
        $unauthorizedIds = [];
        foreach ($questions as $question) {
            if (!$user->hasRole('superadmin')) {
                // Business owner: can only delete their own business questions (not default questions)
                if ($question->is_default || !in_array($question->business_id, $user->businesses()->pluck('id')->toArray())) {
                    $unauthorizedIds[] = $question->id;
                }
            }
        }

        if (!empty($unauthorizedIds)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete questions: ' . implode(', ', $unauthorizedIds)
            ], 403);
        }

        // Delete the questions
        $deletedCount = Question::whereIn('id', $idArray)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Questions deleted successfully.',
            'deleted_count' => $deletedCount
        ], 200);
    }

    // ##################################################
    // This method is to get all questions for client
    // ##################################################
    /**
     * Get all questions for a specific business (Client)
     *
     * @OA\Get(
     *      path="/v1.0/client/questions/{business_id}",
     *      operationId="getAllQuestionClient",
     *      tags={"review.question_management.client"},
     *      summary="Get all questions for a business (client-facing)",
     *      description="Retrieves all active questions for a specific business. Supports filtering by survey and overall flag. Includes star ratings and associated tags.",
     *
     *      @OA\Parameter(
     *          name="business_id",
     *          in="path",
     *          required=true,
     *          description="Business ID",
     *          example=1,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="is_active",
     *          in="query",
     *          required=false,
     *          description="Filter by active status",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="is_overall",
     *          in="query",
     *          required=false,
     *          description="Filter by overall questions only",
     *          example=true,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="survey_id",
     *          in="query",
     *          required=false,
     *          description="Filter questions by specific survey",
     *          example=5,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Questions retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Questions retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="question", type="string", example="How was your experience today?"),
     *                      @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star"),
     *                      @OA\Property(property="is_active", type="boolean", example=true),
     *                      @OA\Property(property="is_overall", type="boolean", example=false),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(
     *                          property="stars",
     *                          type="array",
     *                          description="Rating options with tags (e.g. for star type questions)",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="id", type="integer", example=1),
     *                              @OA\Property(property="name", type="string", example="Excellent"),
     *                              @OA\Property(property="value", type="integer", example=5),
     *                              @OA\Property(
     *                                  property="tags",
     *                                  type="array",
     *                                  @OA\Items(
     *                                      type="object",
     *                                      @OA\Property(property="id", type="integer", example=1),
     *                                      @OA\Property(property="name", type="string", example="Cleanliness")
     *                                  )
     *                              )
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found or invalid survey_id")
     *          )
     *      ),
     *
     *      @OA\Response(response=400, description="Bad request (invalid parameters)"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getAllQuestionClient(Request $request, int $business_id)
    {
        $business = Business::where(["id" => $business_id])->first();
        if (!$business) {
            return response()->json([
                "status" => false,
                "message" => "No Business Found"
            ], 404);
        }

        // Validate survey_id if provided
        if ($request->filled('survey_id')) {
            $survey = Survey::with('questions')->find($request->survey_id);
            if (!$survey) {
                return response()->json([
                    "status" => false,
                    "message" => 'Survey not found: ' . $request->survey_id
                ], 404);
            }
        }

        $query = Question::with(['question_stars.star.star_tags.tag'])
            ->when($request->filled('business_id'), function ($q) use ($request, $business_id) {
                $q->where(function ($q2) use ($business_id) {
                    $q2->where('questions.business_id', $business_id)
                        ->orWhere('questions.is_default', 1);
                });
            }, function ($q) use ($business_id) {
                // Default to the provided business_id if none in request
                $q->where(function ($q2) use ($business_id) {
                    $q2->where('questions.business_id', $business_id)
                        ->orWhere('questions.is_default', 1);
                });
            })
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

        $questions = $query->get();

        $data = json_decode(json_encode($questions), true);
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

        return response()->json([
            "status" => true,
            "message" => "Questions retrieved successfully",
            "data" => $data
        ], 200);
    }

    // ##################################################
    // This method is to store question
    // ##################################################
    /**
     * Store a new review question
     *
     * @OA\Post(
     *      path="/v1.0/questions",
     *      operationId="createQuestion",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Store a new review question",
     *      description="Creates a new review question. Superadmin creates default questions (business_id = null). Regular users can only create for their own business.",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"question", "is_active"},
     *              @OA\Property(property="question", type="string", example="How was your experience?"),
     *              @OA\Property(property="business_id", type="integer", nullable=true, example=1, description="Required for non-superadmin"),
     *              @OA\Property(property="is_active", type="boolean", example=true),
     *              @OA\Property(property="show_in_guest_user", type="boolean", example=true),
     *              @OA\Property(property="show_in_user", type="boolean", example=true),
     *              @OA\Property(property="survey_name", type="string", nullable=true, example="Post-Service Survey"),
     *              @OA\Property(property="survey_id", type="integer", nullable=true, example=5),
     *              @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star"),
     *              @OA\Property(property="is_overall", type="boolean", example=false),
     *          ),
     *      ),
     *
     *      @OA\Response(response=201, description="Question created successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Question")
     *      ),
     *      @OA\Response(response=400, description="Bad request / Business not owned / Questions disabled"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function createQuestion(QuestionRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validated();

        // Handle superadmin: create default question
        if ($user->hasRole('superadmin')) {
            $data['is_default'] = true;
            $data['business_id'] = null;
        } else {
            // Regular user: must own the business
            $business = Business::where([
                'id'       => $data['business_id'] ?? null,
                'OwnerID'  => $user->id
            ])->first();

            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this business or business not found.'
                ], 400);
            }



            // Ensure business_id is set for non-superadmin
            $data['business_id'] = $business->id;
        }

        // Remove survey_name if not needed (optional cleanup)
        if (empty($data['survey_name'])) {
            unset($data['survey_name']);
        }

        $question = Question::create($data);

        // Attach to survey if survey_id is provided
        if ($request->filled('survey_id')) {
            SurveyQuestion::create([
                'survey_id'    => $request->survey_id,
                'question_id'  => $question->id,
            ]);
        }

        $question->info = "Supported types: " . implode(", ", array_values(Question::QUESTION_TYPES));

        return response()->json([
            'success' => true,
            'message' => 'Question created successfully.',
            'data'    => $question
        ], 201);
    }

    // ##################################################
    // This method is to update question
    // ##################################################
    /**
     * Update an existing review question
     *
     * @OA\Patch(
     *      path="/v1.0/questions/{id}",
     *      operationId="updatedQuestion",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Update an existing review question",
     *      description="Updates a review question. Superadmin can update default questions. Regular users can only update questions for their own business.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="Question ID",
     *          example=1
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="question", type="string", example="How was your experience?"),
     *              @OA\Property(property="business_id", type="integer", nullable=true, example=1, description="Required for non-superadmin"),
     *              @OA\Property(property="is_active", type="boolean", example=true),
     *              @OA\Property(property="show_in_guest_user", type="boolean", example=true),
     *              @OA\Property(property="show_in_user", type="boolean", example=true),
     *              @OA\Property(property="survey_name", type="string", nullable=true, example="Post-Service Survey"),
     *              @OA\Property(property="survey_id", type="integer", nullable=true, example=5),
     *              @OA\Property(property="type", type="string", enum={"star","emoji","numbers","heart"}, example="star"),
     *              @OA\Property(property="is_overall", type="boolean", example=false),
     *              @OA\Property(property="is_staff", type="boolean", example=false),
     *          ),
     *      ),
     *
     *      @OA\Response(response=200, description="Question updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Question")
     *      ),
     *      @OA\Response(response=400, description="Bad request / Business not owned / Questions disabled"),
     *      @OA\Response(response=404, description="Question not found"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updatedQuestion(int $id, QuestionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Find the question
        $question = Question::find($id);
        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.'
            ], 404);
        }

        $data = $request->validated();

        // Handle superadmin: can update default questions
        if ($user->hasRole('superadmin')) {
            // Superadmin can update default questions, keep business_id as null
            if ($question->is_default) {
                $data['business_id'] = null;
            }
        } else {
            // Regular user: check if they own the business
            $businessId = $data['business_id'] ?? $question->business_id;
            $business = Business::where([
                'id'       => $businessId,
                'OwnerID'  => $user->id
            ])->first();

            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this business or business not found.'
                ], 400);
            }



            // Ensure business_id is set for non-superadmin
            $data['business_id'] = $business->id;
        }

        // Remove survey_name if not needed (optional cleanup)
        if (empty($data['survey_name'])) {
            unset($data['survey_name']);
        }

        // Update the question
        $question->update($data);

        // Handle survey attachment if survey_id is provided
        if ($request->filled('survey_id')) {
            // Remove existing survey attachment
            SurveyQuestion::where('question_id', $question->id)->delete();

            // Add new survey attachment
            SurveyQuestion::create([
                'survey_id'    => $request->survey_id,
                'question_id'  => $question->id,
            ]);
        }

        $question->info = "Supported types: " . implode(", ", array_values(Question::QUESTION_TYPES));

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully.',
            'data'    => $question
        ], 200);
    }

    /**
     *
     * @OA\patch(
     *      path="/v1.0/questions/set-overall",
     *      operationId="setOverallQuestions",
     *      tags={"review.question_management"},
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
     *                  @OA\Property(property="survey_name", type="string", nullable=true, example="Customer Satisfaction")
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
    public function setOverallQuestions(SetOverallQuestionRequest $request): JsonResponse
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

    /**
     * Update question active state
     *
     * @OA\Patch(
     *      path="/v1.0/questions/toggle",
     *      operationId="toggleQuestionActivation",
     *      tags={"review.question_management"},
     *      security={{"bearerAuth":{}}},
     *      summary="Toggle question active status",
     *      description="Updates the `is_active` status of a question. Only super admins can modify default questions.",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"id","is_active"},
     *              @OA\Property(property="id", type="integer", example=5, description="Question ID"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="New active state")
     *          )
     *      ),
     *
     *      @OA\Response(response=200, description="Question status updated successfully",
     *          @OA\JsonContent(ref="#/components/schemas/Question")
     *      ),
     *      @OA\Response(response=400, description="Bad request"),
     *      @OA\Response(response=403, description="Forbidden - Cannot modify default question"),
     *      @OA\Response(response=404, description="Question not found"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function toggleQuestionActivation(Request $request): JsonResponse
    {
        // Validate input
        $request->validate([
            'id'        => 'required|integer|exists:questions,id',
            'is_active' => 'required|boolean',
        ]);


        $question = Question::findOrFail($request->id);

        // Only superadmin can modify default questions
        if ($question->is_default && !$request->user()->hasRole('superadmin')) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'You are not authorized to modify default questions.'
                ],
                403
            );
        }

        // Non-default questions: must belong to user's business
        if (!$question->is_default && $question->business_id) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'You are not authorized to modify questions for this business.'
                ],
                403
            );
        }

        // Update the is_active field
        $question->update([
            'is_active' => $request->boolean('is_active')
        ]);

        // Reload to get fresh data (optional, but clean)
        $question->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Question active state updated successfully.',
            'data' => $question
        ], 200);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/questions/ordering",
     *      operationId="displayQuestionOrder",
     *      tags={"review.question_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Order questions by specific sequence",
     *      description="Update the display order of questions using order numbers",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"questions"},
     *              @OA\Property(
     *                  property="questions",
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
     *              @OA\Property(property="message", type="string", example="Question updated successfully"),
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
    public function displayQuestionOrder(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                // if (!$request->user()->hasPermissionTo('review_update')) {
                //     return response()->json([
                //         "message" => "You can not perform this action"
                //     ], 403);
                // }

                $payload_request = $request->validate([
                    'questions' => 'required|array',
                    'questions.*.id' => 'required|integer|exists:questions,id',
                    'questions.*.order_no' => 'required|integer|min:0'
                ]);

                foreach ($payload_request['questions'] as $question) {
                    $item = Question::find($question['id']);
                    $item->update([
                        'order_no' => $question['order_no']
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Question updated successfully',
                    'data' => true
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error updating order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
