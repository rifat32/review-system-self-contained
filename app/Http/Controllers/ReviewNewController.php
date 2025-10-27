<?php

namespace App\Http\Controllers;

use App\Models\GuestUser;
use App\Models\Question;
use App\Models\QusetionStar;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\StarTag;
use App\Models\StarTagQuestion;
use App\Models\Tag;
use App\Models\TagReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;

class ReviewNewController extends Controller
{
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
     *      path="/review-new/getvalues/{businessId}/{rate}",
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



    public function getReviewValues($businessId, $rate, Request $request)
    {
        // with
        $reviewValues = ReviewValue::where([
            "business_id" => $businessId,
            "rate" => $rate,

        ])
            ->get();


        return response($reviewValues, 200);
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
    public function  getAverage($businessId, $start, $end, Request $request)
    {
        // with
        $reviews = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween('created_at', [$start, $end])

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
                    $data[$review->question->name]["one"] += 1;
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
                    $data[$review->question->question]["five"] += 1;
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
            ->with("business","value")
            ->whereBetween('created_at', [$start, $end])
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
            ->whereBetween('created_at', [$start, $end])
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
    
     /**
        *
     * @OA\Post(
     *      path="/review-new/{businessId}",
     *      operationId="storeReview",
     *      tags={"review"},
     *    *  @OA\Parameter(
* name="businessId",
* in="path",
* description="businessId",
* required=true,
* example="1"
* ),
*
  *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store review",
     *      description="This method is to store review",
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"description","rate","comment","values"},
     *
     *             @OA\Property(property="description", type="string", format="string",example="test"),
     *            @OA\Property(property="rate", type="string", format="string",example="2.5"),
     *              @OA\Property(property="comment", type="string", format="string",example="not good"),
     *
     *
     *    *  @OA\Property(property="values", type="string", format="array",example={

     *  {"question_id":1,"tag_id":2,"star_id":1},
    *  {"question_id":2,"tag_id":1,"star_id":4},

     * }
     *
     * ),
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

    public function storeReview($businessId,  Request $request)
    {

            $review = [
                'description' => $request["description"],
                'business_id' => $businessId,
                'rate' => $request["rate"],
                'user_id' => $request->user()->id,
                'comment' => $request["comment"],
            //     'question_id' => $singleReview["question_id"],
            // 'tag_id' => $request->tag_id,
            // 'star_id' => $request->star_id,
            ];

            $createdReview =   ReviewNew::create($review);

            $rate = 0;
            $questionCount = 0;
            $previousQuestionId = NULL;
            foreach ($request["values"] as $value) {
               if(!$previousQuestionId) {
                $previousQuestionId = $value["question_id"];
                $rate += $value["star_id"];
               }else {

                if($value["question_id"] != $previousQuestionId) {
                    $rate += $value["star_id"];
                    $previousQuestionId = $value["question_id"];
                    $questionCount += 1;
                }

               }

               $createdReview->rate =  $rate;
               $createdReview->save();
                $value["review_id"] = $createdReview->id;
                // $value["question_id"] = $createdReview->question_id;
                // $value["tag_id"] = $createdReview->tag_id;
                // $value["star_id"] = $createdReview->star_id;
                ReviewValueNew::create($value);
            }


        return response(["message" => "created successfully"], 201);
    }
     // ##################################################
    // This method is to store review
    // ##################################################
     /**
        *
     * @OA\Post(
     *      path="/review-new-guest/{businessId}",
     *      operationId="storeReviewByGuest",
     *      tags={"review"},
     *    *  @OA\Parameter(
* name="businessId",
* in="path",
* description="businessId",
* required=true,
* example="1"
* ),
*
  *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store review by guest user",
     *      description="This method is to store review by guest user",
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"guest_full_name","guest_phone","description","rate","comment","values"},
     *
     * *             @OA\Property(property="guest_full_name", type="string", format="string",example="Rifat"),
     * *             @OA\Property(property="guest_phone", type="string", format="string",example="0177"),
     *             @OA\Property(property="description", type="string", format="string",example="test"),
     *            @OA\Property(property="rate", type="string", format="string",example="2.5"),
     *              @OA\Property(property="comment", type="string", format="string",example="not good"),
     *
     *
     *    *  @OA\Property(property="values", type="string", format="array",example={

     *  {"question_id":1,"tag_id":2,"star_id":1},
    *  {"question_id":2,"tag_id":1,"star_id":4},

     * }
     *
     * ),
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

    public function storeReviewByGuest($businessId,  Request $request)
    {

            $guestData = [
                'full_name' => $request["guest_full_name"],
                'phone' => $request["guest_phone"],
            ];

            $guest = GuestUser::create($guestData);
            $review = [
                'description' => $request["description"],
                'business_id' => $businessId,
                'rate' => $request["rate"],
                'guest_id' => $guest->id,
                'comment' => $request["comment"],

            ];
            $createdReview =   ReviewNew::create($review);

            $rate = 0;
            $questionCount = 0;
            $previousQuestionId = NULL;
            foreach ($request["values"] as $value) {
               if(!$previousQuestionId) {
                $previousQuestionId = $value["question_id"];
                $rate += $value["star_id"];
               }else {

                if($value["question_id"] != $previousQuestionId) {
                    $rate += $value["star_id"];
                    $previousQuestionId = $value["question_id"];
                    $questionCount += 1;
                }

               }

               $createdReview->rate =  $rate;
               $createdReview->save();
                $value["review_id"] = $createdReview->id;
                // $value["question_id"] = $createdReview->question_id;
                // $value["tag_id"] = $createdReview->tag_id;
                // $value["star_id"] = $createdReview->star_id;
                ReviewValueNew::create($value);
            }


        return response(["message" => "created successfully"], 201);
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
            'type' => !empty($request->type)?$request->type:"star"
        ];
        if ($request->user()->hasRole("superadmin")) {
            $question["is_default"] = true;
            $question["business_id"] = NULL;
        } else {

            $business =    Business::where(["id" => $request->business_id,"OwnerID" => $request->user()->id])->first();

            if(!$business){
                return response()->json(["message" => "No Business Found"],400);
            }
            if ($business->enable_question == true) {
                return response()->json(["message" => "question is enabled"],400);
            }
        }



        $createdQuestion =    Question::create($question);
        $createdQuestion->info = "supported value is of type is 'star','emoji','numbers','heart'";

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
            "is_active"=>$request->is_active,
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
            "is_active"=>$request->is_active,
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
     *      path="/review-new/get/questions",
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
    public function   getQuestion(Request $request)
    {
        $is_dafault = false;
        $businessId = !empty($request->business_id)?$request->business_id:NULL;
        if ($request->user()->hasRole("superadmin")) {

            $is_dafault = true;
            $businessId = NULL;

        }else{
            $business =    Business::where(["id" => $request->business_id])->first();
            if(!$business && !$request->user()->hasRole("superadmin")){
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $is_dafault = true;

            // }
        }


        $query =  Question::where(["business_id" => $businessId,"is_default" => $is_dafault]);


        $questions =  $query->get();


    return response($questions, 200);




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
    public function   getQuestionAllUnauthorized(Request $request)
    {
        $is_dafault = false;

            $business =    Business::where(["id" => $request->business_id])->first();
            if(!$business){
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $query =  Question::where(["is_default" => 1]);
            // }
            // else {
                $query =  Question::where(["business_id" => $request->business_id,"is_default" => 0])
                ->when(request()->filled("is_active"), function($query) {
                    $query->where("questions.is_active",request()->input("is_active"));
                 })

                ;
            // }





        $questions =  $query->get();

    $data =  json_decode(json_encode($questions), true);
    foreach($questions as $key1=>$question){

        foreach($question->question_stars as $key2=>$questionStar){
            $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;


            $data[$key1]["stars"][$key2]["tags"] = [];
            foreach($questionStar->star->star_tags as $key3=>$starTag){
if($starTag->question_id == $question->id) {

    array_push($data[$key1]["stars"][$key2]["tags"],json_decode(json_encode($starTag->tag), true));


}



            }

        }

    }
    return response($data, 200);


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

        }else{
            $business =    Business::where(["id" => $request->business_id])->first();
            if(!$business && !$request->user()->hasRole("superadmin")){
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $is_dafault = true;

            // }
        }


        $query =  Question::where(["business_id" => $request->business_id,"is_default" => $is_dafault]);


        $questions =  $query->get();

    $data =  json_decode(json_encode($questions), true);
    foreach($questions as $key1=>$question){

        foreach($question->question_stars as $key2=>$questionStar){
            $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;


            $data[$key1]["stars"][$key2]["tags"] = [];
            foreach($questionStar->star->star_tags as $key3=>$starTag){
if($starTag->question_id == $question->id) {

    array_push($data[$key1]["stars"][$key2]["tags"],json_decode(json_encode($starTag->tag), true));


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

    public function getQuestionAllReport(Request $request) {


            $business =    Business::where(["id" => $request->business_id])->first();
            if(!$business){
                return response("No Business Found", 404);
            }

        $query =  Question::where(["business_id" => $request->business_id,"is_default" => false]);

        $questions =  $query->get();

        $questionsCount = $query->get()->count();

    $data =  json_decode(json_encode($questions), true);
    foreach($questions as $key1=>$question){

        $tags_rating = [];
       $starCountTotal = 0;
       $starCountTotalTimes = 0;
        foreach($question->question_stars as $key2=>$questionStar){


            $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

            $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "star_id" => $questionStar->star->id,
                "review_news.guest_id" => NULL

                ]
            );
            if(!empty($request->start_date) && !empty($request->end_date)) {

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
            if($starCountTotalTimes > 0) {
                $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
            }


            foreach($questionStar->star->star_tags as $key3=>$starTag){


         if($starTag->question_id == $question->id) {

            $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "tag_id" => $starTag->tag->id,
                "review_news.guest_id" => NULL
                ]
            );
            if(!empty($request->start_date) && !empty($request->end_date)) {

                $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);

            }

            $starTag->tag->count = $starTag->tag->count->get()->count();
            if($starTag->tag->count > 0) {
                array_push($tags_rating,json_decode(json_encode($starTag->tag)));
                           }


            $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "question_id" => $question->id,
                "star_id" => $questionStar->star->id,
                "tag_id" => $starTag->tag->id,
                "review_news.guest_id" => NULL
                ]
            );
            if(!empty($request->start_date) && !empty($request->end_date)) {

                $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                    $request->start_date,
                    $request->end_date
                ]);

            }
            $starTag->tag->total = $starTag->tag->total->get()->count();

                if($starTag->tag->total > 0) {
                    unset($starTag->tag->count);
                    array_push($data[$key1]["stars"][$key2]["tag_ratings"],json_decode(json_encode($starTag->tag)));
                }


          }



            }

        }


        $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
    }





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

    $data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
    ->where([
        "review_news.business_id" => $business->id,
        "star_id" => $star->id,
        "review_news.guest_id" => NULL
    ])
    ->distinct("review_value_news.review_id","review_value_news.question_id");
    if(!empty($request->start_date) && !empty($request->end_date)) {

        $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
            $request->start_date,
            $request->end_date
        ]);

    }
    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

    $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

    $ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
    $data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
    $data2["total_rating"] = 0;

}

$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
    "business_id" => $business->id,
    "guest_id" => NULL,
])
->whereNotNull("comment")
;
if(!empty($request->start_date) && !empty($request->end_date)) {

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


            if(!$questions) {
                return response([
                    "message" => "No question found"
                ], 404);
            }
            $data =  json_decode(json_encode($questions), true);

            foreach($questions->question_stars as $key2=>$questionStar){
                $data["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;


                $data["stars"][$key2]["tags"] = [];
                foreach($questionStar->star->star_tags as $key3=>$starTag){

    if($starTag->question_id == $questions->id) {

        array_push($data["stars"][$key2]["tags"],json_decode(json_encode($starTag->tag), true));

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

    public function   getQuestionById2($id,$businessId, Request $request)
    {
        $questions =    Question::where(["id" => $id,"business_id"=>$businessId])
            ->first();


            if(!$questions) {
                return response([
                    "message" => "No question found"
                ], 404);
            }
            $data =  json_decode(json_encode($questions), true);

            foreach($questions->question_stars as $key2=>$questionStar){
                $data["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;


                $data["stars"][$key2]["tags"] = [];
                foreach($questionStar->star->star_tags as $key3=>$starTag){

    if($starTag->question_id == $questions->id) {

        array_push($data["stars"][$key2]["tags"],json_decode(json_encode($starTag->tag), true));

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
            if(!$business){
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
     *      path="/review-new/create/tags/multiple/{businessId}",
     *      operationId="storeTagMultiple",
     *      tags={"review.setting.tag"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store tag",
     *      description="This method is to store tag",
          *  @OA\Parameter(
* name="businessId",
* in="path",
* description="businessId",
* required=true,
* example="1"
* ),
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"tags"},
 *  @OA\Property(property="tags", type="string", format="array",example={
 * "tag1","tag2"
     * }
     *
     * ),
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
    public function storeTagMultiple($businessId,Request $request)
    {


$dataArray = [];
        $duplicate_indexes_array = [];

        $uniqueTags = collect($request->tags)->unique()->values()->all();





        foreach($uniqueTags as $index=>$tag) {
            $question = [
                'tag' => $tag,
                'business_id' => $businessId
            ];


            if ($request->user()->hasRole("superadmin")) {


            $tag_found =    Tag::where([
                    "business_id" => NULL,
                    "tag" => $question["tag"],
                    "is_default" => 1
                ])
                ->first();

         if($tag_found) {

            array_push($duplicate_indexes_array,$index);
        }
            } else {
                $tag_found =    Tag::where(["business_id" => $businessId,"is_default" => 0,"tag" => $question["tag"]])

                ->first();

         if($tag_found) {

            array_push($duplicate_indexes_array,$index);
        } else {
            $tag_found =    Tag::where(["business_id" => NULL,"is_default" => 1,"tag" => $question["tag"]])
            ->first();
            if($tag_found) {

                array_push($duplicate_indexes_array,$index);
            }
        }


            }





        }



        if(count($duplicate_indexes_array)) {

            return response([
                "message" => "duplicate data",
                "duplicate_indexes_array"=> $duplicate_indexes_array
        ], 409);

        }

        else {

 foreach($uniqueTags as $index=>$tag) {
            $question = [
                'tag' => $tag,
                'business_id' => $businessId
            ];


            if ($request->user()->hasRole("superadmin")) {
                $question["is_default"] = true;
                $businessId = NULL;
                $question["business_id"] = NULL;



            } else {

                $question["is_default"] = false;

                $business =    Business::where(["id" => $businessId])->first();
                if(!$business){
                    return response()->json(["message" => "No Business Found"]);
                }
            }

            if(!count($duplicate_indexes_array)) {
              $finalTag =  Tag::create($question);
              array_push($dataArray,$finalTag);
            }
            else {
                return response()->json($duplicate_indexes_array,200) ;
            }



        }
        }





        return response(["message" => "data inserted","data"=>$dataArray], 201);


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
            $query =  Tag::where(["business_id" => NULL,"is_default" => true])
            ->when(request()->filled("is_active"), function($query) {
               $query->where("tags.is_active",request()->input("is_active"));
            });

        }
        else{
            $business =    Business::where(["id" => $request->business_id])->first();
            if(!$business && !$request->user()->hasRole("superadmin")){
                return response("No Business Found", 404);
            }
            // if ($business->enable_question == true) {
            //     $is_dafault = true;
            // }
            $query =  Tag::where(["business_id" => $businessId,"is_default" => 0])
            ->orWhere(["business_id" => NULL,"is_default" => 1])
            ->when(request()->filled("is_active"), function($query) {
                $query->where("tags.is_active",request()->input("is_active"));
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
            if(!$questions) {
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
    public function   getTagById2($id,$reataurantId, Request $request)
    {
        $questions =    Tag::where(["id" => $id,"business_id" => $reataurantId])
            ->first();
            if(!$questions) {
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
                StarTag::where(["tag_id"=> $tagId])->delete();
                $tag->delete();
                ReviewValueNew::where([
                    'tag_id'=>$tagId
                ])
                ->delete();

            }
            else  if(!$request->user()->hasRole("superadmin") &&  $tag->is_default == 0){
                StarTag::where(["tag_id"=> $tagId])->delete();
                $tag->delete();
                ReviewValueNew::where([
                    'tag_id'=>$tagId
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
        if(!$business){
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
    public function   getSelectedTagCount($businessId , Request $request)
    {

        $questions =    Question::where(["business_id" => $businessId])
            ->get();
        $data =  json_decode(json_encode($questions), true);
        foreach($questions as $key1=>$question){

            foreach($question->question_stars as $key2=>$questionStar){
                $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;
                $data[$key1]["stars"][$key2]["star_count"]  =  ReviewValueNew::where([
                      "question_id"=>$question->id,
                      "star_id"=> $questionStar->star->id,

                     ])->count();


                foreach($questionStar->star->star_tags as $key3=>$starTag){
   if($starTag->question_id == $question->id) {
    $data[$key1]["stars"][$key2]["tags"][$key3] = json_decode(json_encode($starTag->tag), true) ;
    // $data[$key1]["stars"][$key2]["tags"][$key3]["search"] = [
    //     "question_id"=>$question->id,
    //     "star_id"=> $questionStar->star->id,
    //     "tag_id"=> $starTag->tag->id

    // ];

     $data[$key1]["stars"][$key2]["tags"][$key3]["tag_count"]  =  ReviewValueNew::where([
         "question_id"=>$question->id,
         "star_id"=> $questionStar->star->id,
         "tag_id"=> $starTag->tag->id

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
    public function   getSelectedTagCountByQuestion($questionId , Request $request)
    {

        $question =    Question::where(["id" => $questionId])
            ->first();

        $data =  json_decode(json_encode($question), true);


            foreach($question->question_stars as $key2=>$questionStar){
                $data["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;
                $data["stars"][$key2]["star_count"]  =  ReviewValueNew::where([
                      "question_id"=>$question->id,
                      "star_id"=> $questionStar->star->id,

                     ])->count();

                foreach($questionStar->star->star_tags as $key3=>$starTag){
                    if($starTag->question_id == $question->id){
                        $data["stars"][$key2]["tags"][$key3] = json_decode(json_encode($starTag->tag), true) ;
                        // $data["stars"][$key2]["tags"][$key3]["search"] = [
                        //     "question_id"=>$question->id,
                        //     "star_id"=> $questionStar->star->id,
                        //     "tag_id"=> $starTag->tag->id

                        // ];

                         $data["stars"][$key2]["tags"][$key3]["tag_count"]  =  ReviewValueNew::where([
                             "question_id"=>$question->id,
                             "star_id"=> $questionStar->star->id,
                             "tag_id"=> $starTag->tag->id

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
        return DB::transaction(function ()use($request) {
            $question_id = $request->question_id;
            foreach($request->stars as $requestStar){


                QusetionStar::create([
                    "question_id"=>$question_id,
                    "star_id" => $requestStar["star_id"]
                         ]);


               foreach($requestStar["tags"] as $tag){


               StarTag::create([
                "question_id"=>$question_id,
                "tag_id"=>$tag["tag_id"],
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

        return DB::transaction(function ()use($request) {
            $question_id = $request->question_id;

            $starIds = collect($request->stars)->pluck('star_id')->toArray();


            QusetionStar::where([
                    'question_id' => $question_id,
                ])
                ->whereNotIn('star_id', $starIds)
                ->delete();



            foreach($request->stars as $requestStar){

    if( !(QusetionStar::where([
    "question_id"=>$question_id,
    "star_id" => $requestStar["star_id"]
         ])->exists())) {
            QusetionStar::create([
                "question_id"=>$question_id,
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

               foreach($requestStar["tags"] as $tag){

                if( !(StarTag::where([
                    "question_id"=>$question_id,
                "tag_id"=>$tag["tag_id"],
                "star_id" => $requestStar["star_id"]
                         ])->exists())) {
                            StarTag::create([
                                "question_id"=>$question_id,
                                "tag_id"=>$tag["tag_id"],
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

    public function getQuestionAllReportGuest(Request $request) {


        $business =    Business::where(["id" => $request->business_id])->first();
        if(!$business){
            return response("No Business Found", 404);
        }

    $query =  Question::where(["business_id" => $request->business_id,"is_default" => false]);

    $questions =  $query->get();

    $questionsCount = $query->get()->count();

$data =  json_decode(json_encode($questions), true);
foreach($questions as $key1=>$question){

    $tags_rating = [];
   $starCountTotal = 0;
   $starCountTotalTimes = 0;
    foreach($question->question_stars as $key2=>$questionStar){


        $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

        $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "review_news.user_id" => NULL,

            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

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
        if($starCountTotalTimes > 0) {
            $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
        }



        foreach($questionStar->star->star_tags as $key3=>$starTag){





     if($starTag->question_id == $question->id) {




        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "tag_id" => $starTag->tag->id,
            "review_news.user_id" => NULL
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->count  = $starTag->tag->count->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }
        $starTag->tag->count = $starTag->tag->count->get()->count();

        if($starTag->tag->count > 0) {
                array_push($tags_rating,json_decode(json_encode($starTag->tag)));
        }

        $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "tag_id" => $starTag->tag->id,
            "review_news.user_id" => NULL
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }
        $starTag->tag->total = $starTag->tag->total->get()->count();
        if($starTag->tag->total > 0) {
            unset($starTag->tag->count);
            array_push($data[$key1]["stars"][$key2]["tag_ratings"],json_decode(json_encode($starTag->tag)));
        }




      }


        }

    }


    $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
}





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

$data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
->where([
    "review_news.business_id" => $business->id,
    "star_id" => $star->id,
    "review_news.user_id" => NULL
])
->distinct("review_value_news.review_id","review_value_news.question_id");
if(!empty($request->start_date) && !empty($request->end_date)) {

    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
        $request->start_date,
        $request->end_date
    ]);

}
$data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

$totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

$ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
$data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
$data2["total_rating"] = 0;
}







$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
    "business_id" => $business->id,
    "user_id" => NULL,
])
->whereNotNull("comment");
if(!empty($request->start_date) && !empty($request->end_date)) {

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

    public function getQuestionAllReportUnauthorized(Request $request) {


        $business =    Business::where(["id" => $request->business_id])->first();
        if(!$business){
            return response("No Business Found", 404);
        }

    $query =  Question::where(["business_id" => $request->business_id,"is_default" => false]);

    $questions =  $query->get();

    $questionsCount = $query->get()->count();

$data =  json_decode(json_encode($questions), true);
foreach($questions as $key1=>$question){

    $tags_rating = [];
   $starCountTotal = 0;
   $starCountTotalTimes = 0;
    foreach($question->question_stars as $key2=>$questionStar){


        $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

        $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
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

        if($starCountTotalTimes > 0) {
            $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
        }




        foreach($questionStar->star->star_tags as $key3=>$starTag){





     if($starTag->question_id == $question->id) {



        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "tag_id" => $starTag->tag->id,
            "review_news.guest_id" => NULL
            ]
        )->get()->count();





            if($starTag->tag->count > 0) {
 array_push($tags_rating,json_decode(json_encode($starTag->tag)));
            }











      }



        }

    }


    $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
}





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

$data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
->where([
    "review_news.business_id" => $business->id,
    "star_id" => $star->id,
    "review_news.guest_id" => NULL
])
->distinct("review_value_news.review_id","review_value_news.question_id")
->count();

$totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

$ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
$data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
$data2["total_rating"] = 0;

}

$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
"business_id" => $business->id,
"guest_id" => NULL,
])
->whereNotNull("comment")
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

    public function getQuestionAllReportGuestUnauthorized(Request $request) {


        $business =    Business::where(["id" => $request->business_id])->first();
        if(!$business){
            return response("No Business Found", 404);
        }

    $query =  Question::where(["business_id" => $request->business_id,"is_default" => false]);

    $questions =  $query->get();

    $questionsCount = $query->get()->count();

$data =  json_decode(json_encode($questions), true);
foreach($questions as $key1=>$question){

    $tags_rating = [];
   $starCountTotal = 0;
   $starCountTotalTimes = 0;
    foreach($question->question_stars as $key2=>$questionStar){


        $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

        $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
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

        if($starCountTotalTimes > 0) {
            $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
        }




        foreach($questionStar->star->star_tags as $key3=>$starTag){





     if($starTag->question_id == $question->id) {




        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "tag_id" => $starTag->tag->id,
            "review_news.user_id" => NULL
            ]
        )->get()->count();


        if($starTag->tag->count > 0) {
                array_push($tags_rating,json_decode(json_encode($starTag->tag)));
        }



      }


        }

    }


    $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
}





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

$data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
->where([
    "review_news.business_id" => $business->id,
    "star_id" => $star->id,
    "review_news.user_id" => NULL
])
->distinct("review_value_news.review_id","review_value_news.question_id")
->count();

$totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

$ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
$data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
$data2["total_rating"] = 0;
}







$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
    "business_id" => $business->id,
    "user_id" => NULL,
])
->whereNotNull("comment")
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

    public function getQuestionAllReportGuestQuantum(Request $request) {


        $business =    Business::where(["id" => $request->business_id,
        "OwnerID" => $request->user()->id
        ])->first();
        if(!$business){
            return response("No Business Found or you are not the owner of the business", 404);
        }
$data = [];

$period=0;
        for($i=0;$i<$request->quantum;$i++ ) {
            $totalCount = 0;
            $ttotalRating = 0;

            foreach(Star::get() as $star) {

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
            ->distinct("review_value_news.review_id","review_value_news.question_id")
            ->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];

            }
            if($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;

            }
            else {
            $data2["total_rating"] = 0;
            }

            // $data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
            //     "business_id" => $business->id,
            //     "user_id" => NULL,
            // ])
            // ->whereNotNull("comment")
            // ->count();
        array_push($data,$data2);
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

    public function getQuestionAllReportQuantum(Request $request) {


        $business =    Business::where(["id" => $request->business_id,
        "OwnerID" => $request->user()->id
        ])->first();
        if(!$business){
            return response("No Business Found or you are not the owner of the business", 404);
        }
$data = [];

$period=0;
        for($i=0;$i<$request->quantum;$i++ ) {
            $totalCount = 0;
            $ttotalRating = 0;

            foreach(Star::get() as $star) {

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
            ->distinct("review_value_news.review_id","review_value_news.question_id")
            ->count();

            $totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

            $ttotalRating += $data2["star_" . $star->value . "_selected_count"];

            }
            if($totalCount > 0) {
            $data2["total_rating"] = $totalCount / $ttotalRating;

            }
            else {
            $data2["total_rating"] = 0;
            }

            // $data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
            //     "business_id" => $business->id,
            //     "user_id" => NULL,
            // ])
            // ->whereNotNull("comment")
            // ->count();
        array_push($data,$data2);
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

    public function getQuestionAllReportByUser($perPage,Request $request) {


        $business =    Business::where(["id" => $request->business_id])->first();
        if(!$business){
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
->select("users.*","review_news.created_at as review_created_at");
if(!empty($request->start_date) && !empty($request->end_date)) {

    $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
        $request->start_date,
        $request->end_date
    ]);

}
$users = $usersQuery->paginate($perPage);

for($i = 0;$i < count($users->items());$i++ ){
    $query =  Question::leftjoin('review_value_news', 'questions.id', '=', 'review_value_news.question_id')
    ->leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
    ->where([
        "questions.business_id" => $request->business_id,
        "questions.is_default" => false,
        "review_news.user_id" => $users->items()[$i]->id
    ])
    ->groupBy("questions.id")
    ->select("questions.*")
    ;

    $questions =  $query->get();



$data =  json_decode(json_encode($questions), true);
foreach($questions as $key1=>$question){

    $tags_rating = [];
   $starCountTotal = 0;
   $starCountTotalTimes = 0;
    foreach($question->question_stars as $key2=>$questionStar){


        $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

        $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "review_news.guest_id" => NULL,
            "review_news.user_id" => $users->items()[$i]->id
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

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
        if($starCountTotalTimes > 0) {
            $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
        }


        foreach($questionStar->star->star_tags as $key3=>$starTag){


     if($starTag->question_id == $question->id) {

        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "tag_id" => $starTag->tag->id,
            "review_news.guest_id" => NULL,
            "review_news.user_id" => $users->items()[$i]->id
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }

        $starTag->tag->count = $starTag->tag->count->get()->count();
        if($starTag->tag->count > 0) {
            array_push($tags_rating,json_decode(json_encode($starTag->tag)));
                       }


        $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "tag_id" => $starTag->tag->id,
            "review_news.guest_id" => NULL,
            "review_news.user_id" => $users->items()[$i]->id
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }
        $starTag->tag->total = $starTag->tag->total->get()->count();

            if($starTag->tag->total > 0) {
                unset($starTag->tag->count);
                array_push($data[$key1]["stars"][$key2]["tag_ratings"],json_decode(json_encode($starTag->tag)));
            }


      }



        }

    }


    $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
}





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

$data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
->where([
    "review_news.business_id" => $business->id,
    "star_id" => $star->id,
    "review_news.guest_id" => NULL,
    "review_news.user_id" => $users->items()[$i]->id
])
->distinct("review_value_news.review_id","review_value_news.question_id");
if(!empty($request->start_date) && !empty($request->end_date)) {

    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
        $request->start_date,
        $request->end_date
    ]);

}
$data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

$totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

$ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
$data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
$data2["total_rating"] = 0;

}

$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
"business_id" => $business->id,
"guest_id" => NULL,
"review_news.user_id" => $users->items()[$i]->id
])
->whereNotNull("comment");
if(!empty($request->start_date) && !empty($request->end_date)) {

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
return response()->json($users,200);

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

    public function getQuestionAllReportByUserGuest($perPage,Request $request) {


        $business =    Business::where(["id" => $request->business_id])->first();
        if(!$business){
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
->select("guest_users.*",

"review_news.created_at as review_created_at"
);

if(!empty($request->start_date) && !empty($request->end_date)) {

    $usersQuery = $usersQuery->whereBetween('review_news.created_at', [
        $request->start_date,
        $request->end_date
    ]);

}
$users = $usersQuery->paginate($perPage);


for($i = 0;$i < count($users->items());$i++ ){
    $query =  Question::leftjoin('review_value_news', 'questions.id', '=', 'review_value_news.question_id')
    ->leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
    ->where([
        "questions.business_id" => $request->business_id,
        "is_default" => false,
        "review_news.guest_id" => $users->items()[$i]->id
    ])
    ->groupBy("questions.id")
    ->select("questions.*")
    ;

    $questions =  $query->get();



$data =  json_decode(json_encode($questions), true);
foreach($questions as $key1=>$question){

    $tags_rating = [];
   $starCountTotal = 0;
   $starCountTotalTimes = 0;
    foreach($question->question_stars as $key2=>$questionStar){


        $data[$key1]["stars"][$key2]= json_decode(json_encode($questionStar->star), true) ;

        $data[$key1]["stars"][$key2]["stars_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "review_news.guest_id" => $users->items()[$i]->id,
            "review_news.user_id" => NULL
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

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
        if($starCountTotalTimes > 0) {
            $data[$key1]["rating"] = $starCountTotal / $starCountTotalTimes;
        }


        foreach($questionStar->star->star_tags as $key3=>$starTag){


     if($starTag->question_id == $question->id) {

        $starTag->tag->count =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "tag_id" => $starTag->tag->id,
            "review_news.guest_id" => $users->items()[$i]->id,
            "review_news.user_id" => NULL
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->count = $starTag->tag->count->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }

        $starTag->tag->count = $starTag->tag->count->get()->count();
        if($starTag->tag->count > 0) {
            array_push($tags_rating,json_decode(json_encode($starTag->tag)));
                       }


        $starTag->tag->total =  ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        ->where([
            "review_news.business_id" => $business->id,
            "question_id" => $question->id,
            "star_id" => $questionStar->star->id,
            "tag_id" => $starTag->tag->id,
            "review_news.guest_id" => $users->items()[$i]->id,
            "review_news.user_id" => NULL
            ]
        );
        if(!empty($request->start_date) && !empty($request->end_date)) {

            $starTag->tag->total = $starTag->tag->total->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);

        }
        $starTag->tag->total = $starTag->tag->total->get()->count();

            if($starTag->tag->total > 0) {
                unset($starTag->tag->count);
                array_push($data[$key1]["stars"][$key2]["tag_ratings"],json_decode(json_encode($starTag->tag)));
            }


      }



        }

    }


    $data[$key1]["tags_rating"] = array_values(collect($tags_rating)->unique()->toArray());
}





$totalCount = 0;
$ttotalRating = 0;

foreach(Star::get() as $star) {

$data2["star_" . $star->value . "_selected_count"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
->where([
    "review_news.business_id" => $business->id,
    "star_id" => $star->id,
    "review_news.guest_id" => $users->items()[$i]->id,
    "review_news.user_id" => NULL
])
->distinct("review_value_news.review_id","review_value_news.question_id");
if(!empty($request->start_date) && !empty($request->end_date)) {

    $data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->whereBetween('review_news.created_at', [
        $request->start_date,
        $request->end_date
    ]);

}
$data2["star_" . $star->value . "_selected_count"] = $data2["star_" . $star->value . "_selected_count"]->count();

$totalCount += $data2["star_" . $star->value . "_selected_count"] * $star->value;

$ttotalRating += $data2["star_" . $star->value . "_selected_count"];

}
if($totalCount > 0) {
$data2["total_rating"] = $totalCount / $ttotalRating;

}
else {
$data2["total_rating"] = 0;

}

$data2["total_comment"] = ReviewNew::with("user","guest_user")->where([
"business_id" => $business->id,
"guest_id" => $users->items()[$i]->id,
"review_news.user_id" => NULL
])
->whereNotNull("comment");
if(!empty($request->start_date) && !empty($request->end_date)) {

$data2["total_comment"] = $data2["total_comment"]->whereBetween('review_news.created_at', [
    $request->start_date,
    $request->end_date
]);

}
$data2["total_comment"] = $data2["total_comment"]->get()
;

$users->items()[$i]["review_info"] = [
    "part1" =>  $data2,
    "part2" =>  $data
];


}

return response()->json($users,200);

}



}
