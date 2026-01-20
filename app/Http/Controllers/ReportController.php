<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Question;
use App\Models\Business;

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{







    private function generateDashboardReportV2(Request $request, $is_overall, $startDate, $endDate)
    {
        $businessId = $request->businessId;
        $data = [];
        $now = Carbon::now();
        $numberOfMonths = $startDate->diffInMonths($endDate);

        /* =========================
     | Date Ranges
     ========================= */
        $today = Carbon::today();
        $thisWeek = [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        $previousWeek = [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()];
        $thisMonthStart = now()->subDays(30)->endOfDay();
        $previousMonthRange = [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()];

        /* =========================
     | BASE REVIEW QUERIES (ONLY ONCE)
     ========================= */
        $baseReviewQuery = ReviewNew::where('review_news.business_id', $businessId)
            ->globalReviewFilters(0, $businessId)
            ->orderBy('review_news.order_no', 'asc')
            ->filterByOverall($is_overall)
            ->select('review_news.*')
            ->withCalculatedRating();

        $guestReviewQuery = (clone $baseReviewQuery)->whereNull('user_id');
        $customerReviewQuery = (clone $baseReviewQuery)->whereNull('guest_id');
        $authenticatedCustomerQuery = (clone $baseReviewQuery)->whereNotNull('user_id');

        /* =========================
     | QUESTIONS & TAGS BASE
     ========================= */
        $questionQuery = Question::when(
            !$request->user()->hasRole('superadmin'),
            fn($q) => $q->where('business_id', $businessId)
        )->filterByOverall($is_overall);

        $tagQuery = Tag::when(
            !$request->user()->hasRole('superadmin'),
            fn($q) => $q->where('business_id', $businessId)
        )->filterByOverall($is_overall);

        /* =========================
     | TODAY / WEEK
     ========================= */
        $data['today_total_reviews'] =
            (clone $baseReviewQuery)->whereDate('created_at', $today)->count();

        $data['this_week_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $previousWeek)->count();

        $data['this_week_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $thisWeek)->count();

        $data['previous_week_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $previousWeek)->count();

        /* =========================
     | MONTH (30 / 60 DAYS)
     ========================= */
        $data['this_month_total_reviews'] =
            (clone $baseReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_total_reviews'] =
            (clone $baseReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_guest_review_count'] =
            (clone $guestReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_guest_review_count'] =
            (clone $guestReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_customer_review_count'] =
            (clone $customerReviewQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_customer_review_count'] =
            (clone $customerReviewQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_question_count'] =
            (clone $questionQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_question_count'] =
            (clone $questionQuery)->whereBetween('created_at', $previousMonthRange)->count();

        $data['this_month_tag_count'] =
            (clone $tagQuery)->where('created_at', '>', $thisMonthStart)->count();

        $data['previous_month_tag_count'] =
            (clone $tagQuery)->whereBetween('created_at', $previousMonthRange)->count();

        /* =========================
     | TOTALS (ALL TIME)
     ========================= */
        $data['total_reviews'] = (clone $baseReviewQuery)->count();
        $data['total_guest_review_count'] = (clone $guestReviewQuery)->count();
        $data['total_customer_review_count'] = (clone $customerReviewQuery)->count();
        $data['total_question_count'] = (clone $questionQuery)->count();
        $data['total_tag_count'] = (clone $tagQuery)->count();

        /* =========================
     | MONTHLY CHART DATA
     ========================= */
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $start = $now->copy()->startOfMonth()->subMonths($i);
            $end = $now->copy()->endOfMonth()->subMonths($i);
            $month = $start->format('F');

            $data['monthly_data']['monthly_reviews'][$i] = [
                'month' => $month,
                'value' => (clone $baseReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['guest_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $guestReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['customer_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $customerReviewQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $data['monthly_data']['customers_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $authenticatedCustomerQuery)
                    ->whereBetween('created_at', [$start, $end])
                    ->distinct()
                    ->count(),
            ];
        }

        return $data;
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/customer-report",
     *      operationId="customerDashboardReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get customer report",
     *      description="This method is to get customer report",
     *       @OA\Parameter(
     * name="customer_id",
     * in="query",
     * description="customer_id",
     * required=true,
     * example="0"
     * ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Customer report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
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


    public function customerDashboardReport(Request $request)
    {
        // Get reviews with calculated rating in one query
        $reviews = ReviewNew::with("business", "value")
            ->where([
                "user_id" => $request->customer_id
            ])
            ->globalReviewFilters(0, auth()->user()->business->id)
            ->orderBy('order_no', 'asc')
            ->latest()
            ->withCalculatedRating()
            ->take(5)
            ->get();

        $data["last_five_reviews"] = $reviews;

        return response()->json([
            'success' => true,
            'message' => 'Customer report retrieved successfully',
            'data' => $data
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-report",
     *      operationId="businessDashboardReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business report",
     *      description="This method is to get business report",
     *       @OA\Parameter(
     * name="business_id",
     * in="query",
     * description="business_id",
     * required=true,
     * example="0"
     * ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
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

    public function businessDashboardReport(Request $request)
    {
        // VALIDATE REQUEST
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
        ]);

        $data = Business::with("owner")->where([
            "id" => $request->business_id
        ])->first();

        // CHECK IF BUSINESS EXISTS
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found'
            ], 404);
        }

        // SEND RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Business report retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/dashboard-report/business/get",
     *      operationId="getBusinessReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business report",
     *      description="This method is to get business report",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
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
     *   @OA\Response(
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

    public function getBusinessReport(Request $request)
    {
        $data = [];

        $data["total_businesses"] = Business::get()->count();


        $data["previous_week_total_businesses"] = Business::whereDate('businesses.created_at', '>=', Carbon::now()->subWeek()->startOfWeek())
            ->whereDate('businesses.created_at', '<=', Carbon::now()->subWeek()->endOfWeek())
            ->get()->count();


        $data["this_week_total_businesses"] = Business::whereDate('businesses.created_at', '>=', Carbon::now()->startOfWeek())
            ->whereDate('businesses.created_at', '<=', Carbon::now()->endOfWeek())



            ->get()->count();
        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/v3.0/dashboard-report",
     *      operationId="getDashboardReportV3",
     *      tags={"report"},
     *          @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *          @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format",
     *         required=false,
     *         example="01-12-2025"
     *      ),
     *          @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get dashboard report",
     *      description="This method is to get dashboard report with dynamic date range (default: current month)",


     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
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

    public function getDashboardReportV3(Request $request)
    {
        // Parse and validate date inputs
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $data = [];

        $data['survey'] = $this->generateDashboardReportV2($request, 0, $startDate, $endDate);
        $data['overall'] = $this->generateDashboardReportV2($request, 1, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
            'data' => $data
        ], 200);
    }
}
