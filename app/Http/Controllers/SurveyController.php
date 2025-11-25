<?php

namespace App\Http\Controllers;

use App\Http\Requests\SurveyCreateRequest;
use App\Http\Requests\SurveyUpdateRequest;

use App\Http\Utils\ErrorUtil;
use App\Models\Business;
use App\Models\Survey;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    use ErrorUtil;

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

            return DB::transaction(function () use ($request) {

                $insertable_data = $request->validated();

                $business = Business::where([
                    "OwnerID" => auth()->user()->id
                ])
                    ->first();
                $insertable_data["business_id"] = $business->id;

                $survey =  Survey::create($insertable_data);


                $survey->questions()->sync($insertable_data["survey_questions"]);



                return response($survey, 201);
            });
        } catch (Exception $e) {
            return response()->json([
                "message" => "some thing went wrong",
                "original_message" => $e->getMessage()
            ], 404);
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
     *   *    @OA\Property(property="survey_questions", type="string", format="array",example="[1,2,3]"),

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

    public function updateSurvey(SurveyUpdateRequest $request)
    {
        try {

            return  DB::transaction(function () use ($request) {

                $request_data = $request->validated();

                $survey =  Survey::where([
                    "id" => $request_data["id"]
                ])->first();


                if (!$survey) {
                    return response()->json([
                        "message" => "no survey type found"
                    ], 404);
                }

                $survey->fill($request_data);
                $survey->save();

                $survey->questions()->sync($request_data["survey_questions"]);

                return response($survey, 201);
            });
        } catch (Exception $e) {
            return response()->json([
                "message" => "some thing went wrong",
                "original_message" => $e->getMessage()
            ], 404);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/surveys/{business_id}",
     *      operationId="getAllSurveys",
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

    public function getAllSurveys($business_id, Request $request)
    {
        try {


            $surveyQuery =  Survey::with('questions')
                ->where([
                    "business_id" => $business_id
                ]);

            $surveys = $surveyQuery->orderByDesc("id")->get();


            return response()->json($surveys, 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => "some thing went wrong",
                "original_message" => $e->getMessage()
            ], 404);
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
     * *  @OA\Parameter(
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

    public function getSurveys($business_id, $perPage, Request $request)
    {
        try {



            $surveyQuery =  Survey::with('questions')
                ->where([
                    "business_id" => $business_id
                ]);


            $surveys = $surveyQuery->orderByDesc("id")->paginate($perPage);


            return response()->json($surveys, 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => "some thing went wrong",
                "original_message" => $e->getMessage()
            ], 404);
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

    public function deleteSurveyById($id, Request $request)
    {

        try {

            Survey::where([
                "id" => $id
            ])
                ->delete();

            return response()->json(["ok" => true], 200);
        } catch (Exception $e) {
            return response()->json([
                "message" => "some thing went wrong",
                "original_message" => $e->getMessage()
            ], 404);
        }
    }
}
