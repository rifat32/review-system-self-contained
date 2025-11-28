<?php

namespace App\Http\Controllers;



use App\Models\Question;
use App\Models\Business;

use App\Models\ReviewNew;
use App\Models\ReviewValueNew;
use App\Models\Star;
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
     *      path="/customer-report",
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


    public function customerDashboardReport(Request $request)
    {

        $data["last_five_reviews"] = ReviewNew::with("business", "value")->where([
            "user_id" => $request->customer_id
        ])
            ->filterByStaff()
            ->latest()
            ->take(5)
            ->get();

        return response()->json($data, 200);
    }


    /**
     *
     * @OA\Get(
     *      path="/business-report",
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


    public function businessDashboardReport(Request $request)
    {
        $data["business"] = Business::with("owner")->where([
            "id" => $request->business_id
        ])->first();

        return response()->json($data, 200);
    }



    /**
     *
     * @OA\Get(
     *      path="/dashboard-report/{businessId}",
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



    public function getDashboardReport(Request $request, $businessId)
    {

        $data = [];

        $data["today_total_reviews"] = ReviewNew::where([
            "review_news.business_id" => $businessId
        ])->whereDate('created_at', Carbon::today())
            ->filterByStaff()
            ->get()
            ->count();

        $data["this_month_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])->filterByStaff()
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->get()->count();

        $data["total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->filterByStaff()
            ->get()->count();

        $data["previous_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
                ->filterByStaff()
            ->get()
            ->count();

        $data["this_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->filterByStaff()
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

        return response()->json($data, 200);
    }




    /**
     *
     * @OA\Get(
     *      path="/dashboard-report/business/get",
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
        return response()->json($data, 200);
    }







    /**
     *
     * @OA\Get(
     *      path="/dashboard-report3",
     *      operationId="getDashboardReport3",
     *      tags={"report"},
     *          @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get dashboard report",
     *      description="This method is to get dashboard report",


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

    public function getDashboardReport3(Request $request)
    {
        $data = [];

        $data['survey'] = $this->generateDashboardReport($request, 0);   // Normal survey (is_overall = 0)
        $data['overall'] = $this->generateDashboardReport($request, 1);  // Overall report (is_overall = 1)


        return response()->json($data, 200);
    }


    private function generateDashboardReport($is_overall)
    {

        // Get the business ID from the request
        $businessId = request()->businessId;
        $data = [];

        // Get the current date and time
        $now = Carbon::now();

        // Define the start date (beginning of the current year)
        $startDate = $now->copy()->startOfYear();

        // Define the end date (end of the current month)
        $endDate = $now->copy()->endOfMonth();

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
                    ->filterByStaff()
                ->count();
        }



        // Count total reviews created today
        $data["today_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "review_news.business_id" => $businessId
        ] : []))
            ->whereDate('created_at', Carbon::today())
            ->filterByOverall($is_overall)
            ->where("status", "published")
                ->filterByStaff()
            ->count();

        // Count total reviews created within the last 30 days (approximate current month)
        $data["this_month_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay()) // Filter reviews created in the last 30 days
            ->filterByOverall($is_overall)
                ->filterByStaff()
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
                ->filterByStaff()
            ->where("status", "published")
            ->count();

        // Count total reviews overall (all-time count)
        $data["total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->filterByOverall($is_overall)
            ->where("status", "published")
                ->filterByStaff()
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
                ->filterByStaff()
            ->where("status", "published")
            ->count();


        // Count total reviews created in the current week (from Monday to Sunday)
        $data["this_week_total_reviews"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]) // Filter by current week range
            ->filterByOverall($is_overall)
                ->filterByStaff()
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
                    ->filterByStaff()
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
                    ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
            ->where("status", "published")
            ->get()
            ->count();

        // Count total guest reviews (all-time)
        $data["total_guest_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))
            ->filterByOverall($is_overall)
                ->filterByStaff()
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
                    ->filterByStaff()
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
                    ->filterByStaff()
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
                    ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
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
                ->filterByStaff()
            ->where("status", "published")
            ->get()
            ->count();

        // Count total customer reviews (all-time, excluding guests)
        $data["total_customer_review_count"] = ReviewNew::where((!request()->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))
            ->filterByOverall($is_overall)
                ->filterByStaff()
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
                    ->filterByStaff()
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
                    ->filterByStaff()
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
                ->filterByStaff()
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
                    ->filterByStaff()
                    ->where("status", "published")
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
}
