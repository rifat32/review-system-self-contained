<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\Business;
use App\Services\DashboardService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardManagementController extends Controller
{
    use ErrorUtil;


    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-owner-dashboard",
     *      operationId="getBusinessOwnerDashboardData",
     *      tags={"dashboard_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     * @OA\Parameter(
     *     name="customer_date_filter",
     *     in="query",
     *     description="Customer date filter (allowed values: today, this_week, this_month, next_week, next_month, previous_week, previous_month)",
     *     required=true,
     *     example="this_week"
     * ),
     *      summary="Get all dashboard data combined",
     *      description="Retrieves combined dashboard data for business owners, including customer analytics by period",
     *

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard data retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="customers", type="object",
     *                      @OA\Property(property="first_time_customers", type="array", @OA\Items(type="object")),
     *                      @OA\Property(property="returning_customers", type="array", @OA\Items(type="object"))
     *                  ),
     *                  @OA\Property(property="customer_report", type="object"),
     *                  @OA\Property(property="filters", type="object")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized - User is not a business user",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You are not a business user")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Unprocessable Content - Validation errors",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation errors"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Bad Request")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Not found")
     *          )
     *      )
     *      )
     *     )
     */

    public function getBusinessOwnerDashboardData(Request $request): JsonResponse
    {
        try {

            $this->storeActivity($request, "");

            $business = Business::where([
                "OwnerID" => auth()->user()->id
            ])
                ->first();

            if (empty($business)) {
                return response()->json([
                    "success" => false,
                    "message" => "You are not a business user"
                ], 401);
            }

            // Define validation rules for date filters
            $validator = Validator::make($request->all(), [
                'customer_date_filter' => 'required|string|in:today,this_week,this_month,next_week,next_month,previous_week,previous_month',
            ], [
                'customer_date_filter.required' => 'The customer date filter is required.',
                'customer_date_filter.string' => 'The customer date filter must be a valid string.',
                'customer_date_filter.in' => 'The customer date filter must be one of the following: today, this_week, this_month, next_week, next_month, previous_week, previous_month.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors()
                ], 422);
            }

            $dashboardService = new DashboardService();
            $customers = $dashboardService->getCustomersByPeriod(request()->input("customer_date_filter"), $business);

            $data["customers"] = $customers;

            $data["customer_report"] = [
                "customers" => $customers,
            ];

            $data["filters"] = [
                "customers" => request()->input("customer_filters"),
            ];

            return response()->json([
                "success" => true,
                "message" => "Dashboard data retrieved successfully",
                "data" => $data
            ], 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }
}
