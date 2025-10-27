<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessDaysUpdateRequest;
use App\Models\BusinessDay;
use App\Models\Business;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessDaysController extends Controller
{
    /**
     *
     * @OA\Patch(
     *      path="/v1.0/business-days/{restaurentId}",
     *      operationId="updateBusinessDays",
     *      tags={"business_days_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      *        @OA\Parameter(
     *         name="restaurentId",
     *         in="path",
     *         description="restaurent Id",
     *         required=false,
     *      ),
     *      summary="This method is to update business times",
     *      description="This method is to update business times",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"business_id","times"},

     * @OA\Property(
     *     property="times",
     *     type="array",
     *     @OA\Items(
     *         type="object",
     *         @OA\Property(property="day", type="integer", example=0),
     *         @OA\Property(property="is_weekend", type="boolean", example=true),
     *         @OA\Property(
     *             property="time_slots",
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="start_at", type="string", format="time", example="10:10:00"),
     *                 @OA\Property(property="end_at", type="string", format="time", example="10:15:00")
     *             )
     *         )
     *     )
     * )



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

    public function updateBusinessdays($restaurentId, BusinessDaysUpdateRequest $request)
    {
        try {

            return  DB::transaction(function () use ($request, $restaurentId) {


                $request_data = $request->validated();
                $checkBusiness =    Business::where(["id" => $restaurentId])->first();
                if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                    return response()->json(["message" => "This is not your business", 401]);
                }

                $timesArray = collect($request_data["times"])->unique("day");



                // Delete existing BusinessDay records for the given business ID
                BusinessDay::where('business_id', $restaurentId)->delete();

                foreach ($timesArray as $business_time) {
                    // Create a BusinessDay record including the is_weekend field
                    $businessDay = BusinessDay::create([
                        'business_id' => $restaurentId,
                        'day' => $business_time['day'],
                        'is_weekend' => $business_time['is_weekend'],
                    ]);

                    // Create multiple BusinessTimeSlot records for the BusinessDay
                    foreach ($business_time['time_slots'] as $time_slot) {
                        $businessDay->timeSlots()->create([
                            'start_at' => $time_slot['start_at'],
                            'end_at' => $time_slot['end_at'],
                        ]);
                    }
                }



                return response(["message" => "data inserted"], 201);
            });
        } catch (Exception $e) {

            return response()->json(
                [
                    "message" => $e->getMessage()
                ],
                $e->getCode()
            );
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-days/{restaurentId}",
     *      operationId="getBusinessDays",
     *      tags={"business_days_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *      summary="This method is to get business times ",
     *      description="This method is to get business times",
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

    public function getBusinessDays($restaurentId, Request $request)
    {
        try {

            $business_days = BusinessDay::with("timeSlots")->where([
                "business_id" => $restaurentId
            ])
            ->when(request()->filled("day"), function ($query) {
                return $query->where("day", request()->input("day"));
            })
            ->orderByDesc("id")->get();
            
            return response()->json($business_days, 200);
        } catch (Exception $e) {

            return response()->json(
                [
                    "message" => $e->getMessage()
                ],
                $e->getCode()
            );
        }
    }
}
