<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\User;
use App\Services\Review\ReviewMetricsService;
use App\Services\User\UserService;
use App\Traits\AuthorizesRoles;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends Controller
{
    use AuthorizesRoles;

    protected ReviewMetricsService $reviewMetricsService;
    protected UserService $userService;

    public function __construct(
        ReviewMetricsService $reviewMetricsService,
        UserService $userService
    ) {
        $this->reviewMetricsService = $reviewMetricsService;
        $this->userService = $userService;
    }


    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/business-metrics",
     *      operationId="getBusinessMetrics",
     *      tags={"super_admin.dashboard_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get super admin dashboard business metrics",
     *      description="Retrieve comprehensive business metrics including total businesses, active/inactive businesses, reviews, and customers with period-based comparisons (Super Admin only)",
     *
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for metrics (today, last_7_days, last_30_days, last_90_days, this_year, all_time)",
     *          required=false,
     *          example="last_30_days",
     *          @OA\Schema(
     *              type="string",
     *              enum={"today", "last_7_days", "last_30_days", "last_90_days", "this_year", "all_time"},
     *              default="last_30_days"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Metrics retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Super Admin Dashboard metrics retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="total_business",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=0, description="Total businesses created in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=120, description="Total businesses created in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="increase"),
     *                      @OA\Property(property="value", type="number", format="float", example=25.0, description="Percentage change (absolute value)")
     *                  ),
     *                  @OA\Property(
     *                      property="active_business",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=140, description="Active businesses in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=110, description="Active businesses in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="increase"),
     *                      @OA\Property(property="value", type="number", format="float", example=27.27, description="Percentage change (absolute value)")
     *                  ),
     *                  @OA\Property(
     *                      property="inactive_business",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=10, description="Inactive businesses in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=10, description="Inactive businesses in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="no_change"),
     *                      @OA\Property(property="value", type="number", format="float", example=0.0, description="Percentage change (absolute value)")
     *                  ),
     *                  @OA\Property(
     *                      property="review",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=0, description="Reviews created in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=1200, description="Reviews created in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="increase"),
     *                      @OA\Property(property="value", type="number", format="float", example=25.0, description="Percentage change (absolute value)")
     *                  ),
     *                  @OA\Property(
     *                      property="customer",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=3000, description="Customers registered in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=2500, description="Customers registered in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="increase"),
     *                      @OA\Property(property="value", type="number", format="float", example=20.0, description="Percentage change (absolute value)")
     *                  )
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
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied: You cannot perform this action")
     *          )
     *      )
     * )
     */
    public function getBusinessMetrics(Request $request)
    {
        // ENSURE SUPER ADMIN ACCESS
        $this->ensureSuperAdmin();

        $period = $request->get("period", "last_30_days");

        $dateRange = $period === 'all_time' ? null : getDateRangeByPeriod($period);

        // ==================== BUSINESS METRICS ====================
        $currentBusinessCount = Business::withTrashed()->when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))->count();

        $comparisonBusinessCount = Business::withTrashed()->when($dateRange, function ($query) use ($dateRange) {
            $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
            $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
            return $query->whereBetween("created_at", [$startDate, $endDate]);
        })->count();

        $activeBusinessCount = Business::withTrashed()->where("is_active", true)
            ->when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))
            ->count();

        $comparisonActiveBusinessCount = Business::withTrashed()->where("is_active", true)
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
                $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
                return $query->whereBetween("created_at", [$startDate, $endDate]);
            })
            ->count();

        $inactiveBusinessCount = Business::withTrashed()->where("is_active", false)
            ->when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))
            ->count();

        $comparisonInactiveBusinessCount = Business::withTrashed()->where("is_active", false)
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
                $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
                return $query->whereBetween("created_at", [$startDate, $endDate]);
            })
            ->count();

        $totalBusinessCount = Business::withTrashed()->count();

        // ==================== REVIEW METRICS ====================
        $currentReviewCount = ReviewNew::when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))->count();

        $comparisonReviewCount = ReviewNew::when($dateRange, function ($query) use ($dateRange) {
            $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
            $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
            return $query->whereBetween("created_at", [$startDate, $endDate]);
        })->count();

        // ==================== CUSTOMER METRICS ====================
        $currentCustomerCount = User::whereHas("roles", fn($query) => $query->where("name", User::USER_ROLE['CUSTOMER']))
            ->when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))
            ->count();

        $comparisonCustomerCount = User::whereHas("roles", fn($query) => $query->where("name", User::USER_ROLE['CUSTOMER']))
            ->when($dateRange, function ($query) use ($dateRange) {
                $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
                $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
                return $query->whereBetween("created_at", [$startDate, $endDate]);
            })
            ->count();

        // ==================== CALCULATE CHANGES ====================
        $businessMetrics = calculateMetricChange($currentBusinessCount, $comparisonBusinessCount);
        $activeBusinessMetrics = calculateMetricChange($activeBusinessCount, $comparisonActiveBusinessCount);
        $inactiveBusinessMetrics = calculateMetricChange($inactiveBusinessCount, $comparisonInactiveBusinessCount);
        $reviewMetrics = calculateMetricChange($currentReviewCount, $comparisonReviewCount);
        $customerMetrics = calculateMetricChange($currentCustomerCount, $comparisonCustomerCount);

        // ==================== RETURN RESPONSE ====================
        return response()->json([
            "success" => true,
            "message" => "Super Admin Dashboard metrics retrieved successfully",
            "data" => [
                "total_business" => [
                    "current" => $currentBusinessCount,
                    "comparison" => $comparisonBusinessCount,
                    "change_type" => $businessMetrics['change_type'],
                    "value" => $businessMetrics['value'],
                ],
                "active_business" => [
                    "current" => $activeBusinessCount,
                    "comparison" => $comparisonActiveBusinessCount,
                    "change_type" => $activeBusinessMetrics['change_type'],
                    "value" => $activeBusinessMetrics['value']
                ],
                "inactive_business" => [
                    "current" => $inactiveBusinessCount,
                    "comparison" => $comparisonInactiveBusinessCount,
                    "change_type" => $inactiveBusinessMetrics['change_type'],
                    "value" => $inactiveBusinessMetrics['value']
                ],
                "review" => [
                    "current" => $currentReviewCount,
                    "comparison" => $comparisonReviewCount,
                    "change_type" => $reviewMetrics['change_type'],
                    "value" => $reviewMetrics['value']
                ],
                "customer" => [
                    "current" => $currentCustomerCount,
                    "comparison" => $comparisonCustomerCount,
                    "change_type" => $customerMetrics['change_type'],
                    "value" => $customerMetrics['value']
                ]
            ]
        ], 200);
    }


    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/review-trends",
     *      operationId="getReviewTrends",
     *      tags={"super_admin.dashboard_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get review trends for super admin",
     *      description="Retrieve review submission trends over time across all businesses for super admin",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Time period for trends (e.g., 30d, 7d, 1d)",
     *          @OA\Schema(type="string"),
     *          example="30d"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Review trends retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Review trends retrieved successfully"),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"),
     *                  description="Array of trend data points with dates and counts"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied: You cannot perform this action")
     *          )
     *      )
     * )
     */
    public function getReviewTrends(Request $request)
    {
        // ENSURE SUPER ADMIN ACCESS
        $this->ensureSuperAdmin();

        // GET ALL REVIEWS (NO BUSINESS ID FILTER FOR SUPER ADMIN)
        $reviewsQuery = ReviewNew::query()
            ->with(['user', 'guest_user', 'survey'])
            ->withCalculatedRating();

        // GENERATE DATA FOR TRENDS
        $reviewTrends = $this->reviewMetricsService->getSubmissionsOverTime(
            reviews: (clone $reviewsQuery),
            period: $request->get('period', '30d')
        );

        return response()->json([
            'success' => true,
            'message' => 'Review trends retrieved successfully',
            'data' => $reviewTrends
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/customer-registration-trends",
     *      operationId="getCustomerRegistrationTrends",
     *      tags={"super_admin.dashboard_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get customer registration trends for super admin",
     *      description="Retrieve customer registration trends over time across all businesses for super admin",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Time period for trends (e.g., 30d, 7d, 1d)",
     *          @OA\Schema(type="string"),
     *          example="30d"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Customer registration trends retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer registration trends retrieved successfully"),
     *              @OA\Property(property="data", type="array", @OA\Items(type="object"),
     *                  description="Array of trend data points with dates and registration counts"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Access denied: You cannot perform this action")
     *          )
     *      )
     * )
     */
    public function getCustomerRegistrationTrends(Request $request)
    {
        // ENSURE SUPER ADMIN ACCESS
        $this->ensureSuperAdmin();

        // GET ALL CUSTOMERS (NO BUSINESS ID FILTER FOR SUPER ADMIN)
        $customersQuery = User::whereHas('roles', fn($query) => $query->where('name', User::USER_ROLE['CUSTOMER']))
            ->select('id', 'created_at');

        // GENERATE DATA FOR TRENDS USING USER SERVICE
        $customerTrends = $this->userService->getRegistrationTrends(
            users: (clone $customersQuery),
            period: $request->get('period', '30d')
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer registration trends retrieved successfully',
            'data' => $customerTrends
        ], 200);
    }
}