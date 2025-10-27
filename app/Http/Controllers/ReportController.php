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


public function customerDashboardReport(Request $request) {

  
    $data["last_five_reviews"] = ReviewNew::with("business","value")->where([
        "user_id" => $request->customer_id
    ])
    ->latest()->take(5)->get();

    return response()->json($data,200);

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


    public function businessDashboardReport(Request $request) {
        $data["business"] = Business::with("owner")->where([
            "id" => $request->business_id
        ])->first();
 


        return response()->json($data,200);

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
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review

     

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


        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
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
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
    // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review



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

        // $businessCondition = [
        //     "business_id" => $request->businessId
        // ];
        // if($request->user()->hasRole("superadmin")) {
        //      if
        // }




        $data = [];
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review
        // @@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@ review

        // $data["today_total_reviews"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        // ->whereDate('review_news.created_at', Carbon::today())
        // ->get()->count();


        $now = Carbon::now();
$startDate = $now->copy()->startOfYear();
$endDate = $now->copy()->endOfMonth();
$numberOfMonths = $startDate->diffInMonths($endDate);

for ($i = 0; $i <= $numberOfMonths; $i++) {
    $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
    $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
    $month = $startDateOfMonth->format('F');

    $data["monthly_data"]["monthly_reviews"][$i]["month"] = $month;

    $data["monthly_data"]["monthly_reviews"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
        "review_news.business_id" => $businessId
    ]:[]))
    ->whereBetween('created_at',[$startDateOfMonth,$endDateOfMonth])
    ->count();

}



        $data["today_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "review_news.business_id" => $businessId
        ]:[]))->whereDate('created_at', Carbon::today())
        ->count();

        // $data["this_month_total_reviews"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        // ->where('review_news.created_at', '>', now()->subDays(30)->endOfDay())
        // ->get()->count();
        $data["this_month_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))
        ->where('created_at', '>', now()->subDays(30)->endOfDay())
        ->count();

        $data["previous_month_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))
        ->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)])
        ->count();


        // $data["total_reviews"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        //     ->where([
        //         "review_news.business_id" => $businessId
        //     ])
        //     ->get()->count();

            $data["total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))
            ->count();


        // $data["previous_week_total_reviews"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        //     ->where([
        //         "review_news.business_id" => $businessId
        //     ])
        //     ->whereBetween(
        //         'review_value_news.created_at',
        //         [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        //     )
        //     ->get()->count();
        $data["previous_week_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))
            ->whereBetween(
                'created_at',
                [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
            )
            ->count();


        // $data["this_week_total_reviews"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
        //     ->where([
        //         "review_news.business_id" => $businessId
        //     ])
        //     ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
        //     ->get()->count();

        $data["this_week_total_reviews"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))
        ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
        ->count();

        // @@@@@@@@@@@@@@@@@@@@@@@@@ star
        // @@@@@@@@@@@@@@@@@@@@@@@@@ star
        // @@@@@@@@@@@@@@@@@@@@@@@@@ star



        $total_stars_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
            ->select("review_value_news.star_id")
            ->distinct()
            ->get();

        foreach ($total_stars_selected as $key => $star_selected) {
            $data["selected_stars"][$key]["star"] = Star::where([
                "id" => $star_selected->star_id
            ])
                ->first();

            $data["selected_stars"][$key]["star_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
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
                    ->where((!$request->user()->hasRole("superadmin")?[
                        "review_news.business_id" => $businessId
                    ]:[]))
                        ->where([
                            "star_id" => $star_selected->star_id
                        ])

                        ->whereBetween('review_value_news.created_at',[$startDateOfMonth,$endDateOfMonth])

                        ->count();

                }

            $data["selected_stars"][$key]["star_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
                ->where([
                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )

                ->count();
            $data["selected_stars"][$key]["star_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
                ->where([

                    "star_id" => $star_selected->star_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])

                ->get()
                ->count();
        }


        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
        // @@@@@@@@@@@@@@@@@@@@@@@@@ tag
        $total_tag_selected = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
            ->select("review_value_news.tag_id")
            ->distinct()
            ->get();

        foreach ($total_tag_selected as $key => $tag_selected) {
            $data["selected_tags"][$key]["tag"] = Tag::where([
                "id" => $tag_selected->tag_id
            ])
                ->first();

            $data["selected_tags"][$key]["tag_selected_time"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
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
                    ->where((!$request->user()->hasRole("superadmin")?[
                        "review_news.business_id" => $businessId
                    ]:[]))
                        ->where([

                            "tag_id" =>  $tag_selected->tag_id
                        ])

                        ->whereBetween(
                            'review_value_news.created_at',
                            [$startDateOfMonth,$endDateOfMonth]
                        )

                        ->count();

                }

            $data["selected_tags"][$key]["tag_id"] = $tag_selected->tag_id;
            $data["selected_tags"][$key]["tag_selected_time_previous_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
                ->where([

                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween(
                    'review_value_news.created_at',
                    [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
                )

                ->count();
            $data["selected_tags"][$key]["tag_selected_time_this_week"] = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
                ->where([

                    "tag_id" =>  $tag_selected->tag_id
                ])
                ->whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])

                ->get()
                ->count();

            $data["selected_tags"][$key]["tag_selected_time_this_month"] =       ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where((!$request->user()->hasRole("superadmin")?[
                "review_news.business_id" => $businessId
            ]:[]))
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
            $data["monthly_data"]["guest_review_count_monthly"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "user_id" => NULL
            ]:[]))->whereBetween('created_at', [$startDateOfMonth,$endDateOfMonth] )
            ->get()
            ->count();

        }



        $data["this_month_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "user_id" => NULL
        ]:[]))->where('created_at', '>', now()->subDays(30)->endOfDay())

        ->count();

        $data["previous_month_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "user_id" => NULL
        ]:[]))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "user_id" => NULL
        ]:[]))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "user_id" => NULL
        ]:[]))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        ) ->get()->count();


        $data["total_guest_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "user_id" => NULL
        ]:[]))->get()->count();


        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "user_id" => NULL
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_week_guest_review"][$i]["total"] =  $customer;


            $data["this_week_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");

        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "user_id" => NULL
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_month_guest_review"][$i]["total"] =  $customer;


            $data["this_month_guest_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");

        }


        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["customer_review_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["customer_review_count_monthly"][$i]["value"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "guest_id" => NULL
            ]:[]))->whereBetween('created_at', [$startDateOfMonth,$endDateOfMonth] )

            ->count();

        }



        $data["this_month_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "guest_id" => NULL
        ]:[]))->where('created_at', '>', now()->subDays(30)->endOfDay())
        ->get()
        ->count();

        $data["previous_month_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "guest_id" => NULL
        ]:[]))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "guest_id" => NULL
        ]:[]))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "guest_id" => NULL
        ]:[]))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        ) ->get()->count();

        $data["total_customer_review_count"] = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId,
            "guest_id" => NULL
        ]:[]))->get()->count();



        for ($i = 0; $i <= 6; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "guest_id" => NULL
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_week_customer_review"][$i]["total"] =  $customer;


            $data["this_week_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");

        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = ReviewNew::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId,
                "guest_id" => NULL
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_month_customer_review"][$i]["total"] =  $customer;
            $data["this_month_customer_review"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");
        }






        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["question_count_monthly"][$i]["month"] = $month;
            $data["monthly_data"]["question_count_monthly"][$i]["value"] = Question::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereBetween('created_at', [$startDateOfMonth,$endDateOfMonth] )

            ->count();

        }




        $data["this_month_question_count"] = Question::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->where('created_at', '>', now()->subDays(30)->endOfDay())

        ->count();

        $data["previous_month_question_count"] = Question::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_question_count"] = Question::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_question_count"] = Question::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        ) ->get()->count();
        $data["total_question_count"] = Question::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = Question::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_week_question"][$i]["total"] =  $customer;


            $data["this_week_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");

        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = Question::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))
            ->get()->count();
            $data["this_month_question"][$i]["total"] =  $customer;
            $data["this_month_question"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");
        }



        for ($i = 0; $i <= $numberOfMonths; $i++) {
            $startDateOfMonth = $now->copy()->startOfMonth()->subMonths($i);
            $endDateOfMonth = $now->copy()->endOfMonth()->subMonths($i);
            $month = $startDateOfMonth->format('F');
            $data["monthly_data"]["tag_count"][$i]["month"] = $month;
            $data["monthly_data"]["tag_count"][$i]["value"] = Tag::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereBetween('created_at', [$startDateOfMonth,$endDateOfMonth] )
            ->get()
            ->count();

        }


        $data["this_month_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->where('created_at', '>', now()->subDays(30)->endOfDay())
        ->get()
        ->count();

        $data["previous_month_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween(
            'created_at',
            [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()]
        )->get()->count();

        $data["this_week_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->get()->count();

        $data["previous_week_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->whereBetween(
            'created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        ) ->get()->count();
        $data["total_tag_count"] = Tag::where((!$request->user()->hasRole("superadmin")?[
            "business_id" => $businessId
        ]:[]))->get()->count();

        for ($i = 0; $i <= 6; $i++) {
            $customer = Tag::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))

            ->get()->count();

            $data["this_week_tag"][$i]["total"] =  $customer;


            $data["this_week_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");

        }
        for ($i = 0; $i <= 29; $i++) {
            $customer = Tag::where((!$request->user()->hasRole("superadmin")?[
                "business_id" => $businessId
            ]:[]))->whereDate('created_at', Carbon::today()->subDay($i))
            ->get()->count();
            $data["this_month_tag"][$i]["total"] =  $customer;
            $data["this_month_tag"][$i]["date"] =  date_format(Carbon::today()->subDay($i),"d/m/Y");
        }






        return response()->json($data, 200);
    }




}
