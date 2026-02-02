<?php

namespace App\Http\Controllers;

use App\Http\Requests\SurveyCreateRequest;
use App\Http\Requests\SurveyUpdateRequest;
use App\Models\Business;
use App\Models\Survey;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SurveyController extends Controller
{

    /**
     *
     * @OA\Post(
     *      path="/v1.0/surveys",
     *      operationId="createSurvey",
     *      tags={"survey_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store survey",
     *      description="This method is to store survey",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name"},
     *    @OA\Property(property="name", type="string", format="string",example="name"),
     *    @OA\Property(property="show_in_guest_user", type="boolean", format="boolean",example="true"),
     *    @OA\Property(property="show_in_user", type="boolean", format="boolean",example="true"),
     *    @OA\Property(property="survey_questions", type="string", format="array",example="[1,2,3]"),

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
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createSurvey(SurveyCreateRequest $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can create surveys.');
            }

            return DB::transaction(function () use ($request, $user) {
                $payload_data = $request->validated();

                // ==================== BUSINESS VALIDATION ====================
                if (!$user->business_id) {
                    throw new AccessDeniedHttpException('No business associated with your account.');
                }

                $payload_data["business_id"] = $user->business_id;

                // ==================== CREATE SURVEY ====================
                $survey = Survey::create($payload_data);

                $survey->questions()->sync($payload_data["survey_questions"]);
                $survey->business_services()->sync($payload_data["business_service_ids"]);

                return response($survey, 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @OA\Put(
     *      path="/v1.0/surveys",
     *      operationId="updateSurvey",
     *      tags={"survey_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update expense type",
     *      description="This method is to update expense type",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name"},
     *    @OA\Property(property="id", type="integer", format="integer",example="1"),
     *    @OA\Property(property="name", type="string", format="string",example="name"),
     *    @OA\Property(property="show_in_guest_user", type="boolean", format="boolean",example="true"),
     *    @OA\Property(property="show_in_user", type="boolean", format="boolean",example="true"),
     *    @OA\Property(property="survey_questions", type="string", format="array",example="[1,2,3]"),

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
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function updateSurvey(SurveyUpdateRequest $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can update surveys.');
            }

            return DB::transaction(function () use ($request, $user) {
                $request_data = $request->validated();

                // ==================== FIND SURVEY ====================
                $survey = Survey::findOrFail($request_data["id"]);

                // ==================== BUSINESS OWNERSHIP VALIDATION ====================
                if ($survey->business_id !== $user->business_id) {
                    throw new AccessDeniedHttpException('This survey belongs to another business.');
                }

                // ==================== UPDATE SURVEY ====================
                $survey->fill($request_data);
                $survey->save();

                $survey->questions()->sync($request_data["survey_questions"]);
                $survey->business_services()->sync($request_data["business_service_ids"]);

                return response($survey, 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }




    /**
     * @OA\Post(
     *      path="/v1.0/surveys/ordering",
     *      operationId="orderSurveys",
     *      tags={"survey_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Order surveys by specific sequence",
     *      description="Update the display order of surveys using order numbers",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"surveys"},
     *              @OA\Property(
     *                  property="surveys",
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

    public function orderSurveys(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {

                $request->validate([
                    'surveys' => 'required|array',
                    'surveys.*.id' => 'required|integer|exists:surveys,id',
                    'surveys.*.order_no' => 'required|integer|min:1'
                ]);

                foreach ($request->surveys as $survey) {
                    Survey::where('id', $survey['id'])
                        ->update([
                            'order_no' => $survey['order_no']
                        ]);
                }

                return response()->json([
                    'message' => 'Order updated successfully',
                    'ok' => true
                ], 200);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Get(
     *   path="/v1.0/client/surveys/{id}",
     *   operationId="getSurveyByIdClient",
     *   tags={"survey_management"},
     *   security={{"bearerAuth":{}}},
     *   summary="Get surveys (filters + pagination + sorting)",
     *   description="Returns surveys for a business. Supports search, date range, active status, pagination, and sorting.",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description=" ID",
     *     @OA\Schema(type="integer", example=6)
     *   ),
     *

     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="surveys retrieved successfully"),
     *
     *       @OA\Property(
     *         property="meta",
     *         type="object",
     *         description="Pagination metadata",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="per_page", type="integer", example=10),
     *         @OA\Property(property="total", type="integer", example=57),
     *         @OA\Property(property="last_page", type="integer", example=6)
     *       ),
     *
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         description="List of surveys",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="business_id", type="integer", example=6),
     *           @OA\Property(property="name", type="string", example="Customer Feedback"),
     *           @OA\Property(property="is_active", type="boolean", example=true),
     *           @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-09T10:20:30Z"),
     *           @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-09T10:20:30Z")
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Unauthenticated")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Unprocessable Content",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation error")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Bad Request")
     *     )
     *   )
     * )
     */

    public function getSurveyByIdClient($id, Request $request)
    {
        try {
            // GET ALL SURVEYS WHICH BELONGS
            $survey = Survey::with('questions.question_stars.star.star_tags.tag', "business_services.business_areas")
                ->withCount([
                    "reviews"
                ])
                ->where([
                    "id" => $id
                ])->first();

            // Check if survey exists
            if (!$survey) {
                return response()->json([
                    "success" => false,
                    "message" => "Survey not found with ID: {$id}"
                ], 404);
            }

            $questions = $survey->questions;
            $data = json_decode(json_encode($questions), true);

            foreach ($questions as $key1 => $question) {
                $data[$key1]["stars"] = []; // Initialize stars array

                foreach ($question->question_stars as $key2 => $questionStar) {
                    $data[$key1]["stars"][$key2] = json_decode(json_encode($questionStar->star), true);
                    $data[$key1]["stars"][$key2]["tags"] = [];

                    foreach ($questionStar->star->star_tags as $key3 => $starTag) {
                        if ($starTag->question_id == $question->id) {
                            array_push($data[$key1]["stars"][$key2]["tags"], json_decode(json_encode($starTag->tag), true));
                        }
                    }
                }
                // Remove the original question_stars to avoid duplication
                unset($data[$key1]['question_stars']);
            }

            // Convert survey to array and replace questions
            $surveyArray = $survey->toArray();
            $surveyArray['questions'] = $data;

            return response()->json([
                "success" => true,
                "message" => "survey retrieved successfully",
                "data" => $surveyArray
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * @OA\Get(
     *   path="/v1.0/surveys/{business_id}",
     *   operationId="getAllSurveys",
     *   tags={"survey_management"},
     *   security={{"bearerAuth":{}}},
     *   summary="Get surveys (filters + pagination + sorting)",
     *   description="Returns surveys for a business. Supports search, date range, active status, pagination, and sorting.",
     *
     *   @OA\Parameter(
     *     name="business_id",
     *     in="path",
     *     required=true,
     *     description="Business ID",
     *     @OA\Schema(type="integer", example=6)
     *   ),
     *
     *   @OA\Parameter(
     *     name="search_key",
     *     in="query",
     *     required=false,
     *     description="Search by survey name (LIKE %search_key%)",
     *     @OA\Schema(type="string", example="customer")
     *   ),
     *
     *   @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     required=false,
     *     description="Filter by created_at start date (YYYY-MM-DD)",
     *     @OA\Schema(type="string", format="date", example="2019-06-29")
     *   ),
     *
     *   @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     required=false,
     *     description="Filter by created_at end date (YYYY-MM-DD)",
     *     @OA\Schema(type="string", format="date", example="2019-07-29")
     *   ),
     *
     *   @OA\Parameter(
     *     name="is_active",
     *     in="query",
     *     required=false,
     *     description="Filter by active status",
     *     @OA\Schema(type="boolean", example=true)
     *   ),
     *
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     description="Page number",
     *     @OA\Schema(type="integer", minimum=1, example=1)
     *   ),
     *
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Items per page",
     *     @OA\Schema(type="integer", minimum=1, maximum=200, example=10)
     *   ),
     *
     *   @OA\Parameter(
     *     name="order_by",
     *     in="query",
     *     required=false,
     *     description="Sort column (example: id, name, created_at)",
     *     @OA\Schema(type="string", example="created_at")
     *   ),
     *
     *   @OA\Parameter(
     *     name="sort_order",
     *     in="query",
     *     required=false,
     *     description="Sort direction",
     *     @OA\Schema(type="string", enum={"asc","desc"}, example="desc")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="surveys retrieved successfully"),
     *
     *       @OA\Property(
     *         property="meta",
     *         type="object",
     *         description="Pagination metadata",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="per_page", type="integer", example=10),
     *         @OA\Property(property="total", type="integer", example=57),
     *         @OA\Property(property="last_page", type="integer", example=6)
     *       ),
     *
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         description="List of surveys",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="business_id", type="integer", example=6),
     *           @OA\Property(property="name", type="string", example="Customer Feedback"),
     *           @OA\Property(property="is_active", type="boolean", example=true),
     *           @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-09T10:20:30Z"),
     *           @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-09T10:20:30Z")
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Unauthenticated")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Unprocessable Content",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation error")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Bad Request")
     *     )
     *   )
     * )
     */



    public function getAllSurveys($business_id, Request $request)
    {
        try {
            // ==================== GET AUTHENTICATED USER ====================
            $user = auth()->user();

            // ==================== AUTHORIZATION CHECK ====================
            // Ensure user belongs to the requested business
            if ($user->business_id != $business_id) {
                throw new AuthorizationException('You are not belongs to this business');
            }



            // ==================== VALIDATE BUSINESS EXISTS ====================
            Business::findOrFail($business_id);

            // ==================== BUILD QUERY WITH RELATIONSHIPS ====================
            // Get all surveys belonging to the business with related data
            $query = Survey::with('questions', "business_services.business_areas")
                ->withCount([
                    "reviews", // Count total reviews for each survey
                ])
                ->where([
                    "business_id" => $business_id,
                ])
                ->filter(); // Apply any query filters from request


            // ==================== RETRIEVE PAGINATED DATA ====================
            // Get surveys ordered by order_no with pagination
            $surveys = retrieve_data($query, 'order_no');

            // ==================== RETURN RESPONSE ====================
            return response()->json([
                "success" => true,
                "message" => "surveys retrieved successfully",
                "meta" => $surveys['meta'],
                "data" => $surveys['data']
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/surveys/{business_id}/{perPage}",
     *      operationId="getSurveys",
     *      tags={"survey_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *              @OA\Parameter(
     *         name="business_id",
     *         in="path",
     *         description="business_id",
     *         required=true,
     *  example="6"
     *      ),
     *              @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage",
     *         required=true,
     *  example="6"
     *      ),
     *      * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     *   @OA\Parameter(
     * name="is_active",
     * in="query",
     * description="is_active",
     * required=true,
     * example="is_active"
     * ),
     *      summary="This method is to get expense types ",
     *      description="This method is to get expense types",
     *

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
     *   @OA\JsonContent()
     * ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getSurveys($business_id, $perPage, Request $request)
    {
        try {



            $surveyQuery = Survey::with('questions')
                ->where([
                    "business_id" => $business_id
                ])
                ->orderBy("order_no", "asc");



            $surveys = $surveyQuery->orderByDesc("id")->paginate($perPage);


            return response()->json($surveys, 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     *     @OA\Delete(
     *      path="/v1.0/surveys/{id}",
     *      operationId="deleteSurveyById",
     *      tags={"survey_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to delete fuel station by id",
     *      description="This method is to delete fuel station by id",
     *

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
     *   @OA\JsonContent()
     * ),
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function deleteSurveyById($id, Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can delete surveys.');
            }

            // ==================== FIND SURVEY ====================
            $survey = Survey::findOrFail($id);

            // ==================== BUSINESS OWNERSHIP VALIDATION ====================
            if ($survey->business_id !== $user->business_id) {
                throw new AccessDeniedHttpException('This survey belongs to another business.');
            }

            // ==================== DELETE SURVEY ====================
            $survey->delete();

            return response()->json(["ok" => true], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * @OA\Patch(
     *   path="/v1.0/surveys/{id}/toggle-active",
     *   operationId="toggleSurveyActive",
     *   tags={"survey_management"},
     *   security={{"bearerAuth":{}}},
     *   summary="Toggle survey active status",
     *   description="Toggle the active status of a specific survey.",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Survey ID",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Survey status toggled successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Survey activated successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="business_id", type="integer", example=6),
     *         @OA\Property(property="name", type="string", example="Customer Feedback"),
     *         @OA\Property(property="is_active", type="boolean", example=true),
     *         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-09T10:20:30Z"),
     *         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-09T10:20:30Z")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Unauthenticated")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Survey not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Bad Request")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Unprocessable Content",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation error")
     *     )
     *   )
     * )
     */


    public function toggleSurveyActive($id, Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            // if (!$user->hasRole('business_owner')) {
            //     throw new AccessDeniedHttpException('Only business owners can toggle survey status.');
            // }

            // ==================== FIND SURVEY ====================
            $survey = Survey::findOrFail($id);

            // ==================== BUSINESS OWNERSHIP VALIDATION ====================
            if ($survey->business_id !== $user->business_id) {
                throw new AccessDeniedHttpException('This survey belongs to another business.');
            }

            // ==================== TOGGLE STATUS ====================
            $survey->is_active = !$survey->is_active;
            $survey->save();

            $message = $survey->is_active ? 'Survey activated successfully' : 'Survey deactivated successfully';

            return response()->json([
                "success" => true,
                "message" => $message,
                "data" => $survey
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
