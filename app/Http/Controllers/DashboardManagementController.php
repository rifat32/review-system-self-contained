<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\Business;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardManagementController extends Controller
{
    use ErrorUtil;

    /**
     *
     * @OA\Get(
     *      path="/v1.0/business-owner-dashboard",
     *      operationId="getBusinessOwnerDashboardData",
     *      tags={"reports"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     * @OA\Parameter(
     *     name="customer_date_filter",
     *     in="query",
     *     description="Customer date filter",
     *     required=true,
     *     example=""
     * ),

   


     *
     * @OA\Parameter(
     *     name="revenue_date_filter",
     *     in="query",
     *     description="Revenue date filter",
     *     required=true,
     *     example=""
     * ),
    

    
     *
    
     *
     *      summary="get all dashboard data combined",
     *      description="get all dashboard data combined",
     *

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
     *   @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   *@OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   *@OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getBusinessOwnerDashboardData(Request $request)
    {
        try {

            $this->storeActivity($request, "");

            $business = Business::where([
                "OwnerID" => auth()->user()->id
            ])
                ->first();

            if (empty($business)) {
                return response()->json([
                    "message" => "You are not a business user"
                ], 401);
            }

            // Define validation rules for date filters
            $validator = Validator::make($request->all(), [
                'customer_date_filter' => 'required|string',
                'revenue_date_filter' => 'required|string',

            ], [
                '*.required' => 'The :attribute field is required.',
                '*.string' => 'The :attribute must be a valid string.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'errors' => $validator->errors()
                ], 422);
            }

            // Call the method with different time periods
            $data["customers"] = $this->getCustomersByPeriod(request()->input("customer_date_filter"), $business);

        


            $data["revenue"] = $this->revenue(request()->input("revenue_date_filter"), $business);

        

         

            $data["customer_report"] = [
                "customers" => $this->getCustomersByPeriod(request()->input("customer_date_filter"), $business),
             
            ];

            $data["filters"] = [
                "customers" => request()->input("customer_filters"),
            
            ];




            return response()->json($data, 200);
        } catch (Exception $e) {
            return $this->sendError($e, 500, $request);
        }
    }


    public function getCustomersByPeriod($period, $business)
    {

        $dateRange = $this->getDateRange($period);
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        
        $first_time_customers = User::
        // whereHas('orders', function ($query) use ($business, $start, $end) {
        //     $query
        //     ->where("orders.business_id", $business->id)
        //         ->whereRaw('(SELECT COUNT(*) FROM orders o WHERE o.customer_id = orders.customer_id AND o.business_id = ?) = 1', [$business->id])
        //         ->when((!empty($start) && !empty($end)), function ($query) use ($start, $end) {
        //             $query->whereBetween('orders.created_at', [$start, $end]);
        //         })
        //     ;
        // })

            distinct()
            ->get();

        $returning_customers = User::
        
        // whereHas('orders', function ($query) use ($business, $start, $end) {
        //     $query->where("orders.business_id", $business->id)
        //         ->whereRaw('(SELECT COUNT(*) FROM orders o WHERE o.customer_id = orders.customer_id AND o.business_id = ?) > 1', [$business->id])
        //         ->when((!empty($start) && !empty($end)), function ($query) use ($start, $end) {
        //             $query->whereBetween('orders.created_at', [$start, $end]);
        //         });
        // })

            distinct()
            ->get();

        // Return the results
        return [
            'first_time_customers' => $first_time_customers,
            'returning_customers' => $returning_customers,
        ];
    }


  










































































    /**
 * @OA\Get(
 *      path="/v1.0/sales-reports",
 *      operationId="getSalesReports",
 *      tags={"reports"},
 *      security={
 *           {"bearerAuth": {}}
 *      },
 *      @OA\Parameter(
 *          name="sales_date_filter",
 *          in="query",
 *          description="Sales date filter",
 *          required=true,
 *          example="this_month"
 *      ),
 *      @OA\Parameter(
 *          name="payment_method_filter",
 *          in="query",
 *          description="Filter by payment method (cash, credit, digital wallets)",
 *          required=false,
 *          example="cash"
 *      ),

 *      summary="Get sales report data",
 *      description="Returns detailed sales report including daily, weekly/monthly trends, and sales breakdown by category and item.",
 *      @OA\Response(
 *          response=200,
 *          description="Successful operation",
 *          @OA\JsonContent()
 *      ),
 *      @OA\Response(
 *          response=401,
 *          description="Unauthenticated",
 *          @OA\JsonContent()
 *      ),
 *      @OA\Response(
 *          response=422,
 *          description="Unprocessable Entity",
 *          @OA\JsonContent()
 *      ),
 *      @OA\Response(
 *          response=400,
 *          description="Bad Request",
 *          @OA\JsonContent()
 *      )
 * )
 */
public function getSalesReports(Request $request)
{
    try {
        $this->storeActivity($request, "");

        // Validate the date filter parameter
        $validator = Validator::make($request->all(), [
            'sales_date_filter' => 'required|string',
            'payment_method_filter' => 'nullable|string',
            'category_filter' => 'nullable|string',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the business based on the authenticated user
        $business = Business::where([
            "OwnerID" => auth()->user()->id
        ])->first();

        if (empty($business)) {
            return response()->json([
                "message" => "You are not a business user"
            ], 401);
        }

        // Get the date range for the sales filter
        $dateRange = $this->getDateRange($request->input('sales_date_filter'));
        $start = $dateRange['start'];
        $end = $dateRange['end'];

        // Fetch daily sales summary report
        $daily_sales_summary = $this->getDailySalesSummary(Carbon::today(), $business);


        // Fetch weekly/monthly sales trends
        $sales_trends = $this->getSalesTrends($start, $end, $business);

        // Fetch sales by category & item
        $sales_by_category_item = $this->getSalesByCategoryItem($start, $end, $business, $request->input('category_filter'));

        // Return the report data
        return response()->json([
            'daily_sales_summary' => $daily_sales_summary,
            'sales_trends' => $sales_trends,
            'sales_by_category_item' => $sales_by_category_item
        ], 200);

    } catch (Exception $e) {
        return $this->sendError($e, 500, $request);
    }
}




private function getDateRange($period)
{
    switch ($period) {
        case 'today':
            $start = Carbon::today();
            $end = Carbon::today();
            break;
        case 'this_week':
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
            break;
        case 'this_month':
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
            break;
        case 'next_week':
            $start = Carbon::now()->addWeek()->startOfWeek();
            $end = Carbon::now()->addWeek()->endOfWeek();
            break;
        case 'next_month':
            $start = Carbon::now()->addMonth()->startOfMonth();
            $end = Carbon::now()->addMonth()->endOfMonth();
            break;
        case 'previous_week':
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end = Carbon::now()->subWeek()->endOfWeek();
            break;
        case 'previous_month':
            $start = Carbon::now()->subMonth()->startOfMonth();
            $end = Carbon::now()->subMonth()->endOfMonth();
            break;
        default:
            $start = "";
            $end = "";
    }

    return [
        'start' => $start,
        'end' => $end,
    ];
}




}
