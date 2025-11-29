<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDailyViewRequest;
use App\Http\Requests\UpdateDailyViewRequest;
use App\Models\Business;
use App\Models\DailyView;
use Illuminate\Http\Request;

class DailyViewsController extends Controller
{
    // ##################################################
    // This method is to store daily views
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/v1.0/daily-views/{businessId}",
     *      operationId="createDailyView",
     *      tags={"daily_views"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Create a daily view record for a business",
     *      description="Create a new daily view record for the specified business. Only business owners or super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"view_date", "daily_views"},
     *              @OA\Property(property="view_date", type="string", format="date", example="2025-01-15", description="Date of the daily view in YYYY-MM-DD format"),
     *              @OA\Property(property="daily_views", type="integer", example=150, description="Number of daily views", minimum=0)
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Daily view created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Daily view created successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="view_date", type="string", format="date", example="2025-01-15"),
     *                  @OA\Property(property="daily_views", type="integer", example=150),
     *                  @OA\Property(property="business_id", type="integer", example=1)
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
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to create daily views for this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */



    public function createDailyView($businessId, CreateDailyViewRequest $request)
    {
        // VALIDATE REQUEST
        $validatedData = $request->validated();

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // CHECK BUSINESS OWNERSHIP OR SUPER ADMIN PERMISSION
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to create daily views for this business"
            ], 403);
        }

        // PREPARE DATA FOR CREATION
        $validatedData["business_id"] = $businessId;

        // CREATE DAILY VIEW
        $dailyView = DailyView::create($validatedData);

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Daily view created successfully",
            "data" => $dailyView
        ], 201);
    }
    // ##################################################
    // This method is to update daily views
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/v1.0/daily-views/update/{businessId}",
     *      operationId="updateDailyView",
     *      tags={"daily_views"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Update daily views for a business",
     *      description="Increment the daily view count for the specified business and date. Only business owners or super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"view_date"},
     *              @OA\Property(property="view_date", type="string", format="date", example="2025-01-15", description="Date of the daily view in YYYY-MM-DD format")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Daily view updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Daily view updated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="view_date", type="string", format="date", example="2025-01-15"),
     *                  @OA\Property(property="daily_views", type="integer", example=151),
     *                  @OA\Property(property="business_id", type="integer", example=1)
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
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to update daily views for this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Daily view not found for this date",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Daily view not found for this date")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */






    public function updateDailyView($businessId, UpdateDailyViewRequest $request)
    {
        // VALIDATE REQUEST
        $validatedData = $request->validated();

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // CHECK BUSINESS OWNERSHIP OR SUPER ADMIN PERMISSION
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to update daily views for this business"
            ], 403);
        }

        // FIND DAILY VIEW FOR THE SPECIFIC DATE
        $dailyView = DailyView::where('business_id', $businessId)
            ->where('view_date', $validatedData['view_date'])
            ->first();

        if (!$dailyView) {
            return response()->json([
                "success" => false,
                "message" => "Daily view not found for this date"
            ], 404);
        }

        // INCREMENT DAILY VIEWS
        $dailyView->daily_views += 1;
        $dailyView->save();

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Daily view updated successfully",
            "data" => $dailyView
        ], 200);
    }
}
