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

                // if (!$request->user()->hasPermissionTo('survey_update')) {
                //     return response()->json([
                //         "message" => "You can not perform this action"
                //     ], 403);
                // }

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
            return response()->json([
                "message" => "something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
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

            $surveys = $surveyQuery
                ->orderBy("order_no", "asc")
                ->get();


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
                ])
                ->orderBy("order_no", "asc");



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
