<?php

namespace App\Http\Controllers;


use App\Models\Business;
use App\Models\ReviewValueNew;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SuperAdminReportController extends Controller
{
/**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/total-business",
     *      operationId="getTotalBusinessReport",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get TotalBusiness report",
     *      description="This method is to get TotalBusiness report",


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


    public function getTotalBusinessReport (Request $request) {
        $data["data"] = Business::get()->count();
        return response()->json($data,200);
    }


    /**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/total-business-enabled",
     *      operationId="getTotalEnabledBusinessReport",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get TotalEnabled business report",
     *      description="This method is to get TotalEnabled  business report",


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

    public function getTotalEnabledBusinessReport (Request $request) {
        $data["data"] = Business::where("expiry_date",">", now())->get()->count();
        return response()->json($data,200);
    }


    /**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/total-business-disabled",
     *      operationId="getTotalDisabledBusinessReport",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get Total Disabledbusiness report",
     *      description="This method is to get Total Disabled business report",


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


    public function getTotalDisabledBusinessReport (Request $request) {
        $data["data"] = Business::where("expiry_date",">", now())->get()->count();
        return response()->json($data,200);
    }







    /**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/total-reviews",
     *      operationId="getTotalReviews",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get Total Reviews report",
     *      description="This method is to Total Reviews  report",


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


    public function getTotalReviews (Request $request) {
        $data["data"] = ReviewValueNew::get()->count();
        return response()->json($data,200);
    }

    /**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/today-reviews",
     *      operationId="getTodayReviews",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get Today Reviews report",
     *      description="This method is to Today Reviews  report",

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




    public function getTodayReviews (Request $request) {
        $data["data"] = ReviewValueNew::whereDate('created_at', Carbon::today())->get()->count();
        return response()->json($data,200);
    }

    /**
     *
     * @OA\Get(
     *      path="/superadmin/dashboard-report/review-report",
     *      operationId="getReviewReport",
     *      tags={"super_admin_report"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get  Reviews report",
     *      description="This method is to get Reviews  report",
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



    public function getReviewReport (Request $request) {
        $data["total_reviews"] = ReviewValueNew::get()->count();


    $data["previous_week_total_reviews"] = ReviewValueNew::
        whereBetween(
            'review_value_news.created_at',
            [Carbon::now()->subWeek()->startOfWeek(), Carbon::now()->subWeek()->endOfWeek()]
        )
        ->get()->count();


    $data["this_week_total_reviews"] = ReviewValueNew::whereBetween('review_value_news.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
        ->get()->count();
        return response()->json($data,200);
    }




}
