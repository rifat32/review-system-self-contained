<?php

namespace App\Http\Controllers;

use App\Helpers\AIProcessor;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Question;
use App\Models\ReviewNew;
use App\Models\Survey;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private function getBaseQueries(Request $request)
    {
        $businessId = $request->businessId;

        $baseReviewQuery = ReviewNew::query()
            ->when(
                !$request->user()->hasRole('superadmin'),
                fn($q) => $q->where('review_news.business_id', $businessId)
            )
            ->globalFilters(0, $businessId)
            ->orderBy('review_news.order_no', 'asc')

            ->select('review_news.*')
            ->withCalculatedRating();

        return [
            'base_review' => $baseReviewQuery,
            'guest_review' => (clone $baseReviewQuery)->whereNull('user_id'),
            'customer_review' => (clone $baseReviewQuery)->whereNull('guest_id'),
            'authenticated_customer' => (clone $baseReviewQuery)->whereNotNull('user_id'),
            'question' => Question::when(
                !$request->user()->hasRole('superadmin'),
                fn($q) => $q->where('business_id', $businessId)
            )
                ->filterByOverall(),
            'tag' => Tag::when(
                !$request->user()->hasRole('superadmin'),
                fn($q) => $q->where('business_id', $businessId)
            )->filterByOverall()
        ];
    }

    private function getDateRanges($startDate, $endDate)
    {
        $now = Carbon::now();

        return [
            'today' => Carbon::today(),
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'previous_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month_start' => now()->subDays(30)->endOfDay(),
            'previous_month_range' => [now()->subDays(60)->startOfDay(), now()->subDays(30)->endOfDay()],
            'start_date' => $startDate,
            'end_date' => $endDate,
            'now' => $now,
            'number_of_months' => $startDate->diffInMonths($endDate)
        ];
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/reviews",
     *      operationId="getReviewStatistics",
     *      tags={"dashboard_management"},
     *      @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format",
     *         required=false,
     *         example="01-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="is_overall",
     *         in="query",
     *         description="0 for survey, 1 for overall",
     *         required=false,
     *         example="0"
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get review statistics",
     *      description="Get detailed review statistics including guest and customer breakdown",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Review statistics retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      )
     * )
     */
    public function getReviewStatistics(Request $request)
    {

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $queries = $this->getBaseQueries($request);
        $dateRanges = $this->getDateRanges($startDate, $endDate);

        $data = [
           
            'today_total_reviews' => (clone $queries['base_review'])
                ->whereDate('created_at', $dateRanges['today'])
                ->count(),

            'this_week_total_reviews' => (clone $queries['base_review'])
                ->whereBetween('created_at', $dateRanges['this_week'])
                ->count(),
            'previous_week_total_reviews' => (clone $queries['base_review'])
                ->whereBetween('created_at', $dateRanges['previous_week'])
                ->count(),
            'this_week_guest_review_count' => (clone $queries['guest_review'])
                ->whereBetween('created_at', $dateRanges['this_week'])
                ->count(),
            'previous_week_guest_review_count' => (clone $queries['guest_review'])
                ->whereBetween('created_at', $dateRanges['previous_week'])
                ->count(),
            'this_week_customer_review_count' => (clone $queries['customer_review'])
                ->whereBetween('created_at', $dateRanges['this_week'])
                ->count(),
            'previous_week_customer_review_count' => (clone $queries['customer_review'])
                ->whereBetween('created_at', $dateRanges['previous_week'])
                ->count(),

            'this_month_total_reviews' => (clone $queries['base_review'])
                ->where('created_at', '>', $dateRanges['this_month_start'])
                ->count(),
            'previous_month_total_reviews' => (clone $queries['base_review'])
                ->whereBetween('created_at', $dateRanges['previous_month_range'])
                ->count(),
            'this_month_guest_review_count' => (clone $queries['guest_review'])
                ->where('created_at', '>', $dateRanges['this_month_start'])
                ->count(),
            'previous_month_guest_review_count' => (clone $queries['guest_review'])
                ->whereBetween('created_at', $dateRanges['previous_month_range'])
                ->count(),
            'this_month_customer_review_count' => (clone $queries['customer_review'])
                ->where('created_at', '>', $dateRanges['this_month_start'])
                ->count(),
            'previous_month_customer_review_count' => (clone $queries['customer_review'])
                ->whereBetween('created_at', $dateRanges['previous_month_range'])
                ->count(),

            'total_reviews' => (clone $queries['base_review'])->count(),
            'total_guest_review_count' => (clone $queries['guest_review'])->count(),
            'total_customer_review_count' => (clone $queries['customer_review'])->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Review statistics retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/content",
     *      operationId="getContentStatistics",
     *      tags={"dashboard_management"},
     *      @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format",
     *         required=false,
     *         example="01-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="is_overall",
     *         in="query",
     *         description="0 for survey, 1 for overall",
     *         required=false,
     *         example="0"
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get question and tag statistics",
     *      description="Get question and tag count and trends",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Content statistics retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      )
     * )
     */
    public function getContentStatistics(Request $request)
    {

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $queries = $this->getBaseQueries($request);
        $dateRanges = $this->getDateRanges($startDate, $endDate);

        $data = [
            'questions' => [
                // Weekly
                'this_week_question_count' => (clone $queries['question'])
                    ->whereBetween('created_at', $dateRanges['this_week'])
                    ->count(),
                'previous_week_question_count' => (clone $queries['question'])
                    ->whereBetween('created_at', $dateRanges['previous_week'])
                    ->count(),

                // Monthly
                'this_month_question_count' => (clone $queries['question'])
                    ->where('created_at', '>', $dateRanges['this_month_start'])
                    ->count(),
                'previous_month_question_count' => (clone $queries['question'])
                    ->whereBetween('created_at', $dateRanges['previous_month_range'])
                    ->count(),

                // Total
                'total_question_count' => (clone $queries['question'])->count(),
            ],

            'tags' => [
                // Weekly
                'this_week_tag_count' => (clone $queries['tag'])
                    ->whereBetween('created_at', $dateRanges['this_week'])
                    ->count(),
                'previous_week_tag_count' => (clone $queries['tag'])
                    ->whereBetween('created_at', $dateRanges['previous_week'])
                    ->count(),

                // Monthly
                'this_month_tag_count' => (clone $queries['tag'])
                    ->where('created_at', '>', $dateRanges['this_month_start'])
                    ->count(),
                'previous_month_tag_count' => (clone $queries['tag'])
                    ->whereBetween('created_at', $dateRanges['previous_month_range'])
                    ->count(),

                // Total
                'total_tag_count' => (clone $queries['tag'])->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Content statistics retrieved successfully',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/monthly-trends",
     *      operationId="getMonthlyTrends",
     *      tags={"dashboard_management"},
     *      @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=false,
     *         example="1"
     *      ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format",
     *         required=false,
     *         example="01-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="is_overall",
     *         in="query",
     *         description="0 for survey, 1 for overall",
     *         required=false,
     *         example="0"
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get monthly trends data",
     *      description="Get monthly data for charts and graphs",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Monthly trends retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      )
     * )
     */
    public function getMonthlyTrends(Request $request)
    {
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfMonth();

        $queries = $this->getBaseQueries($request);
        $dateRanges = $this->getDateRanges($startDate, $endDate);

        $monthlyData = [
            'monthly_reviews' => [],
            'guest_review_count_monthly' => [],
            'customer_review_count_monthly' => [],
            'customers_monthly' => []
        ];

        for ($i = 0; $i <= $dateRanges['number_of_months']; $i++) {
            $start = $dateRanges['now']->copy()->startOfMonth()->subMonths($i);
            $end = $dateRanges['now']->copy()->endOfMonth()->subMonths($i);
            $month = $start->format('F');

            $monthlyData['monthly_reviews'][$i] = [
                'month' => $month,
                'value' => (clone $queries['base_review'])
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $monthlyData['guest_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $queries['guest_review'])
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $monthlyData['customer_review_count_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $queries['customer_review'])
                    ->whereBetween('created_at', [$start, $end])
                    ->count(),
            ];

            $monthlyData['customers_monthly'][$i] = [
                'month' => $month,
                'value' => (clone $queries['authenticated_customer'])
                    ->whereBetween('created_at', [$start, $end])
                    ->distinct()
                    ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Monthly trends retrieved successfully',
            'data' => $monthlyData
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/top-worst-services",
     *      operationId="getTopWorstServices",
     *      tags={"dashboard_management"},
     *      @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="Business ID",
     *         required=true,
     *         example="1"
     *      ),
     *      @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period (last_30_days, last_7_days, this_month, last_month)",
     *         required=false,
     *         example="last_30_days"
     *      ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Custom start date (d-m-Y format)",
     *         required=false,
     *         example="01-01-2025"
     *      ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Custom end date (d-m-Y format)",
     *         required=false,
     *         example="31-01-2025"
     *      ),
     *      @OA\Parameter(
     *         name="min_reviews",
     *         in="query",
     *         description="Minimum reviews required for a service to be included",
     *         required=false,
     *         example="3"
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get top 3 and worst 3 business services",
     *      description="Analyze business services performance based on review ratings",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Services performance analysis retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="top_services", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="service_id", type="integer", example=1),
     *                          @OA\Property(property="service_name", type="string", example="Room Service"),
     *                          @OA\Property(property="description", type="string", example="24-hour room service"),
     *                          @OA\Property(property="average_rating", type="number", format="float", example=4.7),
     *                          @OA\Property(property="total_reviews", type="integer", example=45),
     *                          @OA\Property(property="sentiment_score", type="integer", example=85),
     *                          @OA\Property(property="positive_reviews", type="integer", example=38),
     *                          @OA\Property(property="negative_reviews", type="integer", example=7),
     *                          @OA\Property(property="performance_label", type="string", example="Excellent"),
     *                          @OA\Property(property="top_tags", type="array",
     *                              @OA\Items(type="string", example="Prompt Service")
     *                          ),
     *                          @OA\Property(property="sample_comments", type="array",
     *                              @OA\Items(type="object",
     *                                  @OA\Property(property="comment", type="string", example="Room service was quick and food was hot..."),
     *                                  @OA\Property(property="rating", type="number", format="float", example=5.0),
     *                                  @OA\Property(property="sentiment", type="string", example="positive"),
     *                                  @OA\Property(property="date", type="string", example="Jan 15, 2025")
     *                              )
     *                          )
     *                      )
     *                  ),
     *                  @OA\Property(property="worst_services", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="service_id", type="integer", example=2),
     *                          @OA\Property(property="service_name", type="string", example="Spa Services"),
     *                          @OA\Property(property="description", type="string", example="Spa and wellness services"),
     *                          @OA\Property(property="average_rating", type="number", format="float", example=2.3),
     *                          @OA\Property(property="total_reviews", type="integer", example=32),
     *                          @OA\Property(property="sentiment_score", type="integer", example=25),
     *                          @OA\Property(property="positive_reviews", type="integer", example=8),
     *                          @OA\Property(property="negative_reviews", type="integer", example=24),
     *                          @OA\Property(property="performance_label", type="string", example="Poor"),
     *                          @OA\Property(property="top_tags", type="array",
     *                              @OA\Items(type="string", example="Long Wait")
     *                          ),
     *                          @OA\Property(property="sample_comments", type="array",
     *                              @OA\Items(type="object",
     *                                  @OA\Property(property="comment", type="string", example="Had to wait 45 minutes for massage..."),
     *                                  @OA\Property(property="rating", type="number", format="float", example=2.0),
     *                                  @OA\Property(property="sentiment", type="string", example="negative"),
     *                                  @OA\Property(property="date", type="string", example="Jan 20, 2025")
     *                              )
     *                          )
     *                      )
     *                  ),
     *                  @OA\Property(property="summary", type="object",
     *                      @OA\Property(property="total_services_analyzed", type="integer", example=8),
     *                      @OA\Property(property="services_with_reviews", type="integer", example=6),
     *                      @OA\Property(property="overall_service_rating", type="number", format="float", example=3.8),
     *                      @OA\Property(property="best_performing_service", type="string", example="Room Service"),
     *                      @OA\Property(property="worst_performing_service", type="string", example="Spa Services"),
     *                      @OA\Property(property="period", type="object",
     *                          @OA\Property(property="start", type="string", example="2025-01-01"),
     *                          @OA\Property(property="end", type="string", example="2025-01-31")
     *                      )
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function getTopWorstServices(Request $request)
    {
        $request->validate([
            'businessId' => 'required|integer|exists:businesses,id',
            'period' => 'nullable|in:last_30_days,last_7_days,this_month,last_month',
            'start_date' => 'nullable|date_format:d-m-Y',
            'end_date' => 'nullable|date_format:d-m-Y',
            'min_reviews' => 'nullable|integer|min:1'
        ]);

        $businessId = $request->input('businessId');

        // Get date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $dateRange = [
                'start' => Carbon::parse($request->input('start_date'))->startOfDay(),
                'end' => Carbon::parse($request->input('end_date'))->endOfDay()
            ];
        } else {
            $period = $request->input('period', 'last_30_days');
            $dateRange = getDateRangeByPeriod($period);
        }

        // Analyze services performance
        $servicesAnalysis = analyzeBusinessServicesPerformance($businessId, $dateRange);

        // Apply minimum reviews filter if specified
        $minReviews = $request->input('min_reviews', 3);
        if ($minReviews > 1) {
            $servicesAnalysis['top_services'] = array_filter(
                $servicesAnalysis['top_services'],
                fn($service) => $service['total_reviews'] >= $minReviews
            );
            $servicesAnalysis['worst_services'] = array_filter(
                $servicesAnalysis['worst_services'],
                fn($service) => $service['total_reviews'] >= $minReviews
            );

            $servicesAnalysis['top_services'] = array_values($servicesAnalysis['top_services']);
            $servicesAnalysis['worst_services'] = array_values($servicesAnalysis['worst_services']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Services performance analysis retrieved successfully',
            'data' => $servicesAnalysis
        ], 200);
    }


    /**
 * @OA\Get(
 *      path="/v1.0/dashboard/top-worst-staff",
 *      operationId="getTopWorstStaff",
 *      tags={"dashboard_management"},
 *      summary="Get top worst performing staff",
 *      description="Get staff with lowest ratings and sentiment scores",
 *      @OA\Parameter(
 *         name="businessId",
 *         in="query",
 *         description="Business ID",
 *         required=true,
 *         example="1"
 *      ),
 *      @OA\Parameter(
 *         name="period",
 *         in="query",
 *         description="Time period (last_30_days, last_7_days, this_month, last_month)",
 *         required=false,
 *         example="last_30_days"
 *      ),
 *      @OA\Parameter(
 *         name="start_date",
 *         in="query",
 *         description="Custom start date (d-m-Y format)",
 *         required=false,
 *         example="01-01-2025"
 *      ),
 *      @OA\Parameter(
 *         name="end_date",
 *         in="query",
 *         description="Custom end date (d-m-Y format)",
 *         required=false,
 *         example="31-01-2025"
 *      ),
 *      @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         description="Number of worst staff to return",
 *         required=false,
 *         example="5"
 *      ),
 *      @OA\Parameter(
 *         name="criteria",
 *         in="query",
 *         description="Criteria for ranking (rating, sentiment, negative)",
 *         required=false,
 *         example="rating"
 *      ),
 *      security={
 *          {"bearerAuth": {}}
 *      },
 *      @OA\Response(
 *          response=200,
 *          description="Successful operation",
 *          @OA\JsonContent(
 *              @OA\Property(property="success", type="boolean", example=true),
 *              @OA\Property(property="message", type="string", example="Worst staff analysis retrieved successfully"),
 *              @OA\Property(property="data", type="object")
 *          )
 *      )
 * )
 */
public function getTopWorstStaff(Request $request)
{
    $request->validate([
        'businessId' => 'required|integer|exists:businesses,id',
        'period' => 'nullable|in:last_30_days,last_7_days,this_month,last_month',
        'start_date' => 'nullable|date_format:d-m-Y',
        'end_date' => 'nullable|date_format:d-m-Y',
        'limit' => 'nullable|integer|min:1|max:20',
        'criteria' => 'nullable|in:rating,sentiment,negative'
    ]);

    $businessId = $request->input('businessId');
    
    // Get date range
    if ($request->has('start_date') && $request->has('end_date')) {
        $dateRange = [
            'start' => Carbon::parse($request->input('start_date'))->startOfDay(),
            'end' => Carbon::parse($request->input('end_date'))->endOfDay()
        ];
    } else {
        $period = $request->input('period', 'last_30_days');
        $dateRange = getDateRangeByPeriod($period);
    }

    $limit = $request->input('limit', 5);
    $criteria = $request->input('criteria', 'rating');

    // Use the new method
    $worstStaffAnalysis = AIProcessor::getTopWorstStaff($businessId, $dateRange, $limit, $criteria);

    return response()->json([
        'success' => true,
        'message' => 'Worst staff analysis retrieved successfully',
        'data' => $worstStaffAnalysis
    ], 200);
}

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/overview",
     *      operationId="getDashboardOverview",
     *      tags={"dashboard_management"},
     *      @OA\Parameter(
     *         name="businessId",
     *         in="query",
     *         description="businessId",
     *         required=true,
     *         example="1"
     *      ),
     *      @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date in d-m-Y format (e.g., 01-01-2025)",
     *         required=false,
     *         example="01-01-2025"
     *      ),
     *      @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date in d-m-Y format (e.g., 31-12-2025)",
     *         required=false,
     *         example="31-12-2025"
     *      ),
     *      @OA\Parameter(
     *         name="is_overall",
     *         in="query",
     *         description="0 for survey, 1 for overall",
     *         required=false,
     *         example="0"
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get dashboard overview data",
     *      description="Get dashboard overview statistics for the specified date range",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard overview retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="period", type="object",
     *                      @OA\Property(property="start_date", type="string", example="2025-01-01"),
     *                      @OA\Property(property="end_date", type="string", example="2025-12-31"),
     *                      @OA\Property(property="display_text", type="string", example="Jan 1, 2025 - Dec 31, 2025")
     *                  ),
     *                  @OA\Property(property="total_reviews", type="object",
     *                      @OA\Property(property="count", type="integer", example=1204),
     *                      @OA\Property(property="percentage_change", type="string", example="+2.7%"),
     *                      @OA\Property(property="change_type", type="string", example="increase"),
     *                      @OA\Property(property="from_period", type="string", example="from previous period")
     *                  ),
     *                  @OA\Property(property="average_rating", type="object",
     *                      @OA\Property(property="value", type="number", format="float", example=4.5),
     *                      @OA\Property(property="out_of", type="integer", example=5)
     *                  ),
     *                  @OA\Property(property="top_topic", type="object",
     *                      @OA\Property(property="name", type="string", example="Service"),
     *                      @OA\Property(property="mention_count", type="integer", example=45)
     *                  ),
     *                  @OA\Property(property="new_reviews", type="object",
     *                      @OA\Property(property="count", type="integer", example=58),
     *                      @OA\Property(property="from_period", type="string", example="this week")
     *                  ),
     *                  @OA\Property(property="all_sentiment", type="object",
     *                      @OA\Property(property="status", type="string", example="Positive"),
     *                      @OA\Property(property="based_on", type="string", example="Based on selected period")
     *                  ),
     *                  @OA\Property(property="pending_reviews", type="object",
     *                      @OA\Property(property="count", type="integer", example=3),
     *                      @OA\Property(property="action_text", type="string", example="Review Now")
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function getDashboardOverview(Request $request)
    {

        $businessId = $request->input('businessId');

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Business ID is required'
            ], 400);
        }

        // Parse date parameters with defaults for all-time data
        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::createFromTimestamp(0)->startOfDay(); // Very old date for all-time

        // Validate date range
        if ($startDate->greaterThan($endDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Start date cannot be greater than end date'
            ], 400);
        }

        // Calculate previous period of same duration
        $periodDuration = $startDate->diffInDays($endDate);
        $previousPeriodEnd = $startDate->copy()->subDay();
        $previousPeriodStart = $previousPeriodEnd->copy()->subDays($periodDuration);

        // Get base queries
        $queries = $this->getBaseQueries($request);

        // 1. Total Reviews for current period and previous period
        $currentPeriodReviews = (clone $queries['base_review'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $previousPeriodReviews = (clone $queries['base_review'])
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        // Calculate percentage change
        $percentageChange = 0;
        $changeType = 'no-change';
        $fromPeriodText = 'from previous period';

        if ($previousPeriodReviews > 0) {
            $percentageChange = (($currentPeriodReviews - $previousPeriodReviews) / $previousPeriodReviews) * 100;
            $changeType = $percentageChange >= 0 ? 'increase' : 'decrease';
        } elseif ($currentPeriodReviews > 0 && $previousPeriodReviews == 0) {
            $percentageChange = 100;
            $changeType = 'increase';
            $fromPeriodText = 'from no reviews';
        } elseif ($currentPeriodReviews == 0 && $previousPeriodReviews == 0) {
            $fromPeriodText = 'no previous data';
        }

        // 2. Average Rating for current period
        $currentPeriodReviewsWithRating = (clone $queries['base_review'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $averageRating = $currentPeriodReviewsWithRating->isNotEmpty()
            ? round($currentPeriodReviewsWithRating->avg('calculated_rating'), 1)
            : 0;

        // 3. Top Topic from tags in current period
        $topTopic = getTopTopic($businessId, $startDate, $endDate);

        // 4. New Reviews this week (always calculated for current week, regardless of selected period)
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $newReviewsThisWeek = (clone $queries['base_review'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        // 5. All Sentiment analysis for current period
        $sentiment_status =  calculateAggregatedSentiment($currentPeriodReviewsWithRating);

        // 6. Pending reviews (reviews that need attention - flagged or low rating)
        $reviews = clone $queries['base_review'];

        $pendingReviews = $reviews->whereBetween('created_at', [$startDate, $endDate])->where('status', 'flagged')->get()->filter(function ($review) {
            return $review->calculated_rating !== null && $review->calculated_rating <= 2;
        })->count();

        // Format period display text
        $periodDisplayText = formatPeriodDisplay($startDate, $endDate);

        $data = [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'display_text' => $periodDisplayText
            ],
            'total_reviews' => [
                'count' => $currentPeriodReviews,
                'percentage_change' => $percentageChange != 0 ? sprintf('%+.1f%%', $percentageChange) : '0%',
                'change_type' => $changeType,
                'from_period' => $fromPeriodText
            ],
            'average_rating' => [
                'value' => $averageRating,
                'out_of' => 5
            ],
            'top_topic' => [
                'name' => $topTopic['name'] ?? 'General',
                'mention_count' => $topTopic['count'] ?? 0
            ],
            'new_reviews' => [
                'count' => $newReviewsThisWeek,
                'from_period' => 'this week'
            ],
            'all_sentiment' => [
                'status' => $sentiment_status,
                'based_on' => 'Based on selected period'
            ],
            'pending_reviews' => [
                'count' => $pendingReviews,
                'action_text' => 'Review Now'
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Dashboard overview retrieved successfully',
            'data' => $data
        ], 200);
    }






    /**
     * @OA\Get(
     *      path="/v1.0/reports/branch-comparison",
     *      operationId="branchComparison",
     *      tags={"report"},
     *      summary="Compare multiple branches performance",
     *      description="Compare up to 5 branches with real metrics from database",
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Parameter(
     *          name="branch_ids",
     *          in="query",
     *          required=true,
     *          description="Comma-separated branch IDs (max 5)",
     *          example="1,2,3"
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          required=false,
     *          description="Start date in Y-m-d format",
     *          example="2024-01-01"
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          required=false,
     *          description="End date in Y-m-d format",
     *          example="2024-03-31"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch comparison retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function branchComparison(Request $request)
    {
        $request->validate([
            'branch_ids' => 'required|string',
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d',
        ]);

        // Parse branch IDs
        $branchIds = explode(',', $request->branch_ids);
        $branchIds = array_map('intval', $branchIds);
        $branchIds = array_slice($branchIds, 0, 5); // Limit to max 5 branches

        if (count($branchIds) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'At least one branch ID is required'
            ], 422);
        }

        // Get date range (default: last 90 days)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->subDays(90)->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        // Get branches with business info
        $branches = Branch::with(['business', 'manager'])
            ->whereIn('id', $branchIds)
            ->get();

        if ($branches->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No branches found'
            ], 404);
        }


        // Collect all branch data
        $comparisonData = [];
        $allBranchMetrics = [];

        foreach ($branches as $branch) {
            $branchData = getBranchComparisonData($branch, $startDate, $endDate);
            $comparisonData[] = $branchData;
            $allBranchMetrics[$branch->id] = $branchData['metrics'];
        }

        // Generate AI insights based on comparison
        $aiInsights = generateBranchComparisonInsights($comparisonData, $allBranchMetrics);

        // Generate comparison highlights
        $comparisonHighlights = generateComparisonHighlights($comparisonData);

        // Get sentiment trend over time (for chart)
        $sentimentTrend = getSentimentTrendOverTime($branches, $startDate, $endDate);

        // Get staff performance complaints
        $staffComplaints = getStaffComplaintsByBranch($branches, $startDate, $endDate);

        $data = [
            'selected_branches' => $branches->pluck('name'),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'period_days' => $startDate->diffInDays($endDate)
            ],
            'branches' => $comparisonData,
            'ai_insights' => $aiInsights,
            'comparison_highlights' => $comparisonHighlights,
            'sentiment_trend' => $sentimentTrend,
            'staff_complaints' => $staffComplaints
        ];

        return response()->json([
            'success' => true,
            'message' => 'Branch comparison retrieved successfully',
            'data' => $data
        ], 200);
    }


    /**
     * @OA\Get(
     *      path="/v1.0/branch-dashboard/{branchId}",
     *      operationId="getBranchDashboard",
     *      tags={"report"},
     *      summary="Get branch dashboard data with real metrics",
     *      description="Returns branch dashboard with real data from database",
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Parameter(
     *          name="branchId",
     *          in="path",
     *          required=true,
     *          description="Branch ID",
     *          example="1"
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          required=false,
     *          description="Start date in Y-m-d format",
     *          example="2024-01-01"
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          required=false,
     *          description="End date in Y-m-d format",
     *          example="2024-12-31"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch dashboard retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found"
     *      )
     * )
     */
    public function getBranchDashboard($branchId, Request $request)
    {
        $request->validate([
            'start_date' => 'sometimes|date_format:Y-m-d',
            'end_date' => 'sometimes|date_format:Y-m-d',
        ]);

        // Get date range (default: last 30 days)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        // Get branch with business relationship
        $branch = Branch::with(['business'])
            ->find($branchId);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Get business ID for reviews query
        $businessId = $branch->business_id;

        // Get reviews for this branch within date range
        $reviewsQuery = ReviewNew::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['staff', 'user', 'guest_user', 'survey'])
            ->withCalculatedRating();

        $reviews = $reviewsQuery->get();

        // Calculate summary metrics
        $summary = calculateBranchSummary($reviews);

        // Get AI insights
        $aiInsights = generateAiInsights($reviews);

        // Get recommendations
        $recommendations = generateBranchRecommendations($reviews, $branchId);

        // Get recent reviews (last 5)
        $recentReviews = getRecentReviews($reviews);

        // Get staff performance (top 5)
        $staffPerformance = getStaffPerformance($branchId, $businessId, $startDate, $endDate);

        $data = [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code ?? 'BRN-' . str_pad($branch->id, 5, '0', STR_PAD_LEFT),
                'name' => $branch->name,
                'status' => $branch->is_active ? 'Active' : 'Inactive',
                'location' => $branch->location,
                'manager_id' => $branch->manager_id,
                'manager_name' => $branch->manager ? $branch->manager->name : 'Not assigned',
                'business_id' => $businessId,
                'business_name' => $branch->business ? $branch->business->name : 'Unknown'
            ],
            'summary' => $summary,
            'ai_insights' => $aiInsights,
            'recommendations' => $recommendations,
            'recent_reviews' => $recentReviews,
            'staff_performance' => $staffPerformance,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'period_days' => $startDate->diffInDays($endDate)
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Branch dashboard retrieved successfully',
            'data' => $data
        ], 200);
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
     * *       security={
     *           {"bearerAuth": {}}
     *       },
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
    // All staff-related methods need to use ReviewValueNew for rating calculations
    public function staffComparison($businessId, Request $request)
    {
        $request->validate([
            'staff_a_id' => 'required|integer|exists:users,id',
            'staff_b_id' => 'required|integer|exists:users,id'
        ]);

        $business = Business::findOrFail($businessId);
        $staffAId = $request->staff_a_id;
        $staffBId = $request->staff_b_id;

        $staffA = User::findOrFail($staffAId);
        $staffB = User::findOrFail($staffBId);

        // Get reviews for both staff WITH calculated rating
        $staffAReviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffAId)
            ->withCalculatedRating()
            ->get();

        $staffBReviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffBId)
            ->withCalculatedRating()
            ->get();

        // Calculate metrics from ReviewValueNew
        $staffAMetrics = calculateStaffMetricsFromReviewValue($staffAReviews, $staffA);
        $staffBMetrics = calculateStaffMetricsFromReviewValue($staffBReviews, $staffB);

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
                    'rating_gap_message' => getRatingGapMessage($ratingGap),
                    'sentiment_gap' => $sentimentGap,
                    'sentiment_gap_message' => getSentimentGapMessage($sentimentGap),
                    'better_performer' => $ratingGap >= 0 ? $staffA->name : $staffB->name
                ],
                'staff_a' => $staffAMetrics,
                'staff_b' => $staffBMetrics
            ]
        ], 200);
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

        // Get reviews WITH calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->withCalculatedRating()
            ->get();

        // Calculate average rating from calculated_rating field
        $avgRating = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        $tenure = calculateTenure($staff->join_date);
        $ratingTrend = getRatingTrendFromReviewValue($reviews);
        $reviewSamples = getReviewSamples($reviews);
        $recommendedTraining = getRecommendedTraining($reviews);
        $skillGapAnalysis = analyzeSkillGaps($reviews);
        $customerTone = calculateCustomerTone($reviews);

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
                    'avg_rating' => $avgRating, // From ReviewValueNew
                    'sentiment_distribution' => calculateSentimentDistribution($reviews)
                ],
                'rating_trend' => $ratingTrend,
                'review_samples' => $reviewSamples,
                'recommended_training' => $recommendedTraining,
                'skill_gap_analysis' => $skillGapAnalysis,
                'customer_perceived_tone' => $customerTone
            ]
        ], 200);
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

        $currentReviews = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->globalFilters(0, $businessId)
            ->withCalculatedRating()
            ->get();

        $previousReviews = getPreviousPeriodReviews($businessId, $period);

        // Calculate overall metrics using ReviewValueNew
        $overallMetrics = calculateOverallMetricsFromReviewValue($currentReviews, $previousReviews);
        $complimentRatio = calculateComplimentRatio($currentReviews);
        $topStaff = getTopStaffByRatingFromReviewValue($currentReviews);
        $allStaff = getAllStaffMetricsFromReviewValue($currentReviews);

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

    /**
     * @OA\Get(
     *      path="/v1.0/reports/review-analytics/{businessId}",
     *      operationId="reviewAnalytics",
     *      tags={"Reports"},
     *      summary="Get review analytics with flexible filtering",
     *      description="Get performance overview and recent submissions with optional filters for survey, guest reviews, user reviews, and overall reviews",
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          required=true,
     *          example="1"
     *      ),
     *      @OA\Parameter(name="survey_id", in="query", required=false, description="Filter by survey ID", example="1"),
     *      @OA\Parameter(name="is_guest_review", in="query", required=false, description="Filter guest reviews: true=guest only, false=exclude guest", example="true"),
     *      @OA\Parameter(name="is_user_review", in="query", required=false, description="Filter user reviews: true=user only, false=exclude user", example="true"),
     *      @OA\Parameter(name="is_overall", in="query", required=false, description="Filter overall reviews: true=overall only, false=survey only", example="true"),
     *      @OA\Parameter(name="staff_id", in="query", required=false, description="Filter by staff member ID", example="1"),
     *      @OA\Parameter(name="period", in="query", required=false, description="Period for data: 7d, 30d, 90d, 1y", example="30d"),
     *      @OA\Parameter(name="min_score", in="query", required=false, description="Minimum rating score (1-5)", example="3"),
     *      @OA\Parameter(name="max_score", in="query", required=false, description="Maximum rating score (1-5)", example="5"),
     *      @OA\Parameter(name="labels", in="query", required=false, description="Filter by sentiment labels (comma separated)", example="positive,neutral"),
     *      @OA\Parameter(name="review_type", in="query", required=false, description="Filter by review type", example="feedback"),
     *      @OA\Parameter(name="has_comment", in="query", required=false, description="Filter by comments: true=with comments, false=without comments", example="true"),
     *      @OA\Parameter(name="has_reply", in="query", required=false, description="Filter by replies: true=replied, false=not replied", example="false"),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Review analytics retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="business_name", type="string", example="Business Name"),
     *                  @OA\Property(property="filters_applied", type="object"),
     *                  @OA\Property(property="performance_overview", type="object"),
     *                  @OA\Property(property="submissions_over_time", type="object"),
     *                  @OA\Property(property="recent_submissions", type="array", @OA\Items(type="object")),
     *                  @OA\Property(
     *                      property="top_three_staff",
     *                      type="object",
     *                      description="Top 3 staff by aggregated review metrics",
     *                      @OA\Property(property="total_staff_reviewed", type="integer", example=5),
     *                      @OA\Property(
     *                          property="staff",
     *                          type="array",
     *                          @OA\Items(
     *                              type="object",
     *                              @OA\Property(property="staff_id", type="integer", example=1),
     *                              @OA\Property(property="staff_name", type="string", example="John Doe"),
     *                              @OA\Property(property="position", type="string", example="Manager"),
     *                              @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                              @OA\Property(property="review_count", type="integer", example=25),
     *                              @OA\Property(property="sentiment_score", type="integer", example=80),
     *                              @OA\Property(property="sentiment_label", type="string", example="Excellent"),
     *                              @OA\Property(property="top_topics", type="array", @OA\Items(type="string")),
     *                              @OA\Property(property="recent_activity", type="string", example="2 days ago")
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      ),
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

        $reviewsQuery = ReviewNew::where('business_id', $businessId)
            ->with(['user', 'guest_user', 'survey'])
            ->withCalculatedRating();

        $reviewsQuery = applyFilters($reviewsQuery, $filters);
        $reviews = (clone $reviewsQuery)->get();

        // Calculate performance overview using ReviewValueNew
        $performance_overview = calculatePerformanceOverviewFromReviewValue($reviews);

        $submissionsOverTime = getSubmissionsOverTime((clone $reviewsQuery), $filters['period']);

        $recentSubmissions = getRecentSubmissions($reviews);

        // NEW: Get top three staff
        $topStaff = getTopThreeStaff($businessId, $filters);

        $filterSummary = $this->getFilterSummary($filters, $business);

        return response()->json([
            'success' => true,
            'message' => 'Review analytics retrieved successfully',
            'data' => [
                'business_id' => (int)$businessId,
                'business_name' => $business->name,
                'filters_applied' => $filterSummary,
                'performance_overview' => $performance_overview,
                'submissions_over_time' => $submissionsOverTime,
                'recent_submissions' => $recentSubmissions,
                // NEW: Add top three staff to the response
                'top_staff' => $topStaff
            ]
        ], 200);
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

    /**
     * @OA\Get(
     *      path="/v1.0/reviews/overall-dashboard/{businessId}",
     *      operationId="getOverallDashboardData",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get overall business dashboard data",
     *      description="Get comprehensive dashboard data with AI insights and analytics",
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
     *          description="Period: last_30_days, last_7_days, this_month, last_month",
     *          example="last_30_days"
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard data retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      )
     * )
     */
    public function getOverallDashboardData($businessId, Request $request)
    {
        $filterable_fields = [
            "last_30_days",
            "last_7_days",
            "this_month",
            "last_month"
        ];

        if (!in_array($request->get('period', 'last_30_days'), $filterable_fields)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid period. Only allowed' . implode(', ', $filterable_fields),
                'data' => null
            ], 400);
        }
        $period = $request->get('period', 'last_30_days');

        // Get period dates
        $dateRange = getDateRangeByPeriod($period);

        // Calculate metrics using existing methods
        $metrics = calculateDashboardMetrics($businessId, $dateRange);

        // Get rating breakdown using existing getAverage method logic
        $ratingBreakdown = extractRatingBreakdown(
            ReviewNew::withCalculatedRating()
                ->globalFilters(0, $businessId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->get()
        );

        // Get tags breakdown (NEW)
        $tagsBreakdown = extractTagsBreakdown($businessId, $dateRange);

        // Get AI insights using existing AI pipeline
        $aiInsights = getAiInsightsPanel($businessId, $dateRange);

        // Get staff performance using existing staff suggestions
        $staffPerformance = getStaffPerformanceSnapshot($businessId, $dateRange);

        // Get recent reviews feed
        $reviewFeed = getReviewFeed($businessId, $dateRange);

        // Get available filters
        $filters = getAvailableFilters($businessId);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully',
            'data' => [
                'metrics' => $metrics,
                'rating_breakdown' => $ratingBreakdown,
                'tags_breakdown' => $tagsBreakdown,
                'ai_insights_panel' => $aiInsights,
                'staff_performance_snapshot' => $staffPerformance,
                'review_feed' => $reviewFeed,
                'filters' => $filters
            ]
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/review-new/getavg/review/{businessId}/{start}/{end}",
     *      operationId="getAverages",
     *      tags={"z.unused"},
     *         security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="businessId",
     * in="path",
     * description="businessId",
     * required=true,
     * example="1"
     * ),
     *  @OA\Parameter(
     * name="start",
     * in="path",
     * description="from date",
     * required=true,
     * example="2019-06-29"
     * ),
     *  @OA\Parameter(
     * name="end",
     * in="path",
     * description="to date",
     * required=true,
     * example="2026-06-29"
     * ),
     *      summary="This method is to get average",
     *      description="This method is to get average",
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
     *          description="Unprocessable Content",
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
    public function getAverages($businessId, $start, $end, Request $request)
    {
        // Get reviews with their values
        $reviews = ReviewNew::with(['value'])
            ->where("business_id", $businessId)
            ->globalFilters(0, $businessId)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating()
            ->get();

        $data = extractRatingBreakdown($reviews);

        return response($data, 200);
    }
}
