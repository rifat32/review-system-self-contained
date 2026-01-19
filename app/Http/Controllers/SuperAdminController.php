<?php

namespace App\Http\Controllers;

use App\Models\ReviewNew;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class SuperAdminController extends Controller
{
    /**
     *
     * @OA\Get(
     *      path="/v1.0/customers/{customerId}/reviews",
     *      operationId="getCustomerReviews",
     *      tags={"super_admin.customer_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Get all reviews for a specific customer",
     *      description="Retrieve all reviews submitted by a specific customer with optional filtering and pagination",
     *
     *      @OA\Parameter(
     *          name="customerId",
     *          in="path",
     *          description="Customer ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="business_id",
     *          in="query",
     *          description="Business ID",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of reviews per page for pagination",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="Field to sort by (e.g., created_at, calculated_rating)",
     *          required=false,
     *          example="created_at",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort direction (ASC or DESC)",
     *          required=false,
     *          example="DESC",
     *          @OA\Schema(type="string", enum={"ASC", "DESC"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="rating",
     *          in="query",
     *          description="Filter reviews by rating (1-5)",
     *          required=false,
     *          @OA\Schema(type="integer", minimum=1, maximum=5)
     *      ),
     *
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Filter reviews created after this date (DD-MM-YYYY)",
     *          required=false,
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="Filter reviews created before this date (DD-MM-YYYY)",
     *          required=false,
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term for review comment",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sentiment",
     *          in="query",
     *          description="Filter by sentiment (positive, negative, neutral)",
     *          required=false,
     *          @OA\Schema(type="string", enum={"positive", "negative", "neutral"})
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Customer reviews retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer reviews retrieved successfully."),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="comment", type="string", example="Great service!"),
     *                      @OA\Property(property="sentiment", type="string", example="positive"),
     *                      @OA\Property(property="calculated_rating", type="number", format="float", example=4.5),
     *                      @OA\Property(property="created_at", type="string", format="date-time")
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="total", type="integer", example=50),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="total_pages", type="integer", example=4)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Customer not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Customer not found")
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
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Internal server error")
     *          )
     *      )
     * )
     */
    public function getCustomerReviews(Request $request, $customerId): JsonResponse
    {
        try {
            // VALIDATE CUSTOMER EXISTS
            $customer = User::whereHas('roles', function ($query) {
                $query->where('name', User::USER_ROLE['CUSTOMER']);
            })->findOrFail($customerId);

            // BUILD REVIEW QUERY
            $reviewsQuery = ReviewNew::where('user_id', $customer->id)
                ->withCalculatedRating()
                ->with([
                    'staff:id,first_Name,last_Name,image',
                    'business:id,Name,EmailAddress',
                    'business_services:id,name',
                    'value.question:id,question',
                    'value.star:id,value'
                ])
                // FILTER: BUSINESS ID
                ->when($request->filled('business_id'), function ($q) use ($request) {
                    $q->where('business_id', $request->business_id);
                })
                // FILTER: SEARCH KEY
                ->when($request->filled('search_key'), function ($q) use ($request) {
                    $q->where('comment', 'like', '%' . $request->search_key . '%');
                })
                // FILTER: SENTIMENT
                ->when($request->filled('sentiment'), function ($q) use ($request) {
                    $q->where('sentiment', $request->sentiment);
                })
                // FILTER: DATE RANGE
                ->when($request->filled('start_date'), function ($q) use ($request) {
                    $q->whereDate('review_news.created_at', '>=', $request->start_date);
                })
                ->when($request->filled('end_date'), function ($q) use ($request) {
                    $q->whereDate('review_news.created_at', '<=', $request->end_date);
                })
                // FILTER: RATING (using having clause for calculated rating)
                ->when($request->filled('rating'), function ($q) use ($request) {
                    $q->havingRaw('calculated_rating = ?', [$request->rating]);
                });

            // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
            $result = retrieve_data($reviewsQuery);

            return response()->json([
                "success" => true,
                "message" => "Customer reviews retrieved successfully.",
                "data" => $result['data'],
                "meta" => $result['meta']
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @OA\Patch(
     *      path="/v1.0/customers/{customerId}/email",
     *      operationId="customerEmailChange",
     *      tags={"super_admin.customer_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Change customer email address",
     *      description="Update a customer's email address (Super Admin only)",
     *
     *      @OA\Parameter(
     *          name="customerId",
     *          in="path",
     *          description="Customer ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email"},
     *              @OA\Property(property="email", type="string", format="email", description="New email address")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Email updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer email updated successfully."),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="email", type="string", example="newemail@example.com")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Customer not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Customer not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="The email has already been taken.")
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
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      )
     * )
     */
    public function customerEmailChange(Request $request, $customerId): JsonResponse
    {
        try {
            // VALIDATE REQUEST
            $request->validate([
                'email' => 'required|email|unique:users,email,' . $customerId
            ]);

            // FIND CUSTOMER
            $customer = User::whereHas('roles', function ($query) {
                $query->where('name', User::USER_ROLE['CUSTOMER']);
            })->findOrFail($customerId);

            // UPDATE EMAIL
            $customer->email = $request->email;
            $customer->save();

            return response()->json([
                "success" => true,
                "message" => "Customer email updated successfully.",
                "data" => [
                    "id" => $customer->id,
                    "email" => $customer->email
                ]
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/customers/metrics",
     *      operationId="customerMetrics",
     *      tags={"super_admin.customer_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Get customer metrics",
     *      description="Retrieve customer metrics including total customers with period-based comparisons (Super Admin only)",
     *
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for metrics",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              enum={"last_7_days", "last_30_days", "last_90_days", "this_week", "last_week", "this_month", "last_month", "this_quarter", "last_quarter", "this_year", "last_year"},
     *              default="last_30_days"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Customer metrics retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer metrics retrieved successfully."),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="total_customers",
     *                      type="object",
     *                      @OA\Property(property="current", type="integer", example=3000, description="Customers registered in current period"),
     *                      @OA\Property(property="comparison", type="integer", example=2500, description="Customers registered in comparison period"),
     *                      @OA\Property(property="change_type", type="string", enum={"increase", "decrease", "no_change"}, example="increase"),
     *                      @OA\Property(property="value", type="number", format="float", example=20.0, description="Percentage change (absolute value)")
     *                  ),
     *                  @OA\Property(
     *                      property="today",
     *                      type="object",
     *                      @OA\Property(property="count", type="integer", example=50, description="Customers registered today")
     *                  ),
     *                  @OA\Property(
     *                      property="last_7_days",
     *                      type="object",
     *                      @OA\Property(property="count", type="integer", example=350, description="Customers registered in last 7 days")
     *                  ),
     *                  @OA\Property(
     *                      property="last_30_days",
     *                      type="object",
     *                      @OA\Property(property="count", type="integer", example=1200, description="Customers registered in last 30 days")
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
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      )
     * )
     */
    public function customerMetrics(Request $request): JsonResponse
    {
        try {
            $period = $request->get("period", "last_30_days");
            $dateRange = $period === 'all_time' ? null : getDateRangeByPeriod($period);

            // ==================== CUSTOMER BASE QUERY ====================
            $customerQuery = fn() => User::whereHas("roles", fn($q) => $q->where("name", User::USER_ROLE['CUSTOMER']));

            // ==================== CURRENT PERIOD COUNT ====================
            $currentCustomerCount = $customerQuery()
                ->when($dateRange, fn($query) => $query->whereBetween("created_at", [$dateRange["start"], $dateRange["end"]]))
                ->count();

            // ==================== COMPARISON PERIOD COUNT ====================
            $comparisonCustomerCount = $customerQuery()
                ->when($dateRange, function ($query) use ($dateRange) {
                    $startDate = Carbon::parse($dateRange["start"])->subDays($dateRange["daysOffset"])->startOfDay();
                    $endDate = Carbon::parse($dateRange["end"])->subDays($dateRange["daysOffset"])->endOfDay();
                    return $query->whereBetween("created_at", [$startDate, $endDate]);
                })
                ->count();

            // ==================== FIXED PERIOD COUNTS ====================
            // TODAY
            $todayCount = $customerQuery()
                ->whereDate("created_at", Carbon::today())
                ->count();
            $yesterdayCount = $customerQuery()
                ->whereDate("created_at", Carbon::yesterday())
                ->count();

            // LAST 7 DAYS
            $last7DaysCount = $customerQuery()
                ->where("created_at", ">=", Carbon::now()->subDays(7)->startOfDay())
                ->count();
            $previous7DaysCount = $customerQuery()
                ->whereBetween("created_at", [
                    Carbon::now()->subDays(14)->startOfDay(),
                    Carbon::now()->subDays(7)->startOfDay()
                ])
                ->count();

            // LAST 30 DAYS
            $last30DaysCount = $customerQuery()
                ->where("created_at", ">=", Carbon::now()->subDays(30)->startOfDay())
                ->count();
            $previous30DaysCount = $customerQuery()
                ->whereBetween("created_at", [
                    Carbon::now()->subDays(60)->startOfDay(),
                    Carbon::now()->subDays(30)->startOfDay()
                ])
                ->count();

            // ==================== ALL TIME COUNT ====================
            $allTimeCount = $customerQuery()->count();

            // ==================== CALCULATE CHANGE METRICS ====================
            $totalCustomersMetrics = calculateMetricChange($currentCustomerCount, $comparisonCustomerCount);
            $todayMetrics = calculateMetricChange($todayCount, $yesterdayCount);
            $last7DaysMetrics = calculateMetricChange($last7DaysCount, $previous7DaysCount);
            $last30DaysMetrics = calculateMetricChange($last30DaysCount, $previous30DaysCount);

            return response()->json([
                "success" => true,
                "message" => "Customer metrics retrieved successfully.",
                "data" => [
                    "total_customers" => [
                        "current" => $currentCustomerCount,
                        "comparison" => $comparisonCustomerCount,
                        "change_type" => $totalCustomersMetrics['change_type'],
                        "value" => $totalCustomersMetrics['value']
                    ],
                    "today" => [
                        "current" => $todayCount,
                        "comparison" => $yesterdayCount,
                        "change_type" => $todayMetrics['change_type'],
                        "value" => $todayMetrics['value']
                    ],
                    "last_7_days" => [
                        "current" => $last7DaysCount,
                        "comparison" => $previous7DaysCount,
                        "change_type" => $last7DaysMetrics['change_type'],
                        "value" => $last7DaysMetrics['value']
                    ],
                    "last_30_days" => [
                        "current" => $last30DaysCount,
                        "comparison" => $previous30DaysCount,
                        "change_type" => $last30DaysMetrics['change_type'],
                        "value" => $last30DaysMetrics['value']
                    ]
                ]
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
