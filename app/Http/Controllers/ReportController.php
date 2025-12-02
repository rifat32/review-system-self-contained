<?php

namespace App\Http\Controllers;



use App\Models\Question;
use App\Models\Business;

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{



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

        // 
        $data["last_five_reviews"] = ReviewNew::with("business", "value")->where([
            "user_id" => $request->customer_id
        ])
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->latest()
            ->take(5)
            ->get();

        // SEND RESPONSE
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
     *      path="/v1.0/dashboard-report/{businessId}",
     *      operationId="getDashboardReport",
     *      tags={"report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get dashboard report",
     *      description="This method is to get dashboard report",
     *       @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="0"
     * ),

     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     * ),
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
     *  @OA\Response(
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



    public function getDashboardReport(Request $request, $businessId)
    {

        $data = [];

        $data["today_total_reviews"] = ReviewNew::where([
            "review_news.business_id" => $businessId
        ])->whereDate('created_at', Carbon::today())
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->get()
            ->count();

        $data["this_month_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])->globalFilters()
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->orderBy('order_no', 'asc')
            ->get()->count();

        $data["total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->globalFilters()

            ->get()->count();

        $data["previous_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->globalFilters()
            ->get()
            ->count();

        $data["this_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->globalFilters()
            ->get()
            ->count();

        // @@@@@@@@@@@@@@@@@@@@@@@@@ star
        // @@@@@@@@@@@@@@@@@@@@@@@@@ star
        // @@@@@@@@@@@@@@@@@@@@@@@@@ star
        $total_stars_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $businessId
            ])
            ->select("review_value_news.star_id")
            ->distinct()
            ->get();

        foreach ($total_stars_selected as $key => $star_selected) {
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->first();

            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "star_id" => $star_selected->star_id
                ])

                ->get()
                ->count();
            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->get()
                ->count();
            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])

                ->get()
                ->count();
        }

        $total_tag_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $businessId
            ])
            ->select("review_value_news.tag_id")
            ->distinct()
            ->get();

        foreach ($total_tag_selected as $key => $tag_selected) {
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->first();

            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "tag_id" =>  $tag_selected->tag_id
                ])

                ->get()
                ->count();
            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->get()
                ->count();
            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])

                ->get()
                ->count();

            $data["selected_tags"][$key]["tag_selected_time_this_month"] =       ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where([
                    "review_news.business_id" => $businessId,
                    "tag_id" =>  $tag_selected->tag_id
                ])

                ->where('review_value_news.created_at', '>', now()->subDays(30)->endOfDay())
                ->get()
                ->count();
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
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


        $data["previous_week_total_businesses"] = Business::whereBetween(
            'businesses.created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )
            ->get()->count();


        $data["this_week_total_businesses"] = Business::whereBetween('businesses.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
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
            ? Carbon::createFromFormat('d-m-Y', $request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::createFromFormat('d-m-Y', $request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $data = [];

        $data['survey'] = $this->generateDashboardReport(0, $startDate, $endDate);   // Normal survey (is_overall = 0)
        $data['overall'] = $this->generateDashboardReport(1, $startDate, $endDate);  // Overall report (is_overall = 1)


        return response()->json([
            'success' => true,
            'message' => 'Dashboard report retrieved successfully',
            'data' => $data
        ], 200);
    }


    private function generateDashboardReport($is_overall, $startDate, $endDate)
    {

        // Get the business ID from the request
        $businessId = request()->businessId;
        $data = [];

        // Get the current date and time
        $now = Carbon::now();

        // Calculate the total number of months between start and end dates
        $numberOfMonths = $startDate->diffInMonths($endDate);

        // Loop through each month (from current going backwards)
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Get the start date of the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);

            // Get the end date of the same month
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);

            // Format the month name (e.g., January, February)
            $month = $startDateOfMonth->format('F');

            // Store the month name in the data array
            $data["monthly_data"]["monthly_reviews"][$i]["month"] = $month;

            // Count the number of reviews created in that month
            // If user is not a superadmin, filter by their business ID
            $data["monthly_data"]["monthly_reviews"][$i]["value"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "review_news.business_id" => $businessId
            ] : []))
                ->where("status", "published")
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->globalFilters()
                ->count();
        }



        // Count total reviews created today
        $data["today_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "review_news.business_id" => $businessId
        ] : []))
            ->whereDate('created_at', Carbon::today())
            ->filterByOverall($is_overall)
            ->where("status", "published")
            ->globalFilters()
            ->count();

        // Count total reviews created within the last 30 days (approximate current month)
        $data["this_month_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay()) // Filter reviews created in the last 30 days
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->count();




        // Count total reviews from the previous month (between 30 and 60 days ago)
        $data["previous_month_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)] // Date range for the previous month
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->count();

        // Count total reviews overall (all-time count)
        $data["total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->filterByOverall($is_overall)
            ->where("status", "published")
            ->globalFilters()
            ->count();

        // Count total reviews from the previous week (last full week)
        $data["previous_week_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()] // Start and end of last week
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->count();


        // Count total reviews created in the current week (from Monday to Sunday)
        $data["this_week_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]) // Filter by current week range
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->count();

        // Get distinct star ratings selected in reviews
        $total_stars_selected = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->where("review_news.status", "published")
            ->filterByOverall($is_overall)
            ->select("review_value_news.star_id") // Select only the star_id field
            ->distinct() // Ensure only unique star IDs are fetched
            ->get();




        // Loop through each distinct star selected
        foreach ($total_stars_selected as $key => $star_selected) {
            // Get the star details from the Star table
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this star was selected overall
            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Loop through each month to get monthly star selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["month"] = $month;

                // Count times this star was selected in the given month
                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["value"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                    ->where("review_news.status", "published")
                    ->where([
                        "star_id" => $star_selected->star_id
                    ])
                    ->whereBetween('review_value_news.created_at', [$startDateOfMonth, $endDateOfMonth])
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Count times this star was selected in the previous week
            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this star was selected in the current week
            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->get()
                ->count();
        }




        // Get all distinct tags selected in reviews
        $total_tag_selected = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->where("review_news.status", "published") // Filter by business ID if user is not superadmin
            ->select("review_value_news.tag_id")
            ->filterByOverall($is_overall)
            ->distinct() // Ensure only unique tag IDs are retrieved
            ->get(); // Execute the query and get the results


        // Loop through each distinct tag selected
        foreach ($total_tag_selected as $key => $tag_selected) {
            // Get the tag details from the Tag table
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->filterByOverall($is_overall)
                ->first();

            // Count total times this tag was selected overall
            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Loop through each month to get monthly tag selection counts
            for ($i = 0; $i <= $numberOfMonths; $i++) {
                // Start and end dates for the month (i months ago)
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                // Store the month name
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["month"] = $month;

                // Count times this tag was selected in the given month
                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["value"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                    ->where("review_news.status", "published")
                    ->where([
                        "tag_id" =>  $tag_selected->tag_id
                    ])
                    ->whereBetween(
                        'review_value_news.created_at',
                        [$startDateOfMonth, $endDateOfMonth]
                    )
                    ->filterByOverall($is_overall)
                    ->count();
            }

            // Store tag ID for reference
            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;

            // Count times this tag was selected in the previous week
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->filterByOverall($is_overall)
                ->count();

            // Count times this tag was selected in the current week
            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Count times this tag was selected in the last 30 days (approximate current month)
            $data["selected_tags"][$key]["tag_selected_time_this_month"] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->where('review_value_news.created_at', '>', now()->subDays(30)->endOfDay())
                ->filterByOverall($is_overall)
                ->get()
                ->count();
        }


        // Loop through each month to store month names for customer monthly data
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Start and end dates for the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);

            // Format the month name (e.g., January, February)
            $month = $startDateOfMonth->format('F');

            // Store the month name in the customers_monthly array
            $data["monthly_data"]["customers_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["customers_monthly"][$i]["value"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "review_news.business_id" => $businessId
            ] : []))
                ->whereBetween(
                    'created_at',
                    [$startDateOfMonth, $endDateOfMonth]
                )
                ->whereNotNull('user_id')
                ->where("status", "published")
                ->globalFilters()
                ->distinct()
                ->count();
        }

        // Loop through each month to calculate guest review counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Start and end dates for the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);

            // Format the month name
            $month = $startDateOfMonth->format('F');

            // Store the month name in the guest_review_count_monthly array
            $data["monthly_data"]["guest_review_count_monthly"][$i]["month"] = $month;

            // Count reviews created by guests (user_id is NULL) in the given month
            $data["monthly_data"]["guest_review_count_monthly"][$i]["value"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->where("status", "published")
                ->globalFilters()
                ->get()
                ->count();
        }



        // Count guest reviews created in the last 30 days (approximate current month)
        $data["this_month_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->count();

        // Count guest reviews from the previous month (between 30 and 60 days ago)
        $data["previous_month_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count guest reviews created in the current week (Monday to Sunday)
        $data["this_week_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();


        // Count guest reviews created in the previous week
        $data["previous_week_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count total guest reviews (all-time)
        $data["total_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Prepare daily guest review data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters()
                ->where("status", "published")
                ->get()
                ->count();

            // Store total guest reviews for the day
            $data["this_week_guest_review"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_week_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily guest review data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters()
                ->where("status", "published")
                ->get()
                ->count();

            // Store total guest reviews for the day
            $data["this_month_guest_review"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_month_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }



        // Loop through each month to calculate customer review counts (excluding guests)
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Start and end dates for the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            // Store the month name
            $data["monthly_data"]["customer_review_count_monthly"][$i]["month"] = $month;

            // Count customer reviews in the given month (guest_id is NULL)
            $data["monthly_data"]["customer_review_count_monthly"][$i]["value"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->globalFilters()
                ->where("status", "published")
                ->count();
        }

        // Count customer reviews for the current month (last 30 days)
        $data["this_month_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count customer reviews for the previous month (between 30 and 60 days ago)
        $data["previous_month_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count customer reviews for the current week
        $data["this_week_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count customer reviews for the previous week
        $data["previous_week_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();

        // Count total customer reviews (all-time, excluding guests)
        $data["total_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->filterByOverall($is_overall)
            ->globalFilters()
            ->where("status", "published")
            ->get()
            ->count();




        // Prepare daily customer review data for the current week (last 7 days, excluding guests)
        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->globalFilters()
                ->where("status", "published")
                ->get()
                ->count();

            // Store total customer reviews for the day
            $data["this_week_customer_review"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_week_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily customer review data for the current month (last 30 days, excluding guests)
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->where("status", "published")
                ->globalFilters()
                ->get()
                ->count();

            // Store total customer reviews for the day
            $data["this_month_customer_review"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_month_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate question counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Start and end dates for the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            // Store the month name
            $data["monthly_data"]["question_count_monthly"][$i]["month"] = $month;

            // Count questions created in the given month
            $data["monthly_data"]["question_count_monthly"][$i]["value"] = Question::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->count();
        }

        // Count questions created in the last 30 days (approximate current month)
        $data["this_month_question_count"] = Question::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->count();




        // Count questions from the previous month (between 30 and 60 days ago)
        $data["previous_month_question_count"] = Question::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
            )
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count questions created in the current week
        $data["this_week_question_count"] = Question::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count questions created in the previous week
        $data["previous_week_question_count"] = Question::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count total questions (all-time)
        $data["total_question_count"] = Question::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Prepare daily question data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Question::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Store total questions for the day
            $data["this_week_question"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_week_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily question data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Question::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Store total questions for the day
            $data["this_month_question"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_month_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Loop through each month to calculate tag counts
        for ($i = 0; $i <= $numberOfMonths; $i++) {
            // Start and end dates for the month (i months ago)
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            // Store the month name
            $data["monthly_data"]["tag_count"][$i]["month"] = $month;

            // Count tags created in the given month
            $data["monthly_data"]["tag_count"][$i]["value"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->filterByOverall($is_overall)
                ->get()
                ->count();
        }


        // Count tags created in the current month (last 30 days)
        $data["this_month_tag_count"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count tags created in the previous month (between 30 and 60 days ago)
        $data["previous_month_tag_count"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
            )
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count tags created in the current week
        $data["this_week_tag_count"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count tags created in the previous week
        $data["previous_week_tag_count"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Count total tags (all-time)
        $data["total_tag_count"] = Tag::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->filterByOverall($is_overall)
            ->get()
            ->count();

        // Prepare daily tag data for the current week (last 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $customer = Tag::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Store total tags for the day
            $data["this_week_tag"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_week_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        // Prepare daily tag data for the current month (last 30 days)
        for ($i = 0; $i <= 29; $i++) {
            $customer = Tag::where((!request()->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))
                ->whereDate('created_at', Carbon::today()->subDay($i))
                ->filterByOverall($is_overall)
                ->get()
                ->count();

            // Store total tags for the day
            $data["this_month_tag"][$i]["total"] =  $customer;

            // Store the date for reference
            $data["this_month_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }



        // ----------------------------
        // New Reports Enhancement
        // ----------------------------

        // 1️⃣ Review Growth Rate
        // ----------------------------
        // Clone base review query with business filter if not superadmin
        $review_query = ReviewNew::when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->where("status", "published")
            ->globalFilters()
            ->orderBy('order_no', 'asc')
            ->filterByOverall($is_overall);

        // Count previous month reviews
        $previous_month_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();

        // Count this month reviews
        $this_month_reviews = $data['this_month_total_reviews'] ?? (clone $review_query)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        // Calculate monthly review growth rate
        $data['review_growth_rate_month'] = $previous_month_reviews > 0
            ? round((($this_month_reviews - $previous_month_reviews) / $previous_month_reviews) * 100, 2)
            : 0;

        // Count previous week reviews
        $previous_week_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();

        // Count this week reviews
        $this_week_reviews = $data['this_week_total_reviews'] ?? (clone $review_query)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        // Calculate weekly review growth rate
        $data['review_growth_rate_week'] = $previous_week_reviews > 0
            ? round((($this_week_reviews - $previous_week_reviews) / $previous_week_reviews) * 100, 2)
            : 0;

        // ----------------------------
        // 2️⃣ Review Source Breakdown
        // ----------------------------
        // Get all distinct sources and count reviews for each
        $sources = (clone $review_query)->distinct()->pluck('source');
        $data['review_source_breakdown'] = $sources->map(fn($source) => [
            'source' => $source,
            'total'  => (clone $review_query)->where('source', $source)->count()
        ]);

        // ----------------------------
        // 3️⃣ Review Response Time (average in hours)
        // ----------------------------
        // Calculate average response time for responded reviews
        $responses = (clone $review_query)->whereNotNull('responded_at')->get();
        $data['average_response_time_hours'] = $responses->count() > 0
            ? round($responses->avg(fn($r) => \Carbon\Carbon::parse($r->responded_at)->diffInHours($r->created_at)), 2)
            : 0;

        // ----------------------------
        // 4️⃣ Review Language Distribution
        // ----------------------------
        // Count reviews by language
        $languages = (clone $review_query)->distinct()->pluck('language');
        $data['review_language_distribution'] = $languages->map(fn($lang) => [
            'language' => $lang,
            'total'    => (clone $review_query)->where('language', $lang)->count()
        ]);

        // ----------------------------
        // ⭐ Star Rating Enhancements
        // ----------------------------

        // Average Star Rating
        $avg_ratings = [
            'today' => (clone $review_query)->whereDate('created_at', now())->avg('rate'),
            'this_week' => (clone $review_query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->avg('rate'),
            'this_month' => (clone $review_query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->avg('rate')
        ];
        // Round averages and handle null
        $data['average_star_rating'] = array_map(fn($r) => round($r ?? 0, 2), $avg_ratings);

        // Star Rating Distribution
        $total_reviews = (clone $review_query)->count();
        $data['star_rating_distribution'] = collect(range(5, 1))->mapWithKeys(fn($i) => [
            $i => $total_reviews ? round((clone $review_query)->where('rate', $i)->count() / $total_reviews * 100, 2) : 0
        ])->toArray();

        // Star Rating vs Benchmark
        $industry_benchmark_avg = 4.3;
        $data['star_rating_vs_benchmark'] = [
            'this_month_avg' => round($avg_ratings['this_month'], 2),
            'industry_benchmark' => $industry_benchmark_avg,
            'difference' => round($avg_ratings['this_month'] - $industry_benchmark_avg, 2)
        ];

        // Weighted Star Rating
        $weights = ['verified' => 1.5, 'guest' => 1];
        // Weighted sum of ratings
        $weighted_sum = $review_query->get()->sum(fn($r) => $r->user_id ? $r->rating * $weights['verified'] : $r->rating * $weights['guest']);
        // Total weight for averaging
        $total_weight = $review_query->get()->sum(fn($r) => $r->user_id ? $weights['verified'] : $weights['guest']);
        $data['weighted_star_rating'] = $total_weight ? round($weighted_sum / $total_weight, 2) : 0;

        // Low-Rating Alerts
        $low_rating_this_week = (clone $review_query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->whereIn('rate', [1, 2])->count();
        $low_rating_last_week = (clone $review_query)->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->whereIn('rate', [1, 2])->count();
        // Calculate increase percent and determine alert
        $low_rating_increase = $low_rating_last_week ? round(($low_rating_this_week - $low_rating_last_week) / $low_rating_last_week * 100, 2) : ($low_rating_this_week ? 100 : 0);

        $data['low_rating_alert'] = [
            'this_week_low_ratings' => $low_rating_this_week,
            'last_week_low_ratings' => $low_rating_last_week,
            'increase_percent' => $low_rating_increase,
            'alert' => $low_rating_increase >= 30
        ];

        // ----------------------------
        // 🏷️ Tag Report Enhancements
        // ----------------------------

        // Get all tags per review
        $tags_with_reviews = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->where("review_news.status", "published")
            ->filterByOverall($is_overall)
            ->select('review_news.id as review_id', 'review_value_news.tag_id')
            ->get()
            ->groupBy('review_id');

        // Calculate co-occurrence of tags in the same review
        $tag_co_occurrence = [];
        foreach ($tags_with_reviews as $review_tags) {
            $tag_ids = $review_tags->pluck('tag_id')->toArray();
            foreach ($tag_ids as $tag1) {
                foreach ($tag_ids as $tag2) {
                    if ($tag1 != $tag2) $tag_co_occurrence[$tag1][$tag2] = ($tag_co_occurrence[$tag1][$tag2] ?? 0) + 1;
                }
            }
        }
        $data['tag_co_occurrence'] = $tag_co_occurrence;

        // Calculate impact of each tag on average rating
        $all_tags = Tag::when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        $data['tag_impact_on_ratings'] = $all_tags->mapWithKeys(fn($tag) => [
            $tag->id => round(
                ReviewNew::leftJoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
                    ->where('review_value_news.tag_id', $tag->id)
                    ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                    ->filterByOverall($is_overall)
                    ->globalFilters()
                    ->where("status", "published")
                    ->orderBy('order_no', 'asc')
                    ->avg('review_news.rate') ?? 0,
                2
            )
        ])->toArray();


        // ----------------------------
        // ❓ Question Report Enhancements
        // ----------------------------
        // Get all questions for the business (or all if superadmin)
        $questions = Question::when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))
            ->filterByOverall($is_overall)
            ->get();

        // Total users/reviews to calculate completion rates
        $total_users = $review_query->count();

        // Calculate completion rate per question
        $data['question_completion_rate'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => [
                'question_text' => $qst->text,
                'completion_rate' => $total_users ? round(
                    ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                        ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                        ->where("review_news.status", "published")
                        ->where('question_id', $qst->id)
                        ->filterByOverall($is_overall)
                        ->count() / $total_users * 100,
                    2
                ) : 0
            ]
        ])->toArray();

        // Calculate total responses per question
        $data['average_response_per_question'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                ->where("review_news.status", "published")
                ->where('question_id', $qst->id)

                ->filterByOverall($is_overall)
                ->count()
        ])->toArray();

        // Response distribution per question option
        $data['response_distribution'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => collect($qst->options ?? [])->mapWithKeys(fn($opt) => [
                $opt => ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                    ->where("review_news.status", "published")
                    ->where('question_id', $qst->id)
                    ->where('answer', $opt)
                    ->filterByOverall($is_overall)
                    ->count()
            ])->toArray()
        ])->toArray();

        // ----------------------------
        // 📊 Dashboard Trends Enhancements
        // ----------------------------
        // Total reviews and average star rating
        $total_review_count = $review_query->count();
        $avg_star = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->where("review_news.status", "published")
            ->filterByOverall($is_overall)
            ->avg('star_id');

        // Calculate engagement index and performance vs target
        $data['dashboard_trends'] = [
            'engagement_index' => round($total_review_count * ($avg_star ?? 0), 2),
            'performance_vs_target' => round(($total_review_count / 100) * 100, 2), // assuming 100 target
            // Reviews by hour of day (0–23)
            'time_of_day_trends' => collect(range(0, 23))
                ->mapWithKeys(function ($h) use ($review_query) {
                    return [$h => (clone $review_query)
                        ->whereRaw('HOUR(created_at) = ?', [$h])
                        ->count()];
                })
                ->toArray(),
            // Reviews by day of week (0 = Sunday, 6 = Saturday)
            'day_of_week_trends' => collect(range(0, 6))
                ->mapWithKeys(function ($d) use ($review_query) {
                    return [$d => (clone $review_query)
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [$d + 1]) // MySQL DAYOFWEEK returns 1–7
                        ->count()];
                })
                ->toArray(),
        ];


        // ----------------------------
        // 📈 Advanced Insights
        // ----------------------------

        // Calculate customer retention rate based on repeat reviewers
        $reviewers = $review_query->pluck('user_id')->filter();
        $repeat_reviewers_count = $reviewers->countBy()->filter(fn($c) => $c > 1)->count();
        $total_customers = $reviewers->unique()->count();
        $data['advanced_insights']['customer_retention_rate'] = $total_customers ? round($repeat_reviewers_count / $total_customers * 100, 2) : 0;

        // Topic/tag analysis: count of reviews per tag
        $data['advanced_insights']['topic_analysis'] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!request()->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->where("review_news.status", "published")
            ->filterByOverall($is_overall)
            ->select('tag_id', DB::raw('count(*) as total'))
            ->groupBy('tag_id')
            ->get()
            ->map(fn($t) => [
                'tag_id' => $t->tag_id,
                'count' => $t->total,
                'tag_name' => Tag::find($t->tag_id)?->name
            ]);

        // Monthly review trend: total reviews per month
        $data['advanced_insights']['monthly_review_trend'] = $review_query
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'))
            ->groupBy('month')
            ->pluck('total', 'month');

        // Response effectiveness: average star before and after replies
        $review_with_replies = $review_query->whereNotNull('responded_at')->get();
        $data['advanced_insights']['response_effectiveness'] = [
            'before_reply_avg' => round($review_with_replies->avg('star_before_reply') ?? 0, 2),
            'after_reply_avg' => round($review_with_replies->avg('star_after_reply') ?? 0, 2)
        ];


        return $data;
    }





















    /**
     * @OA\Get(
     *      path="/v1.0/reports/staff-comparison/{businessId}",
     *      operationId="staffComparison",
     *      tags={"Reports"},
     *      summary="Compare two staff members performance",
     *      description="Get detailed comparison between two staff members",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="staff_a_id",
     *          in="query",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="staff_b_id",
     *          in="query",
     *          required=true,
     *          example="2"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Staff comparison data retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="business_name", type="string", example="Business Name"),
     *                  @OA\Property(property="comparison", type="object",
     *                      @OA\Property(property="rating_gap", type="number", example=0.5),
     *                      @OA\Property(property="rating_gap_message", type="string", example="Staff A is performing better"),
     *                      @OA\Property(property="sentiment_gap", type="number", example=10),
     *                      @OA\Property(property="sentiment_gap_message", type="string", example="Staff A has more positive reviews"),
     *                      @OA\Property(property="better_performer", type="string", example="John Doe")
     *                  ),
     *                  @OA\Property(property="staff_a", type="object"),
     *                  @OA\Property(property="staff_b", type="object")
     *              )
     *          )
     *       ),
     *      @OA\Response(response=404, description="Not Found")
     * )
     */
    public function staffComparison($businessId, Request $request)
    {
        $request->validate([
            'staff_a_id' => 'required|integer|exists:users,id',
            'staff_b_id' => 'required|integer|exists:users,id'
        ]);

        $business = Business::findOrFail($businessId);
        $staffAId = $request->staff_a_id;
        $staffBId = $request->staff_b_id;

        // Get staff user details
        $staffA = User::findOrFail($staffAId);
        $staffB = User::findOrFail($staffBId);

        // Get reviews for both staff
        $staffAReviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffAId)
            ->whereNotNull('sentiment_score')
            ->get();

        $staffBReviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffBId)
            ->whereNotNull('sentiment_score')
            ->get();

        // Calculate metrics for Staff A
        $staffAMetrics = $this->calculateStaffMetrics($staffAReviews, $staffA);

        // Calculate metrics for Staff B
        $staffBMetrics = $this->calculateStaffMetrics($staffBReviews, $staffB);

        // Calculate gaps
        $ratingGap = round($staffAMetrics['avg_rating'] - $staffBMetrics['avg_rating'], 1);
        $sentimentGap = $staffAMetrics['sentiment_breakdown']['positive'] - $staffBMetrics['sentiment_breakdown']['positive'];

        return response()->json([
            "success" => true,
            "message" => "Staff comparison data retrieved successfully",
            "data" => [
                'business_id' => (int)$businessId,
                'business_name' => $business->name,
                'comparison' => [
                    'rating_gap' => $ratingGap,
                    'rating_gap_message' => $this->getRatingGapMessage($ratingGap),
                    'sentiment_gap' => $sentimentGap,
                    'sentiment_gap_message' => $this->getSentimentGapMessage($sentimentGap),
                    'better_performer' => $ratingGap >= 0 ? $staffA->name : $staffB->name
                ],
                'staff_a' => $staffAMetrics,
                'staff_b' => $staffBMetrics
            ]
        ], 200);
    }

    private function calculateStaffMetrics($reviews, $staffUser)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return $this->emptyStaffMetrics($staffUser);
        }

        // Calculate average rating
        $avgRating = round($reviews->avg('rate'), 1);

        // Calculate sentiment distribution
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        $positivePercentage = round(($positiveCount / $totalReviews) * 100);
        $neutralPercentage = round(($neutralCount / $totalReviews) * 100);
        $negativePercentage = round(($negativeCount / $totalReviews) * 100);

        // Extract topics and categories
        $topics = $this->extractTopicsFromReviews($reviews);
        $performanceByCategory = $this->calculatePerformanceByCategory($reviews);
        $notableReviews = $this->getNotableReviews($reviews);

        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => $totalReviews,
            'avg_rating' => $avgRating,
            'sentiment_breakdown' => [
                'positive' => $positivePercentage,
                'neutral' => $neutralPercentage,
                'negative' => $negativePercentage
            ],
            'performance_by_category' => $performanceByCategory,
            'top_topics' => array_slice($topics, 0, 5),
            'notable_reviews' => $notableReviews
        ];
    }

    private function emptyStaffMetrics($staffUser)
    {
        return [
            'id' => $staffUser->id,
            'name' => $staffUser->name,
            'job_title' => $staffUser->job_title ?? 'Staff',
            'email' => $staffUser->email,
            'total_reviews' => 0,
            'avg_rating' => 0,
            'sentiment_breakdown' => [
                'positive' => 0,
                'neutral' => 0,
                'negative' => 0
            ],
            'performance_by_category' => [],
            'top_topics' => [],
            'notable_reviews' => []
        ];
    }

    private function extractTopicsFromReviews($reviews)
    {
        $allTopics = [];

        foreach ($reviews as $review) {
            if ($review->topics && is_array($review->topics)) {
                foreach ($review->topics as $topic) {
                    $allTopics[$topic] = ($allTopics[$topic] ?? 0) + 1;
                }
            }
        }

        arsort($allTopics);
        return $allTopics;
    }

    private function calculatePerformanceByCategory($reviews)
    {
        $categories = [
            'friendliness' => ['friendly', 'polite', 'rude', 'attitude', 'nice'],
            'efficiency' => ['slow', 'fast', 'efficient', 'wait', 'time'],
            'knowledge' => ['knowledge', 'explain', 'information', 'helpful', 'expert']
        ];

        $performance = [];

        foreach ($categories as $category => $keywords) {
            $categoryReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($categoryReviews->count() > 0) {
                $avgSentiment = $categoryReviews->avg('sentiment_score');
                $performance[$category] = [
                    'score' => round($avgSentiment * 100),
                    'review_count' => $categoryReviews->count()
                ];
            } else {
                $performance[$category] = [
                    'score' => 0,
                    'review_count' => 0
                ];
            }
        }

        return $performance;
    }

    private function getNotableReviews($reviews, $limit = 2)
    {
        return $reviews->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                return [
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans()
                ];
            })
            ->values()
            ->toArray();
    }

    private function getRatingGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A is performing better";
        } elseif ($gap < 0) {
            return "Staff B is performing better";
        } else {
            return "Both staff are performing equally";
        }
    }

    private function getSentimentGapMessage($gap)
    {
        if ($gap > 0) {
            return "Staff A has more positive reviews";
        } elseif ($gap < 0) {
            return "Staff B has more positive reviews";
        } else {
            return "Both have similar positive sentiment";
        }
    }


    /**
     * @OA\Get(
     *      path="/v1.0/reports/staff-performance/{businessId}/{staffId}",
     *      operationId="staffPerformance",
     *      tags={"Reports"},
     *      summary="Get detailed staff performance report",
     *      description="Get comprehensive performance analysis for a staff member",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="staffId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Staff performance report retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="staff_profile", type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="job_title", type="string", example="Staff"),
     *                      @OA\Property(property="email", type="string", example="john@example.com"),
     *                      @OA\Property(property="tenure", type="string", example="2 years 3 months"),
     *                      @OA\Property(property="join_date", type="string", format="date", example="2022-01-15")
     *                  ),
     *                  @OA\Property(property="performance_summary", type="object",
     *                      @OA\Property(property="total_reviews", type="integer", example=25),
     *                      @OA\Property(property="avg_rating", type="number", example=4.2),
     *                      @OA\Property(property="sentiment_distribution", type="object",
     *                          @OA\Property(property="positive", type="integer", example=60),
     *                          @OA\Property(property="neutral", type="integer", example=25),
     *                          @OA\Property(property="negative", type="integer", example=15)
     *                      )
     *                  ),
     *                  @OA\Property(property="rating_trend", type="object",
     *                      @OA\Property(property="period", type="string", example="last_6_months"),
     *                      @OA\Property(property="data", type="object", example={"2023-01": 4.0, "2023-02": 4.2}),
     *                      @OA\Property(property="trend_direction", type="string", example="improving")
     *                  ),
     *                  @OA\Property(property="review_samples", type="object",
     *                      @OA\Property(property="positive", type="array", @OA\Items(type="object")),
     *                      @OA\Property(property="constructive", type="array", @OA\Items(type="object")),
     *                      @OA\Property(property="neutral", type="array", @OA\Items(type="object"))
     *                  ),
     *                  @OA\Property(property="recommended_training", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="skill_gap_analysis", type="object",
     *                      @OA\Property(property="strengths", type="array", @OA\Items(type="string")),
     *                      @OA\Property(property="improvement_areas", type="array", @OA\Items(type="string"))
     *                  ),
     *                  @OA\Property(property="customer_perceived_tone", type="object",
     *                      @OA\Property(property="friendliness", type="integer", example=75),
     *                      @OA\Property(property="patience", type="integer", example=80),
     *                      @OA\Property(property="professionalism", type="integer", example=85)
     *                  )
     *              )
     *          )
     *       ),
     *      @OA\Response(response=404, description="Not Found")
     * )
     */
    public function staffPerformance($businessId, $staffId)
    {
        $business = Business::findOrFail($businessId);
        $staff = User::findOrFail($staffId);

        // Get all reviews for this staff member
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->whereNotNull('sentiment_score')
            ->get();

        // Calculate tenure
        $tenure = $this->calculateTenure($staff->join_date);

        // Get rating trend
        $ratingTrend = $this->getRatingTrend($reviews);

        // Get review samples by sentiment
        $reviewSamples = $this->getReviewSamples($reviews);

        // Get recommended training
        $recommendedTraining = $this->getRecommendedTraining($reviews, $staff);

        // Get AI skill-gap detection
        $skillGapAnalysis = $this->analyzeSkillGaps($reviews);

        // Get customer-perceived tone
        $customerTone = $this->calculateCustomerTone($reviews);

        return response()->json([
            "success" => true,
            "message" => "Staff performance report retrieved successfully",
            "data" => [
                'staff_profile' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'job_title' => $staff->job_title ?? 'Staff',
                    'email' => $staff->email,
                    'tenure' => $tenure,
                    'join_date' => $staff->join_date
                ],
                'performance_summary' => [
                    'total_reviews' => $reviews->count(),
                    'avg_rating' => round($reviews->avg('rate'), 1),
                    'sentiment_distribution' => $this->calculateSentimentDistribution($reviews)
                ],
                'rating_trend' => $ratingTrend,
                'review_samples' => $reviewSamples,
                'recommended_training' => $recommendedTraining,
                'skill_gap_analysis' => $skillGapAnalysis,
                'customer_perceived_tone' => $customerTone
            ]
        ], 200);
    }

    private function calculateTenure($joinDate)
    {
        if (!$joinDate) {
            return 'Not specified';
        }

        $join = Carbon::parse($joinDate);
        $now = Carbon::now();

        $years = $now->diffInYears($join);
        $months = $now->diffInMonths($join) % 12;

        return "{$years} years {$months} months";
    }

    private function getRatingTrend($reviews)
    {
        // Get last 6 months of ratings
        $sixMonthsAgo = Carbon::now()->subMonths(6);

        $monthlyRatings = $reviews->where('created_at', '>=', $sixMonthsAgo)
            ->groupBy(function ($review) {
                return $review->created_at->format('Y-m');
            })
            ->map(function ($monthReviews) {
                return round($monthReviews->avg('rate'), 1);
            })
            ->sortKeys()
            ->toArray();

        return [
            'period' => 'last_6_months',
            'data' => $monthlyRatings,
            'trend_direction' => $this->calculateTrendDirection($monthlyRatings)
        ];
    }

    private function calculateTrendDirection($monthlyRatings)
    {
        if (count($monthlyRatings) < 2) {
            return 'stable';
        }

        $values = array_values($monthlyRatings);
        $first = $values[0];
        $last = end($values);

        if ($last > $first + 0.3) {
            return 'improving';
        } elseif ($last < $first - 0.3) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    private function getReviewSamples($reviews, $limit = 2)
    {
        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)
            ->sortByDesc('created_at')
            ->take($limit);

        $constructiveReviews = $reviews->whereBetween('sentiment_score', [0.4, 0.69])
            ->sortByDesc('created_at')
            ->take($limit);

        $negativeReviews = $reviews->where('sentiment_score', '<', 0.4)
            ->sortByDesc('created_at')
            ->take($limit);

        return [
            'positive' => $positiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'constructive' => $constructiveReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray(),
            'neutral' => $negativeReviews->map(function ($review) {
                return [
                    'id' => $review->id,
                    'comment' => $review->comment,
                    'sentiment_score' => $review->sentiment_score,
                    'date' => $review->created_at->diffForHumans(),
                    'rating' => $review->rate
                ];
            })->values()->toArray()
        ];
    }

    private function getRecommendedTraining($reviews, $staff)
    {
        $trainingRecommendations = [];

        // Analyze reviews for training needs
        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Check for conflict resolution needs
        if (strpos($textLower, 'escalat') !== false || strpos($textLower, 'conflict') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Advanced Conflict Resolution',
                'description' => 'Recommended based on feedback regarding complex customer escalations.',
                'priority' => 'high',
                'category' => 'communication'
            ];
        }

        // Check for technical knowledge gaps
        if (strpos($textLower, 'technical') !== false || strpos($textLower, 'knowledge') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Technical Product Training',
                'description' => 'Recommended to improve product knowledge and technical expertise.',
                'priority' => 'medium',
                'category' => 'knowledge'
            ];
        }

        // Check for upselling opportunities
        if (strpos($textLower, 'upsell') !== false || strpos($textLower, 'recommend') !== false) {
            $trainingRecommendations[] = [
                'title' => 'Sales and Upselling Techniques',
                'description' => 'Recommended to enhance sales skills and product recommendation abilities.',
                'priority' => 'medium',
                'category' => 'sales'
            ];
        }

        // Default training if no specific needs detected
        if (empty($trainingRecommendations)) {
            $trainingRecommendations[] = [
                'title' => 'Customer Service Excellence',
                'description' => 'General customer service skills enhancement.',
                'priority' => 'low',
                'category' => 'communication'
            ];
        }

        return $trainingRecommendations;
    }

    private function analyzeSkillGaps($reviews)
    {
        $strengths = [];
        $improvement_areas = [];

        $text = $reviews->pluck('comment')->implode(' ');
        $textLower = strtolower($text);

        // Analyze strengths
        if (strpos($textLower, 'communicat') !== false || strpos($textLower, 'explain') !== false) {
            $strengths[] = 'Communication';
        }
        if (strpos($textLower, 'solve') !== false || strpos($textLower, 'resolve') !== false) {
            $strengths[] = 'Problem Solving';
        }
        if (strpos($textLower, 'patient') !== false) {
            $strengths[] = 'Patience';
        }
        if (strpos($textLower, 'professional') !== false) {
            $strengths[] = 'Professionalism';
        }

        // Analyze improvement areas
        if (strpos($textLower, 'technical') !== false && strpos($textLower, 'know') === false) {
            $improvement_areas[] = 'Technical Knowledge';
        }
        if (strpos($textLower, 'upsell') !== false) {
            $improvement_areas[] = 'Upselling';
        }
        if (strpos($textLower, 'slow') !== false) {
            $improvement_areas[] = 'Process Efficiency';
        }

        // Remove duplicates
        $strengths = array_unique($strengths);
        $improvement_areas = array_unique($improvement_areas);

        return [
            'strengths' => array_values($strengths),
            'improvement_areas' => array_values($improvement_areas)
        ];
    }

    private function calculateCustomerTone($reviews)
    {
        $toneMetrics = [
            'friendliness' => ['friendly', 'nice', 'kind', 'pleasant', 'warm'],
            'patience' => ['patient', 'calm', 'understanding', 'tolerant'],
            'professionalism' => ['professional', 'expert', 'knowledgeable', 'competent']
        ];

        $results = [];

        foreach ($toneMetrics as $tone => $keywords) {
            $matchingReviews = $reviews->filter(function ($review) use ($keywords) {
                $text = strtolower($review->raw_text . ' ' . $review->comment);
                foreach ($keywords as $keyword) {
                    if (strpos($text, $keyword) !== false) {
                        return true;
                    }
                }
                return false;
            });

            if ($matchingReviews->count() > 0) {
                $positiveMatches = $matchingReviews->where('sentiment_score', '>=', 0.7)->count();
                $percentage = round(($positiveMatches / $matchingReviews->count()) * 100);
            } else {
                $percentage = 0;
            }

            $results[$tone] = $percentage;
        }

        return $results;
    }

    private function calculateSentimentDistribution($reviews)
    {
        $total = $reviews->count();

        if ($total === 0) {
            return ['positive' => 0, 'neutral' => 0, 'negative' => 0];
        }

        $positive = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutral = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negative = $reviews->where('sentiment_score', '<', 0.4)->count();

        return [
            'positive' => round(($positive / $total) * 100),
            'neutral' => round(($neutral / $total) * 100),
            'negative' => round(($negative / $total) * 100)
        ];
    }



    /**
     * @OA\Get(
     *      path="/v1.0/reports/staff-dashboard/{businessId}",
     *      operationId="staffDashboard",
     *      tags={"Reports"},
     *      summary="Get staff performance dashboard",
     *      description="Get overall staff performance metrics and rankings",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period for comparison: last_week, last_month, last_quarter",
     *          example="last_month"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Staff dashboard report retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="business_name", type="string", example="Business Name"),
     *                  @OA\Property(property="period", type="string", example="last_month"),
     *                  @OA\Property(property="overall_metrics", type="object",
     *                      @OA\Property(property="overall_rating", type="object",
     *                          @OA\Property(property="value", type="number", example=4.2),
     *                          @OA\Property(property="change", type="number", example=5.5),
     *                          @OA\Property(property="change_type", type="string", example="positive")
     *                      ),
     *                      @OA\Property(property="overall_sentiment", type="object",
     *                          @OA\Property(property="value", type="integer", example=75),
     *                          @OA\Property(property="change", type="number", example=2.1),
     *                          @OA\Property(property="change_type", type="string", example="positive")
     *                      ),
     *                      @OA\Property(property="total_reviews", type="object",
     *                          @OA\Property(property="value", type="integer", example=150),
     *                          @OA\Property(property="change", type="integer", example=25),
     *                          @OA\Property(property="change_type", type="string", example="positive")
     *                      )
     *                  ),
     *                  @OA\Property(property="compliment_ratio", type="object",
     *                      @OA\Property(property="compliments_percentage", type="integer", example=70),
     *                      @OA\Property(property="complaints_percentage", type="integer", example=15),
     *                      @OA\Property(property="neutral_percentage", type="integer", example=15),
     *                      @OA\Property(property="compliments_count", type="integer", example=105),
     *                      @OA\Property(property="complaints_count", type="integer", example=22),
     *                      @OA\Property(property="neutral_count", type="integer", example=23)
     *                  ),
     *                  @OA\Property(property="top_staff", type="array", @OA\Items(type="object")),
     *                  @OA\Property(property="all_staff", type="array", @OA\Items(type="object"))
     *              )
     *          )
     *       ),
     *      @OA\Response(response=404, description="Not Found")
     * )
     */
    public function staffDashboard($businessId, Request $request)
    {
        $business = Business::findOrFail($businessId);
        $period = $request->get('period', 'last_month');

        // Get current period reviews
        $currentReviews = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereNotNull('sentiment_score')
            ->get();

        // Get previous period reviews for comparison
        $previousReviews = $this->getPreviousPeriodReviews($businessId, $period);

        // Calculate overall metrics
        $overallMetrics = $this->calculateOverallMetrics($currentReviews, $previousReviews);

        // Calculate compliment vs complaint ratio
        $complimentRatio = $this->calculateComplimentRatio($currentReviews);

        // Get top staff by rating
        $topStaff = $this->getTopStaffByRating($currentReviews);

        // Get all staff with detailed metrics
        $allStaff = $this->getAllStaffMetrics($currentReviews);

        return response()->json([
            'success' => true,
            'message' => 'Staff dashboard report retrieved successfully',
            'data' => [
                'business_id' => (int)$businessId,
                'business_name' => $business->name,
                'period' => $period,
                'overall_metrics' => $overallMetrics,
                'compliment_ratio' => $complimentRatio,
                'top_staff' => $topStaff,
                'all_staff' => $allStaff
            ]
        ], 200);
    }

    private function getPreviousPeriodReviews($businessId, $period)
    {
        $startDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->startOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->startOfQuarter(),
            default => Carbon::now()->subMonth()->startOfMonth() // last_month
        };

        $endDate = match ($period) {
            'last_week' => Carbon::now()->subWeek()->endOfWeek(),
            'last_quarter' => Carbon::now()->subQuarter()->endOfQuarter(),
            default => Carbon::now()->subMonth()->endOfMonth()
        };

        return ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereNotNull('sentiment_score')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
    }

    private function calculateOverallMetrics($currentReviews, $previousReviews)
    {
        // Current period metrics
        $currentAvgRating = round($currentReviews->avg('rate') ?? 0, 1);
        $currentSentiment = $this->calculateAverageSentiment($currentReviews);
        $currentTotalReviews = $currentReviews->count();

        // Previous period metrics
        $previousAvgRating = round($previousReviews->avg('rate') ?? 0, 1);
        $previousSentiment = $this->calculateAverageSentiment($previousReviews);
        $previousTotalReviews = $previousReviews->count();

        // Calculate changes
        $ratingChange = $previousAvgRating > 0 ?
            round((($currentAvgRating - $previousAvgRating) / $previousAvgRating) * 100, 1) : 0;

        $sentimentChange = $previousSentiment > 0 ?
            round($currentSentiment - $previousSentiment, 1) : 0;

        $reviewsChange = $previousTotalReviews > 0 ?
            $currentTotalReviews - $previousTotalReviews : $currentTotalReviews;

        return [
            'overall_rating' => [
                'value' => $currentAvgRating,
                'change' => $ratingChange,
                'change_type' => $ratingChange >= 0 ? 'positive' : 'negative'
            ],
            'overall_sentiment' => [
                'value' => $currentSentiment,
                'change' => $sentimentChange,
                'change_type' => $sentimentChange >= 0 ? 'positive' : 'negative'
            ],
            'total_reviews' => [
                'value' => $currentTotalReviews,
                'change' => $reviewsChange,
                'change_type' => $reviewsChange >= 0 ? 'positive' : 'negative'
            ]
        ];
    }

    private function calculateAverageSentiment($reviews)
    {
        if ($reviews->isEmpty()) {
            return 0;
        }

        $positiveReviews = $reviews->where('sentiment_score', '>=', 0.7)->count();
        return round(($positiveReviews / $reviews->count()) * 100);
    }

    private function calculateComplimentRatio($reviews)
    {
        $totalReviews = $reviews->count();

        if ($totalReviews === 0) {
            return [
                'compliments_percentage' => 0,
                'complaints_percentage' => 0,
                'compliments_count' => 0,
                'complaints_count' => 0
            ];
        }

        $compliments = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $complaints = $reviews->where('sentiment_score', '<', 0.4)->count();
        $neutral = $totalReviews - $compliments - $complaints;

        return [
            'compliments_percentage' => round(($compliments / $totalReviews) * 100),
            'complaints_percentage' => round(($complaints / $totalReviews) * 100),
            'neutral_percentage' => round(($neutral / $totalReviews) * 100),
            'compliments_count' => $compliments,
            'complaints_count' => $complaints,
            'neutral_count' => $neutral
        ];
    }

    private function getTopStaffByRating($reviews, $limit = 5)
    {
        $staffRatings = $reviews->groupBy('staff_id')
            ->map(function ($staffReviews, $staffId) {
                $staff = User::find($staffId);
                return [
                    'staff_id' => $staffId,
                    'staff_name' => $staff ? $staff->name : 'Unknown Staff',
                    'position' => $staff->job_title ?? 'Staff',
                    'avg_rating' => round($staffReviews->avg('rate'), 1),
                    'total_reviews' => $staffReviews->count(),
                    'sentiment_score' => $this->getSentimentLabel($staffReviews->avg('sentiment_score'))
                ];
            })
            ->filter(function ($staff) {
                return $staff['total_reviews'] >= 3; // Minimum reviews to be considered
            })
            ->sortByDesc('avg_rating')
            ->take($limit)
            ->values()
            ->toArray();

        return $staffRatings;
    }

    private function getAllStaffMetrics($reviews)
    {
        $staffMetrics = $reviews->groupBy('staff_id')
            ->map(function ($staffReviews, $staffId) {
                $staff = User::find($staffId);
                if (!$staff) return null;

                $compliments = $staffReviews->where('sentiment_score', '>=', 0.7)->count();
                $complaints = $staffReviews->where('sentiment_score', '<', 0.4)->count();
                $neutral = $staffReviews->count() - $compliments - $complaints;

                return [
                    'staff_id' => $staffId,
                    'staff_name' => $staff->name,
                    'position' => $staff->job_title ?? 'Staff',
                    'avg_rating' => round($staffReviews->avg('rate'), 1),
                    'sentiment_score' => $this->getSentimentLabel($staffReviews->avg('sentiment_score')),
                    'compliments_count' => $compliments,
                    'complaints_count' => $complaints,
                    'neutral_count' => $neutral,
                    'total_reviews' => $staffReviews->count(),
                    'sentiment_numeric' => round($staffReviews->avg('sentiment_score') * 100)
                ];
            })
            ->filter()
            ->sortByDesc('avg_rating')
            ->values()
            ->toArray();

        return $staffMetrics;
    }

    private function getSentimentLabel($sentimentScore)
    {
        if (!$sentimentScore) return 'Neutral';

        if ($sentimentScore >= 0.7) return 'Positive';
        if ($sentimentScore >= 0.4) return 'Neutral';
        return 'Negative';
    }





    /**
     * @OA\Get(
     *      path="/v1.0/reports/review-analytics/{businessId}",
     *      operationId="reviewAnalytics",
     *      tags={"Reports"},
     *      summary="Get review analytics with flexible filtering",
     *      description="Get performance overview and recent submissions with optional filters for survey, guest reviews, user reviews, and overall reviews",
     * @OA\Parameter(
     *     name="min_score",
     *     in="query",
     *     required=false,
     *     description="Minimum rating score (1-5)",
     *     example="3"
     * ),
     * @OA\Parameter(
     *     name="max_score",
     *     in="query",
     *     required=false,
     *     description="Maximum rating score (1-5)",
     *     example="5"
     * ),
     * @OA\Parameter(
     *     name="labels",
     *     in="query",
     *     required=false,
     *     description="Filter by sentiment labels (comma separated)",
     *     example="positive,neutral"
     * ),
     * @OA\Parameter(
     *     name="review_type",
     *     in="query",
     *     required=false,
     *     description="Filter by review type",
     *     example="feedback"
     * ),
     * @OA\Parameter(
     *     name="has_comment",
     *     in="query",
     *     required=false,
     *     description="Filter by comments: true=with comments, false=without comments",
     *     example="true"
     * ),
     * @OA\Parameter(
     *     name="has_reply",
     *     in="query",
     *     required=false,
     *     description="Filter by replies: true=replied, false=not replied",
     *     example="false"
     * ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Review analytics retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="business_name", type="string", example="Business Name"),
     *                  @OA\Property(property="filters_applied", type="object",
     *                      @OA\Property(property="business", type="string", example="Business Name"),
     *                      @OA\Property(property="total_filters", type="integer", example=2),
     *                      @OA\Property(property="period", type="string", example="30d")
     *                  ),
     *                  @OA\Property(property="performance_overview", type="object",
     *                      @OA\Property(property="total_submissions", type="integer", example=150),
     *                      @OA\Property(property="average_score", type="number", example=4.2),
     *                      @OA\Property(property="score_out_of", type="integer", example=5),
     *                      @OA\Property(property="sentiment_distribution", type="object",
     *                          @OA\Property(property="positive", type="integer", example=70),
     *                          @OA\Property(property="neutral", type="integer", example=20),
     *                          @OA\Property(property="negative", type="integer", example=10)
     *                      ),
     *                      @OA\Property(property="submissions_today", type="integer", example=5),
     *                      @OA\Property(property="submissions_this_week", type="integer", example=25),
     *                      @OA\Property(property="submissions_this_month", type="integer", example=85),
     *                      @OA\Property(property="guest_reviews_count", type="integer", example=45),
     *                      @OA\Property(property="user_reviews_count", type="integer", example=105),
     *                      @OA\Property(property="overall_reviews_count", type="integer", example=75),
     *                      @OA\Property(property="survey_reviews_count", type="integer", example=75)
     *                  ),
     *                  @OA\Property(property="submissions_over_time", type="object",
     *                      @OA\Property(property="period", type="string", example="30d"),
     *                      @OA\Property(property="data", type="object", example={"2023-11-01": {"submissions_count": 5, "average_rating": 4.2, "sentiment_score": 75.5}}),
     *                      @OA\Property(property="total_submissions", type="integer", example=150),
     *                      @OA\Property(property="peak_submissions", type="integer", example=10),
     *                      @OA\Property(property="date_range", type="object",
     *                          @OA\Property(property="start", type="string", format="date", example="2023-10-31"),
     *                          @OA\Property(property="end", type="string", format="date", example="2023-11-30")
     *                      )
     *                  ),
     *                  @OA\Property(property="recent_submissions", type="array", @OA\Items(type="object"))
     *              )
     *          )
     *       ),
     *      @OA\Response(response=404, description="Not Found")
     * )
     */
    public function reviewAnalytics($businessId, Request $request)
    {
        $business = Business::findOrFail($businessId);

        $filters = [
            'survey_id' => $request->get('survey_id'),
            'is_guest_review' => $request->get('is_guest_review'),
            'is_user_review' => $request->get('is_user_review'),
            'is_overall' => $request->get('is_overall'),
            'staff_id' => $request->get('staff_id'),
            'period' => $request->get('period', '30d'),
            'min_score' => $request->get('min_score'),
            'max_score' => $request->get('max_score'),
            'labels' => $request->get('labels'),
            'review_type' => $request->get('review_type'),
            'has_comment' => $request->get('has_comment'),
            'has_reply' => $request->get('has_reply')
        ];

        // Build query with filters
        $reviewsQuery = ReviewNew::where('business_id', $businessId)
            ->with(['user', 'guest_user', 'survey']);

        // Apply filters
        $reviewsQuery = $this->applyFilters($reviewsQuery, $filters);

        $reviews = $reviewsQuery->get();

        // Calculate performance overview
        $performanceOverview = $this->calculatePerformanceOverview($reviews);

        // Get submissions over time
        $submissionsOverTime = $this->getSubmissionsOverTime($reviews, $filters['period']);

        // Get recent submissions
        $recentSubmissions = $this->getRecentSubmissions($reviews);

        // Get filter summary
        $filterSummary = $this->getFilterSummary($filters, $business);

        return response()->json([
            'success' => true,
            'message' => 'Review analytics retrieved successfully',
            'data' => [
                'business_id' => (int)$businessId,
                'business_name' => $business->name,
                'filters_applied' => $filterSummary,
                'performance_overview' => $performanceOverview,
                'submissions_over_time' => $submissionsOverTime,
                'recent_submissions' => $recentSubmissions
            ]
        ], 200);
    }

    private function applyFilters($query, $filters)
    {
        // Survey filter
        if (!empty($filters['survey_id'])) {
            $query->where('survey_id', $filters['survey_id']);
        }

        // Guest reviews filter
        if (isset($filters['is_guest_review']) && $filters['is_guest_review'] === 'true') {
            $query->whereNotNull('guest_id');
        }

        // User reviews filter
        if (isset($filters['is_user_review']) && $filters['is_user_review'] === 'true') {
            $query->whereNotNull('user_id');
        }

        // Overall reviews filter
        if (isset($filters['is_overall']) && $filters['is_overall'] === 'true') {
            $query->where('is_overall', 1);
        } elseif (isset($filters['is_overall']) && $filters['is_overall'] === 'false') {
            $query->where('is_overall', 0);
        }

        // Staff filter
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        // Score range filter
        if (!empty($filters['min_score'])) {
            $query->where('rate', '>=', $filters['min_score']);
        }
        if (!empty($filters['max_score'])) {
            $query->where('rate', '<=', $filters['max_score']);
        }

        // Labels filter (using sentiment field)
        if (!empty($filters['labels'])) {
            $labels = is_array($filters['labels']) ? $filters['labels'] : explode(',', $filters['labels']);
            $query->whereHas('value', function ($q) use ($labels) {
                $q->whereIn('review_value_news.tag_id', $labels);
            });
        }

        // Review type filter (using review_type field)
        if (!empty($filters['review_type'])) {
            $query->where('review_type', $filters['review_type']);
        }

        // With comment or without comment
        if (isset($filters['has_comment']) && $filters['has_comment'] === 'true') {
            $query->whereNotNull('comment')->where('comment', '!=', '');
        } elseif (isset($filters['has_comment']) && $filters['has_comment'] === 'false') {
            $query->where(function ($q) {
                $q->whereNull('comment')->orWhere('comment', '');
            });
        }

        // Replied - yes or no
        if (isset($filters['has_reply']) && $filters['has_reply'] === 'true') {
            $query->whereNotNull('responded_at');
        } elseif (isset($filters['has_reply']) && $filters['has_reply'] === 'false') {
            $query->whereNull('responded_at');
        }

        return $query;
    }

    private function getFilterSummary($filters, $business)
    {
        $summary = [
            'business' => $business->name,
            'total_filters' => 0
        ];

        if (!empty($filters['survey_id'])) {
            $survey = Survey::find($filters['survey_id']);
            $summary['survey'] = $survey ? $survey->name : 'Unknown Survey';
            $summary['total_filters']++;
        }

        if (isset($filters['is_guest_review']) && $filters['is_guest_review'] === 'true') {
            $summary['review_type'] = 'Guest Reviews Only';
            $summary['total_filters']++;
        }

        if (isset($filters['is_user_review']) && $filters['is_user_review'] === 'true') {
            $summary['review_type'] = 'User Reviews Only';
            $summary['total_filters']++;
        }

        if (isset($filters['is_overall']) && $filters['is_overall'] === 'true') {
            $summary['review_scope'] = 'Overall Reviews Only';
            $summary['total_filters']++;
        } elseif (isset($filters['is_overall']) && $filters['is_overall'] === 'false') {
            $summary['review_scope'] = 'Survey Reviews Only';
            $summary['total_filters']++;
        }

        if (!empty($filters['staff_id'])) {
            $staff = User::find($filters['staff_id']);
            $summary['staff'] = $staff ? $staff->name : 'Unknown Staff';
            $summary['total_filters']++;
        }

        // Score range filter summary
        if (!empty($filters['min_score']) || !empty($filters['max_score'])) {
            $scoreRange = [];
            if (!empty($filters['min_score'])) {
                $scoreRange[] = "Min: {$filters['min_score']}";
            }
            if (!empty($filters['max_score'])) {
                $scoreRange[] = "Max: {$filters['max_score']}";
            }
            $summary['score_range'] = implode(', ', $scoreRange);
            $summary['total_filters']++;
        }

        // Labels filter summary
        if (!empty($filters['labels'])) {
            $labels = is_array($filters['labels']) ? $filters['labels'] : explode(',', $filters['labels']);
            $summary['labels'] = implode(', ', $labels);
            $summary['total_filters']++;
        }

        // Review type filter summary
        if (!empty($filters['review_type'])) {
            $summary['review_type_category'] = $filters['review_type'];
            $summary['total_filters']++;
        }

        // Comment filter summary
        if (isset($filters['has_comment']) && $filters['has_comment'] === 'true') {
            $summary['comment_filter'] = 'With Comments Only';
            $summary['total_filters']++;
        } elseif (isset($filters['has_comment']) && $filters['has_comment'] === 'false') {
            $summary['comment_filter'] = 'Without Comments Only';
            $summary['total_filters']++;
        }

        // Reply filter summary
        if (isset($filters['has_reply']) && $filters['has_reply'] === 'true') {
            $summary['reply_filter'] = 'Replied Reviews Only';
            $summary['total_filters']++;
        } elseif (isset($filters['has_reply']) && $filters['has_reply'] === 'false') {
            $summary['reply_filter'] = 'Unreplied Reviews Only';
            $summary['total_filters']++;
        }

        $summary['period'] = $filters['period'] ?? 'All time';

        return $summary;
    }
    private function calculatePerformanceOverview($reviews)
    {
        $totalSubmissions = $reviews->count();
        $averageScore = $totalSubmissions > 0 ? round($reviews->avg('rate'), 1) : 0;

        // Calculate sentiment distribution
        $positiveCount = $reviews->where('sentiment_score', '>=', 0.7)->count();
        $neutralCount = $reviews->whereBetween('sentiment_score', [0.4, 0.69])->count();
        $negativeCount = $reviews->where('sentiment_score', '<', 0.4)->count();

        return [
            'total_submissions' => $totalSubmissions,
            'average_score' => $averageScore,
            'score_out_of' => 5,
            'sentiment_distribution' => [
                'positive' => $totalSubmissions > 0 ? round(($positiveCount / $totalSubmissions) * 100) : 0,
                'neutral' => $totalSubmissions > 0 ? round(($neutralCount / $totalSubmissions) * 100) : 0,
                'negative' => $totalSubmissions > 0 ? round(($negativeCount / $totalSubmissions) * 100) : 0
            ],
            'submissions_today' => $reviews->where('created_at', '>=', Carbon::today())->count(),
            'submissions_this_week' => $reviews->where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'submissions_this_month' => $reviews->where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            'guest_reviews_count' => $reviews->whereNotNull('guest_id')->count(),
            'user_reviews_count' => $reviews->whereNotNull('user_id')->count(),
            'overall_reviews_count' => $reviews->where('is_overall', 1)->count(),
            'survey_reviews_count' => $reviews->whereNotNull('survey_id')->count()
        ];
    }

    private function getSubmissionsOverTime($reviews, $period)
    {
        $endDate = Carbon::now();
        $startDate = match ($period) {
            '7d' => Carbon::now()->subDays(7),
            '90d' => Carbon::now()->subDays(90),
            '1y' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30) // 30d
        };

        $groupFormat = match ($period) {
            '7d' => 'd-m-Y', // Daily for 7 days
            '90d', '1y' => 'm-Y', // Monthly for 90 days and 1 year
            default => 'd-m-Y' // Daily for 30 days
        };

        $filteredReviews = $reviews->whereBetween('created_at', [$startDate, $endDate]);

        $submissionsByPeriod = $filteredReviews->groupBy(function ($review) use ($groupFormat) {
            return $review->created_at->format($groupFormat);
        })->map(function ($periodReviews) {
            return [
                'submissions_count' => $periodReviews->count(),
                'average_rating' => round($periodReviews->avg('rate'), 1),
                'sentiment_score' => round($periodReviews->avg('sentiment_score') * 100, 1)
            ];
        });

        // Fill in missing periods with zero values
        $filledData = $this->fillMissingPeriods($submissionsByPeriod, $startDate, $endDate, $groupFormat);

        return [
            'period' => $period,
            'data' => $filledData,
            'total_submissions' => $filteredReviews->count(),
            'peak_submissions' => $submissionsByPeriod->max('submissions_count') ?? 0,
            'date_range' => [
                'start' => $startDate->format('d-m-Y'),
                'end' => $endDate->format('d-m-Y')
            ]
        ];
    }

    private function fillMissingPeriods($data, $startDate, $endDate, $format)
    {
        $filledData = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $periodKey = $current->format($format);
            $filledData[$periodKey] = $data[$periodKey] ?? [
                'submissions_count' => 0,
                'average_rating' => 0,
                'sentiment_score' => 0
            ];

            if ($format === 'd-m-Y') {
                $current->addDay();
            } else {
                $current->addMonth();
            }
        }

        return $filledData;
    }

    private function getRecentSubmissions($reviews, $limit = 5)
    {
        return $reviews->sortByDesc('created_at')
            ->take($limit)
            ->map(function ($review) {
                $userName = $this->getUserName($review);

                return [
                    'review_id' => $review->id,
                    'user_name' => $userName,
                    'rating' => $review->rate,
                    'comment' => $review->comment,
                    'submission_date' => $review->created_at->diffForHumans(),
                    'exact_date' => $review->created_at->format('d-m-Y H:i:s'),
                    'is_guest' => !is_null($review->guest_id),
                    'is_overall' => (bool)$review->is_overall,
                    'sentiment_score' => $review->sentiment_score,
                    'survey_name' => $review->survey ? $review->survey->name : null,
                    'staff_name' => $review->staff ? $review->staff->name : null
                ];
            })
            ->values()
            ->toArray();
    }

    private function getUserName($review)
    {
        if ($review->user) {
            return $review->user->name;
        } elseif ($review->guest_user) {
            return $review->guest_user->full_name;
        } else {
            return 'Anonymous User';
        }
    }
}
