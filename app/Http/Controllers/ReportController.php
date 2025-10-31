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
            ->latest()->take(5)->get();

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
            ->get()->count();

        $data["this_month_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->get()->count();

        $data["total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->get()->count();

        $data["previous_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->get()->count();

        $data["this_week_total_reviews"] = ReviewNew::where([
            "business_id" => $businessId
        ])
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->get()->count();

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
     *      path="/dashboard-report2",
     *      operationId="getDashboardReport2",
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



    public function getDashboardReport2(Request $request)
    {

        $businessId =   $request->businessId;

        $data = [];

        $now = Carbon::now();
        $startDate = $now->copy()->startOfYear();
        $endDate = $now->copy()->endOfMonth();
        $numberOfMonths = $startDate->diffInMonths($endDate);

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["monthly_reviews"][$i]["month"] = $month;

            $data["monthly_data"]["monthly_reviews"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "review_news.business_id" => $businessId
            ] : []))
                ->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->count();
        }

        $data["today_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "review_news.business_id" => $businessId
        ] : []))->whereDate('created_at', Carbon::today())
            ->count();


        $data["this_month_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->count();

        $data["previous_month_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [now()->subDays(60)->startOfDay(), now()->subDays(30)]
            )
            ->count();

        $data["total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->count();

        $data["previous_week_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->count();

        $data["this_week_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        $total_stars_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin") ? [
                "review_news.business_id" => $businessId
            ] : []))
            ->select("review_value_news.star_id")
            ->distinct()
            ->get();

        foreach ($total_stars_selected as $key => $star_selected) {
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->first();

            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->get()
                ->count();

            for ($i = 0; $i <= $numberOfMonths; $i++) {
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
                $month = $startDateOfMonth->format('F');

                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["month"] = $month;

                $data["monthly_data"]["selected_stars"][$key]["star_selected_time_monthly"][$i]["value"]   = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where((!$request->user()->hasRole("superadmin") ? [
                        "review_news.business_id" => $businessId
                    ] : []))
                    ->where([
                        "star_id" => $star_selected->star_id
                    ])
                    ->whereBetween('review_value_news.created_at', [$startDateOfMonth, $endDateOfMonth])
                    ->count();
            }

            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->count();

            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
                ->get()
                ->count();
        }

        $total_tag_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin") ? [
                "review_news.business_id" => $businessId
            ] : []))
            ->select("review_value_news.tag_id")
            ->distinct()
            ->get();

        foreach ($total_tag_selected as $key => $tag_selected) {
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->first();

            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->get()
                ->count();

            for ($i = 0; $i <= $numberOfMonths; $i++) {
                $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
                $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);

                $month = $startDateOfMonth->format('F');

                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["month"] = $month;

                $data["monthly_data"]["selected_tags"][$key]["tag_selected_time_monthly"][$i]["value"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                    ->where((!$request->user()->hasRole("superadmin") ? [
                        "review_news.business_id" => $businessId
                    ] : []))
                    ->where([

                        "tag_id" =>  $tag_selected->tag_id
                    ])
                    ->whereBetween(
                        'review_value_news.created_at',
                        [$startDateOfMonth, $endDateOfMonth]
                    )

                    ->count();
            }

            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([

                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )
                ->count();

            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([
                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])

                ->get()
                ->count();

            $data["selected_tags"][$key]["tag_selected_time_this_month"] =       ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
                ->where((!$request->user()->hasRole("superadmin") ? [
                    "review_news.business_id" => $businessId
                ] : []))
                ->where([

                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->where('review_value_news.created_at', '>', now()->subDays(30)->endOfDay())
                ->get()
                ->count();
        }

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);

            $month = $startDateOfMonth->format('F');

            $data["monthly_data"]["customers_monthly"][$i]["month"] = $month;
        }

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["guest_review_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["guest_review_count_monthly"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->get()
                ->count();
        }

        $data["this_month_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))->where('created_at', '>', now()->subDays(30)->endOfDay())

            ->count();

        $data["previous_month_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )->get()->count();

        $data["total_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "user_id" => NULL
        ] : []))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_week_guest_review"][$i]["total"] =  $customer;

            $data["this_week_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "user_id" => NULL
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_month_guest_review"][$i]["total"] =  $customer;


            $data["this_month_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["customer_review_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["customer_review_count_monthly"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])

                ->count();
        }

        $data["this_month_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->get()
            ->count();

        $data["previous_month_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )->get()->count();

        $data["total_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId,
            "guest_id" => NULL
        ] : []))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_week_customer_review"][$i]["total"] =  $customer;

            $data["this_week_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId,
                "guest_id" => NULL
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_month_customer_review"][$i]["total"] =  $customer;
            $data["this_month_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["question_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["question_count_monthly"][$i]["value"] = Question::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])

                ->count();
        }

        $data["this_month_question_count"] = Question::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->where('created_at', '>', now()->subDays(30)->endOfDay())

            ->count();

        $data["previous_month_question_count"] = Question::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_question_count"] = Question::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_question_count"] = Question::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )->get()->count();
        $data["total_question_count"] = Question::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = Question::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_week_question"][$i]["total"] =  $customer;

            $data["this_week_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = Question::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))
                ->get()->count();
            $data["this_month_question"][$i]["total"] =  $customer;
            $data["this_month_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }

        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["tag_count"][$i]["month"] = $month;
            $data["monthly_data"]["tag_count"][$i]["value"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereBetween('created_at', [$startDateOfMonth, $endDateOfMonth])
                ->get()
                ->count();
        }

        $data["this_month_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->where('created_at', '>', now()->subDays(30)->endOfDay())
            ->get()
            ->count();

        $data["previous_month_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )->get()->count();
        $data["total_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin") ? [
            "business_id" => $businessId
        ] : []))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = Tag::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))

                ->get()->count();

            $data["this_week_tag"][$i]["total"] =  $customer;


            $data["this_week_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = Tag::where((!$request->user()->hasRole("superadmin") ? [
                "business_id" => $businessId
            ] : []))->whereDate('created_at', Carbon::today()->subDay($i))
                ->get()->count();
            $data["this_month_tag"][$i]["total"] =  $customer;
            $data["this_month_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i), "d/m/Y");
        }



        // new  reports enhancement 
        // ----------------------------
        // 1️⃣ Review Growth Rate
        // ----------------------------
        $review_query = ReviewNew::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId));

        $previous_month_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();

        $this_month_reviews = $data['this_month_total_reviews'] ?? (clone $review_query)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $data['review_growth_rate_month'] = $previous_month_reviews > 0
            ? round((($this_month_reviews - $previous_month_reviews) / $previous_month_reviews) * 100, 2)
            : 0;

        $previous_week_reviews = (clone $review_query)
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();


        $this_week_reviews = $data['this_week_total_reviews'] ?? (clone $review_query)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $data['review_growth_rate_week'] = $previous_week_reviews > 0
            ? round((($this_week_reviews - $previous_week_reviews) / $previous_week_reviews) * 100, 2)
            : 0;

        // ----------------------------
        // 2️⃣ Review Source Breakdown
        // ----------------------------
        $sources = (clone $review_query)->distinct()->pluck('source');
        $data['review_source_breakdown'] = $sources->map(fn($source) => [
            'source' => $source,
            'total'  => (clone $review_query)->where('source', $source)->count()
        ]);

        // ----------------------------
        // 3️⃣ Review Response Time (average in hours)
        // ----------------------------
        $responses = (clone $review_query)->whereNotNull('responded_at')->get();
        $data['average_response_time_hours'] = $responses->count() > 0
            ? round($responses->avg(fn($r) => \Carbon\Carbon::parse($r->responded_at)->diffInHours($r->created_at)), 2)
            : 0;

        // ----------------------------
        // 4️⃣ Review Language Distribution
        // ----------------------------
        $languages = (clone $review_query)->distinct()->pluck('language');
        $data['review_language_distribution'] = $languages->map(fn($lang) => [
            'language' => $lang,
            'total'    => (clone $review_query)->where('language', $lang)->count()
        ]);

        // ----------------------------
        // ⭐ STAR RATING ENHANCEMENTS
        // ----------------------------

        // Average Star Rating
        $avg_ratings = [
            'today' => (clone $review_query)->whereDate('created_at', now())->avg('rate'),
            'this_week' => (clone $review_query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->avg('rate'),
            'this_month' => (clone $review_query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->avg('rate')
        ];
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
        $weighted_sum = $review_query->get()->sum(fn($r) => $r->user_id ? $r->rating * $weights['verified'] : $r->rating * $weights['guest']);
        $total_weight = $review_query->get()->sum(fn($r) => $r->user_id ? $weights['verified'] : $weights['guest']);
        $data['weighted_star_rating'] = $total_weight ? round($weighted_sum / $total_weight, 2) : 0;

        // Low-Rating Alerts
        $low_rating_this_week = (clone $review_query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->whereIn('rate', [1, 2])->count();
        $low_rating_last_week = (clone $review_query)->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->whereIn('rate', [1, 2])->count();
        $low_rating_increase = $low_rating_last_week ? round(($low_rating_this_week - $low_rating_last_week) / $low_rating_last_week * 100, 2) : ($low_rating_this_week ? 100 : 0);
        $data['low_rating_alert'] = [
            'this_week_low_ratings' => $low_rating_this_week,
            'last_week_low_ratings' => $low_rating_last_week,
            'increase_percent' => $low_rating_increase,
            'alert' => $low_rating_increase >= 30
        ];

        // ----------------------------
        // 🏷️ TAG REPORT ENHANCEMENTS
        // ----------------------------
        $tags_with_reviews = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->select('review_news.id as review_id', 'review_value_news.tag_id')
            ->get()
            ->groupBy('review_id');

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

        $all_tags = Tag::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))->get();
        $data['tag_impact_on_ratings'] = $all_tags->mapWithKeys(fn($tag) => [
            $tag->id => round(
                ReviewNew::leftJoin('review_value_news', 'review_news.id', '=', 'review_value_news.review_id')
                    ->where('review_value_news.tag_id', $tag->id)
                    ->when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
                    ->avg('review_news.rate') ?? 0,
                2
            )
        ])->toArray();

        // ----------------------------
        // ❓ QUESTION REPORT ENHANCEMENTS
        // ----------------------------
        $questions = Question::when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('business_id', $businessId))->get();
        $total_users = $review_query->count();

        $data['question_completion_rate'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => [
                'question_text' => $qst->text,
                'completion_rate' => $total_users ? round(ReviewValueNew::where('question_id', $qst->id)->whereHas('review', fn($r) => $r->where('business_id', $businessId))->count() / $total_users * 100, 2) : 0
            ]
        ])->toArray();

        $data['average_response_per_question'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => ReviewValueNew::where('question_id', $qst->id)->whereHas('review', fn($r) => $r->where('business_id', $businessId))->count()
        ])->toArray();

        $data['response_distribution'] = $questions->mapWithKeys(fn($qst) => [
            $qst->id => collect($qst->options ?? [])->mapWithKeys(fn($opt) => [
                $opt => ReviewValueNew::where('question_id', $qst->id)->where('answer', $opt)->whereHas('review', fn($r) => $r->where('business_id', $businessId))->count()
            ])->toArray()
        ])->toArray();

        // ----------------------------
        // 📊 DASHBOARD TRENDS ENHANCEMENTS
        // ----------------------------
        $total_review_count = $review_query->count();
        $avg_star = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->avg('star_id');

        $data['dashboard_trends'] = [
            'engagement_index' => round($total_review_count * ($avg_star ?? 0), 2),
            'performance_vs_target' => round(($total_review_count / 100) * 100, 2), // assuming 100 target

            // Reviews by hour of day
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
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [$d + 1]) // MySQL returns 1–7 (Sunday = 1)
                        ->count()];
                })
                ->toArray(),
        ];

        // ----------------------------
        // 📈 ADVANCED INSIGHTS
        // ----------------------------
        $reviewers = $review_query->pluck('user_id')->filter();
        $repeat_reviewers_count = $reviewers->countBy()->filter(fn($c) => $c > 1)->count();
        $total_customers = $reviewers->unique()->count();
        $data['advanced_insights']['customer_retention_rate'] = $total_customers ? round($repeat_reviewers_count / $total_customers * 100, 2) : 0;

        $data['advanced_insights']['topic_analysis'] = ReviewValueNew::leftJoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->when(!$request->user()->hasRole('superadmin'), fn($q) => $q->where('review_news.business_id', $businessId))
            ->select('tag_id', DB::raw('count(*) as total'))
            ->groupBy('tag_id')
            ->get()
            ->map(fn($t) => [
                'tag_id' => $t->tag_id,
                'count' => $t->total,
                'tag_name' => Tag::find($t->tag_id)?->name
            ]);

        $data['advanced_insights']['monthly_review_trend'] = $review_query->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'))->groupBy('month')->pluck('total', 'month');

        $review_with_replies = $review_query->whereNotNull('responded_at')->get();
        $data['advanced_insights']['response_effectiveness'] = [
            'before_reply_avg' => round($review_with_replies->avg('star_before_reply') ?? 0, 2),
            'after_reply_avg' => round($review_with_replies->avg('star_after_reply') ?? 0, 2)
        ];



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

        $businessId =   $request->businessId;

        $data = [];

     $data['survey'] = $this->generateDashboardReport($request, 0);   // Normal survey (is_overall = 0)
    $data['overall'] = $this->generateDashboardReport($request, 1);  // Overall report (is_overall = 1)


        return response()->json($data, 200);
    }


    private function generateDashboardReport($request, $is_overall)
{
    $user = auth()->user();
    $business_id = $user->business_id;
    $is_superadmin = $user->hasRole('superadmin');

    // Base query scope by role
    $review_query = ReviewNew::query()
        ->when(!$is_superadmin, fn($q) => $q->where('business_id', $business_id));

    $review_value_query = ReviewValueNew::query()
        ->when(!$is_superadmin, fn($q) => $q->where('business_id', $business_id));

    // 1️⃣ Total Reviews Count
    $total_reviews = $review_query->count();

    // 2️⃣ Average Rating
    $average_rating = round($review_value_query->avg('value') ?? 0, 2);

    // 3️⃣ Rating Distribution
    $rating_distribution = $review_value_query
        ->select('value', DB::raw('count(*) as count'))
        ->groupBy('value')
        ->pluck('count', 'value');

    // 4️⃣ Tags Summary
    $tags = Tag::query()
        ->when(!$is_superadmin, fn($q) => $q->where('business_id', $business_id))
        ->get();

    $tag_summary = $tags->map(function ($tag) use ($review_value_query, $is_overall) {
        $avg_value = $review_value_query
            ->where('tag_id', $tag->id)
            ->whereHas('question', fn($q) => $q->where('is_overall', $is_overall))
            ->avg('value');
        return [
            'tag' => $tag->name,
            'average' => round($avg_value ?? 0, 2),
        ];
    });

    // 5️⃣ Recent Reviews (latest 5)
    $recent_reviews = $review_query
        ->latest()
        ->take(5)
        ->get(['id', 'customer_name', 'created_at']);

    // 6️⃣ Questions Summary (Filtered by is_overall)
    $questions = Question::query()
        ->when(!$is_superadmin, fn($q) => $q->where('business_id', $business_id))
        ->where('is_overall', $is_overall)
        ->get();

    $question_summary = $questions->map(function ($question) use ($review_value_query) {
        $avg_rating = $review_value_query
            ->where('question_id', $question->id)
            ->avg('value');
        return [
            'question' => $question->name,
            'average_rating' => round($avg_rating ?? 0, 2),
        ];
    });

    // 7️⃣ Overall Trend by Month (optional chart data)
    $trend_by_month = $review_value_query
        ->select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('avg(value) as average')
        )
        ->whereHas('question', fn($q) => $q->where('is_overall', $is_overall))
        ->groupBy('month')
        ->orderBy('month')
        ->get();

    // Final data response
    return [
        'total_reviews' => $total_reviews,
        'average_rating' => $average_rating,
        'rating_distribution' => $rating_distribution,
        'tag_summary' => $tag_summary,
        'recent_reviews' => $recent_reviews,
        'question_summary' => $question_summary,
        'trend_by_month' => $trend_by_month,
    ];
}
    
}
