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
use App\Services\AIProcessor\AIProcessorService;
use App\Services\Dashboard\DashboardService;
use App\Services\Review\RecentReviewService;
use App\Services\Review\ReviewMetricsService;
use App\Services\Review\ReviewService;
use App\Services\Staff\StaffPerformanceService;
use App\Services\Business\BusinessAnalyticsService;
use App\Services\Review\ReviewFeedService;
use App\Services\Review\ReviewTopicService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardController extends Controller
{
    private $dashboardService;
    private $aiProcessorService;
    private $businessAnalyticsService;
    private $recentReviewService;
    private $reviewMetricsService;
    private $reviewService;
    private $staffPerformanceService;
    private $reviewFeedService;
    private $reviewTopicService;

    public function __construct(
        DashboardService $dashboardService,
        AIProcessorService $aiProcessorService,
        BusinessAnalyticsService $businessAnalyticsService,
        RecentReviewService $recentReviewService,
        ReviewMetricsService $reviewMetricsService,
        ReviewService $reviewService,
        StaffPerformanceService $staffPerformanceService,
        ReviewFeedService $reviewFeedService,
        ReviewTopicService $reviewTopicService
    ) {
        $this->dashboardService = $dashboardService;
        $this->aiProcessorService = $aiProcessorService;
        $this->businessAnalyticsService = $businessAnalyticsService;
        $this->recentReviewService = $recentReviewService;
        $this->reviewMetricsService = $reviewMetricsService;
        $this->reviewService = $reviewService;
        $this->staffPerformanceService = $staffPerformanceService;
        $this->reviewFeedService = $reviewFeedService;
        $this->reviewTopicService = $reviewTopicService;
    }

    private function getBaseQueries(Request $request)
    {
        $businessId = $request->businessId;

        $baseReviewQuery = ReviewNew::where('review_news.business_id', $businessId)
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->orderBy('review_news.order_no', 'asc')
            ->select('review_news.*')
            ->withCalculatedRating();

        return [
            'base_review' => $baseReviewQuery,
            'guest_review' => (clone $baseReviewQuery)->whereNull('user_id'),
            'customer_review' => (clone $baseReviewQuery)->whereNull('guest_id'),
            'authenticated_customer' => (clone $baseReviewQuery)->whereNotNull('user_id'),
            'question' => Question::when(
                $request->user() && !$request->user()->hasRole('superadmin'),
                fn($q) => $q->where('business_id', $businessId)
            )
                ->filterByOverall(),
            'tag' => Tag::when(
                $request->user() && !$request->user()->hasRole('superadmin'),
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

    private const FILTERABLE_FIELDS = [
        "last_30_days",
        "last_7_days",
        "this_month",
        "last_month",
        "all_time"
    ];

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
     *         name="period",
     *         in="query",
     *         description="Time period (last_30_days, last_7_days, this_month, last_month)",
     *         required=false,
     *         example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month"})
     *      ),
     *      @OA\Parameter(
     *         name="min_reviews",
     *         in="query",
     *         description="Minimum reviews required for a service to be included",
     *         required=false,
     *         example="3"
     *      ),
     *      @OA\Parameter(
     *         name="is_overall",
     *         in="query",
     *         description="0 for survey-specific, 1 for overall analysis",
     *         required=false,
     *         example="0"
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

        $businessId = auth()->user()->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );

        // Analyze services performance
        $servicesAnalysis = $this->businessAnalyticsService->analyzeBusinessServicesPerformance($businessId, $dateRange);

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
     *      path="/v1.0/dashboard/staff-performance",
     *      operationId="getStaffPerformanceAnalysis",
     *      tags={"dashboard_management"},
     *      summary="Get top and worst performing staff",
     *      description="Get both top performing and worst performing staff with analysis",
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
     *         example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month"})
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
     *         description="Number of top/worst staff to return (default: 3)",
     *         required=false,
     *         example="3"
     *      ),
     *      @OA\Parameter(
     *         name="criteria",
     *         in="query",
     *         description="Criteria for ranking (rating, sentiment, positive, negative)",
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
     *              @OA\Property(property="message", type="string", example="Staff performance analysis retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="top_staff", type="array",
     *                      @OA\Items(type="object")
     *                  ),
     *                  @OA\Property(property="worst_staff", type="array",
     *                      @OA\Items(type="object")
     *                  ),
     *                  @OA\Property(property="summary", type="object"),
     *                  @OA\Property(property="total_staff_analyzed", type="integer"),
     *                  @OA\Property(property="criteria_used", type="string"),
     *                  @OA\Property(property="date_range", type="object")
     *              )
     *          )
     *      )
     * )
     */
    public function getStaffPerformanceAnalysis(Request $request)
    {
        $request->validate([
            'businessId' => 'required|integer|exists:businesses,id',
            'period' => 'nullable|in:last_30_days,last_7_days,this_month,last_month',
            'start_date' => 'nullable|date_format:d-m-Y',
            'end_date' => 'nullable|date_format:d-m-Y',
            'limit' => 'nullable|integer|min:1|max:10',
            'criteria' => 'nullable|in:rating,sentiment,positive,negative'
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

        $limit = $request->input('limit', 3);
        $criteria = $request->input('criteria', 'rating');

        // Use the updated method
        $performanceAnalysis = $this->aiProcessorService->getTopWorstStaff($businessId, $dateRange, $limit, $criteria);

        return response()->json([
            'success' => true,
            'message' => 'Staff performance analysis retrieved successfully',
            'data' => $performanceAnalysis
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/insights-overview",
     *      operationId="getInsightsOverview",
     *      tags={"dashboard_management"},
     *      summary="Get insights overview data",
     *      description="Get top issues, performance by branch, performance by area, and top performing staff",
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
     *         example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month"})
     *      ),
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Insights overview retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="top_issues", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="issue", type="string", example="Wait Time"),
     *                          @OA\Property(property="percentage", type="integer", example=15),
     *                          @OA\Property(property="count", type="integer", example=45)
     *                      )
     *                  ),
     *                  @OA\Property(property="performance_by_branch", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="name", type="string", example="Downtown"),
     *                          @OA\Property(property="rating", type="number", format="float", example=4.8),
     *                          @OA\Property(property="review_count", type="integer", example=120),
     *                          @OA\Property(property="branch_id", type="integer", example=1)
     *                      )
     *                  ),
     *                  @OA\Property(property="performance_by_area", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="name", type="string", example="Dining Area"),
     *                          @OA\Property(property="rating", type="number", format="float", example=4.5),
     *                          @OA\Property(property="review_count", type="integer", example=80),
     *                          @OA\Property(property="area_id", type="integer", example=1),
     *                          @OA\Property(property="business_service_id", type="integer", example=1),
     *                          @OA\Property(property="business_service_name", type="string", example="Restaurant Service")
     *                      )
     *                  ),
     *                  @OA\Property(property="top_performing_staff", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="staff_id", type="integer", example=1),
     *                          @OA\Property(property="name", type="string", example="Sarah J."),
     *                          @OA\Property(property="role", type="string", example="Server"),
     *                          @OA\Property(property="area", type="string", example="Downtown"),
     *                          @OA\Property(property="rating", type="number", format="float", example=5.0),
     *                          @OA\Property(property="review_count", type="integer", example=12),
     *                          @OA\Property(property="image", type="string", nullable=true, example="https://example.com/image.jpg")
     *                      )
     *                  )
     *              )
     *          )
     *      )
     * )
     */
    public function getInsightsOverview(Request $request)
    {
        $request->validate([
            'businessId' => 'required|integer|exists:businesses,id',
            'period' => 'nullable|in:last_30_days,last_7_days,this_month,last_month',
            // 'start_date' => 'nullable|date_format:d-m-Y',
            // 'end_date' => 'nullable|date_format:d-m-Y'
        ]);

        $businessId = $request->input('businessId');

        // Get date range - using existing function
        // if ($request->has('start_date') && $request->has('end_date')) {
        //     $dateRange = [
        //         'start' => Carbon::parse($request->input('start_date'))->startOfDay(),
        //         'end' => Carbon::parse($request->input('end_date'))->endOfDay()
        //     ];
        // } else {
        // }
        $period = $request->input('period', 'last_30_days');
        $dateRange = getDateRangeByPeriod($period);


        // Get insights overview data
        $insightsData = $this->aiProcessorService->getInsightsOverview($businessId, $dateRange);

        return response()->json([
            'success' => true,
            'message' => 'Insights overview retrieved successfully',
            'data' => $insightsData
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/staff-insights",
     *      operationId="getStaffInsights",
     *      tags={"dashboard_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get staff insights and top performers",
     *      description="Get overall sentiment and top performing staff member for a business",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Staff insights retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="period", type="object",
     *                      @OA\Property(property="start_date", type="string", example="2025-01-01"),
     *                      @OA\Property(property="end_date", type="string", example="2025-12-31"),
     *                      @OA\Property(property="display_text", type="string", example="Jan 1, 2025 - Dec 31, 2025")
     *                  ),
     *                  @OA\Property(property="overall_sentiment", type="string", example="Positive"),
     *                  @OA\Property(property="top_performer", type="object", nullable=true,
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="rating", type="number", format="float", example=4.8),
     *                      @OA\Property(property="review_count", type="integer", example=25)
     *                  ),
     *                  @OA\Property(property="action_text", type="string", example="Details")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request"
     *      )
     * )
     */
    public function getStaffInsights(Request $request)
    {

        $user = auth()->user();
        // GET BUSINESS ID
        $businessId = $user->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );

        // Get reviews with staff for the current period
        $staffReviewQuery = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')

            ->when($dateRange, fn($query) => $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]))
            ->globalReviewFilters(0)
            ->withCalculatedRating();

        $staffReviews = $staffReviewQuery->get();
        // Calculate overall sentiment
        $sentiment_data = $this->aiProcessorService->calculateAggregatedSentiment($staffReviews);
        $sentiment_status = is_array($sentiment_data) ? $sentiment_data['sentiment_label'] : $sentiment_data;

        $topPerformer = null;

        if ($staffReviews->isNotEmpty()) {
            // Group reviews by staff_id
            $staffGroups = [];
            foreach ($staffReviews as $review) {
                if ($review->staff_id) {
                    $staffGroups[$review->staff_id][] = $review;
                }
            }

            $staffPerformance = [];

            foreach ($staffGroups as $staffId => $reviewsArray) {
                $staff = User::find($staffId);
                if (!$staff || count($reviewsArray) < 3)
                    continue; // Skip staff with less than 3 reviews

                $totalRating = 0;
                $totalReviews = count($reviewsArray);

                foreach ($reviewsArray as $review) {
                    $totalRating += $review->calculated_rating ?? 0;
                }

                $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;

                $staffPerformance[] = [
                    'staff_id' => $staffId,
                    'staff_name' => $staff->name,
                    'staff_image' => $staff->image,
                    'avg_rating' => round($avgRating, 2),
                    'review_count' => $totalReviews
                ];
            }

            // Sort by average rating (highest first)
            usort($staffPerformance, function ($a, $b) {
                if ($b['avg_rating'] == $a['avg_rating']) {
                    return $b['review_count'] <=> $a['review_count'];
                }
                return $b['avg_rating'] <=> $a['avg_rating'];
            });

            // Get the top performer
            if (!empty($staffPerformance)) {
                $topStaff = $staffPerformance[0];
                $topPerformer = [
                    'name' => $topStaff['staff_name'],
                    'rating' => $topStaff['avg_rating'],
                    'review_count' => $topStaff['review_count']
                ];
            }
        }


        $data = [
            'overall_sentiment' => $sentiment_status,
            'top_performer' => $topPerformer,
            'action_text' => 'Details'
        ];

        return response()->json([
            'success' => true,
            'message' => 'Staff insights retrieved successfully',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/survey-insights",
     *      operationId="getSurveyInsights",
     *      tags={"dashboard_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get survey insights and statistics",
     *      description="Get active surveys count and recent survey submissions for a business",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Survey insights retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="period", type="object",
     *                      @OA\Property(property="start_date", type="string", example="2025-01-01"),
     *                      @OA\Property(property="end_date", type="string", example="2025-12-31"),
     *                      @OA\Property(property="display_text", type="string", example="Jan 1, 2025 - Dec 31, 2025")
     *                  ),
     *                  @OA\Property(property="active_surveys", type="integer", example=5),
     *                  @OA\Property(property="recent_submissions", type="integer", example=120),
     *                  @OA\Property(property="action_text", type="string", example="Manage")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request"
     *      )
     * )
     */
    public function getSurveyInsights(Request $request)
    {
        // GET USER AND BUSINESS ID
        $user = $request->user();
        $businessId = $user->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );



        // Active Surveys (surveys that are active/published)
        $activeSurveysQuery = Survey::where('business_id', $businessId)
            ->where('is_active', true);



        if ($dateRange) {
            $activeSurveysQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $activeSurveys = $activeSurveysQuery->count();

        // Recent Submissions (reviews in the current period that are from surveys)
        $recentSubmissionsQuery = ReviewNew::where('business_id', $businessId)
            ->globalReviewFilters(0)
            ->whereNotNull('survey_id');

        if ($dateRange) {
            $recentSubmissionsQuery->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }



        $recentSubmissions = $recentSubmissionsQuery->count();

        $data = [
            'active_surveys' => $activeSurveys,
            'recent_submissions' => $recentSubmissions,
            'action_text' => 'Manage'
        ];

        return response()->json([
            'success' => true,
            'message' => 'Survey insights retrieved successfully',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/overview",
     *      operationId="getDashboardOverview",
     *      tags={"z.unused"},
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
        $topTopic = $this->reviewTopicService->getTopTopic($businessId, $startDate, $endDate);

        // 4. New Reviews this week (always calculated for current week, regardless of selected period)
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        $newReviewsThisWeek = (clone $queries['base_review'])
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        // 5. All Sentiment analysis for current period
        $sentiment_data = $this->aiProcessorService->calculateAggregatedSentiment($currentPeriodReviewsWithRating);
        $sentiment_status = is_array($sentiment_data) ? $sentiment_data['sentiment_label'] : $sentiment_data;

        // 6. Flagged reviews (reviews below threshold)
        $flagged_reviews = (clone $queries['base_review'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereDoesNotMeetsThreshold()
            ->count();

        // 7. CSAT Score (percentage of reviews meeting threshold)
        $totalReviewsInPeriod = $currentPeriodReviews;
        $csatReviewsCount = (clone $queries['base_review'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereMeetsThreshold()
            ->count();

        $csatPercentage = $totalReviewsInPeriod > 0
            ? round(($csatReviewsCount / $totalReviewsInPeriod) * 100)
            : 0;

        // 8. Calculate CSAT percentage change vs previous period
        $previousPeriodCSATCount = (clone $queries['base_review'])
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->whereMeetsThreshold()
            ->count();

        $previousPeriodTotalReviews = $previousPeriodReviews;
        $previousCSATPercentage = $previousPeriodTotalReviews > 0
            ? round(($previousPeriodCSATCount / $previousPeriodTotalReviews) * 100)
            : 0;

        $csatPercentageChange = $previousCSATPercentage > 0
            ? round($csatPercentage - $previousCSATPercentage, 1)
            : 0;

        // 9. Surveys Data
        // Active Surveys (surveys that are active/published)
        $activeSurveys = Survey::where('business_id', $businessId)
            ->where('is_active', true)
            ->count();

        // Recent Submissions (reviews in the current period that are from surveys)
        $recentSubmissions = (clone $queries['base_review'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('survey_id')
            ->count();

        // 10. Staff Insights Data - Get top performing staff for the current period
        $topPerformer = null;

        // Get reviews with staff for the current period
        $staffReviews = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->withCalculatedRating()
            ->globalReviewFilters(0)
            ->get();

        if ($staffReviews->isNotEmpty()) {
            // Group reviews by staff_id
            $staffGroups = [];
            foreach ($staffReviews as $review) {
                if ($review->staff_id) {
                    $staffGroups[$review->staff_id][] = $review;
                }
            }

            $staffPerformance = [];

            foreach ($staffGroups as $staffId => $reviewsArray) {
                $staff = User::find($staffId);
                if (!$staff || count($reviewsArray) < 3)
                    continue; // Skip staff with less than 3 reviews

                $totalRating = 0;
                $totalReviews = count($reviewsArray);

                foreach ($reviewsArray as $review) {
                    $totalRating += $review->calculated_rating ?? 0;
                }

                $avgRating = $totalReviews > 0 ? $totalRating / $totalReviews : 0;

                $staffPerformance[] = [
                    'staff_id' => $staffId,
                    'staff_name' => $staff->name,
                    'avg_rating' => round($avgRating, 2),
                    'review_count' => $totalReviews
                ];
            }

            // Sort by average rating (highest first)
            usort($staffPerformance, function ($a, $b) {
                if ($b['avg_rating'] == $a['avg_rating']) {
                    return $b['review_count'] <=> $a['review_count'];
                }
                return $b['avg_rating'] <=> $a['avg_rating'];
            });

            // Get the top performer
            if (!empty($staffPerformance)) {
                $topStaff = $staffPerformance[0];
                $topPerformer = [
                    'name' => $topStaff['staff_name'],
                    'rating' => $topStaff['avg_rating'],
                    'review_count' => $topStaff['review_count']
                ];
            }
        }

        // Format period display text
        $periodDisplayText = $this->reviewService->formatPeriodDisplay($startDate, $endDate);

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
                'from_period' => 'this week',
                'date_range' => [
                    'start_date' => $weekStart->format('d-m-Y'),
                    'end_date' => $weekEnd->format('d-m-Y')
                ]
            ],
            'all_sentiment' => [
                'status' => $sentiment_status,
                'based_on' => 'Based on selected period'
            ],
            'flagged_reviews' => [
                'count' => $flagged_reviews,
                'action_text' => 'Review Now'
            ],
            'csat_score' => [
                'percentage' => $csatPercentage,
                'percentage_change' => $csatPercentageChange != 0 ? sprintf('%+.1f%%', $csatPercentageChange) : '0%',
                'change_type' => $csatPercentageChange >= 0 ? 'increase' : 'decrease'
            ],
            // New sections from image
            'surveys' => [
                'active_surveys' => $activeSurveys,
                'recent_submissions' => $recentSubmissions,
                'action_text' => 'Manage'
            ],
            'staff_insights' => [
                'overall_sentiment' => $sentiment_status,
                'top_performer' => $topPerformer ? [
                    'name' => $topPerformer['name'],
                    'rating' => $topPerformer['rating'],
                    'review_count' => $topPerformer['review_count']
                ] : null,
                'action_text' => 'Details'
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'api has been deprecated',
            'data' => $data
        ], 200);
    }





    /**
     * @OA\Get(
     *      path="/v1.0/reports/branch-comparison",
     *      operationId="branchComparison",
     *      tags={"Reports"},
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
            $branchData = $this->aiProcessorService->getBranchComparisonData($branch, $startDate, $endDate);
            $comparisonData[] = $branchData;
            $allBranchMetrics[$branch->id] = $branchData['metrics'];
        }

        // Generate AI insights based on comparison
        $aiInsights = $this->aiProcessorService->generateBranchComparisonInsights($comparisonData, $allBranchMetrics);

        // Generate comparison highlights
        $comparisonHighlights = $this->aiProcessorService->generateComparisonHighlights($comparisonData);

        // Get sentiment trend over time (for chart)
        $sentimentTrend = $this->aiProcessorService->getSentimentTrendOverTime($branches, $startDate, $endDate);

        // Get staff performance complaints
        $staffComplaints = $this->aiProcessorService->getStaffComplaintsByBranch($branches, $startDate, $endDate);

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
     *      tags={"z.unused"},
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
            ->globalReviewFilters(0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['staff', 'user', 'guest_user', 'survey'])
            ->withCalculatedRating();

        $reviews = $reviewsQuery->get();

        // Calculate summary metrics
        $summary = $this->aiProcessorService->calculateBranchSummary($reviews);

        // Get AI insights
        $aiInsights = $this->aiProcessorService->generateAiInsights($reviews);

        // Get recommendations
        $recommendations = $this->aiProcessorService->generateBranchRecommendations($reviews);

        // Get recent reviews (last 5)
        $recentReviews = $this->recentReviewService->getRecentReviews($reviews);

        // Get staff performance (top 5)
        $staffPerformance = $this->aiProcessorService->getStaffPerformance($branchId, $businessId, $startDate, $endDate);

        $data = [
            'branch' => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'status' => $branch->is_active ? 'Active' : 'Inactive',
                'location' => $branch->location,
                'is_default' => $branch->is_default,
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
            'success' => false,
            'message' => 'Api has been dupricated',
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
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->get();

        $staffBReviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffBId)
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->withCalculatedRating()
            ->get();

        // Calculate metrics from ReviewValueNew
        $staffAMetrics = $this->aiProcessorService->calculateStaffMetricsFromReviewValue($staffAReviews, $staffA);
        $staffBMetrics = $this->aiProcessorService->calculateStaffMetricsFromReviewValue($staffBReviews, $staffB);

        // Calculate gaps
        $ratingGap = round($staffAMetrics['avg_rating'] - $staffBMetrics['avg_rating'], 1);
        $sentimentGap = $staffAMetrics['sentiment_breakdown']['positive'] - $staffBMetrics['sentiment_breakdown']['positive'];

        return response()->json([
            "success" => true,
            "message" => "Staff comparison data retrieved successfully",
            "data" => [
                'business_id' => (int) $businessId,
                'business_name' => $business->name,
                'comparison' => [
                    'rating_gap' => $ratingGap,
                    'rating_gap_message' => $this->aiProcessorService->getRatingGapMessage($ratingGap),
                    'sentiment_gap' => $sentimentGap,
                    'sentiment_gap_message' => $this->aiProcessorService->getSentimentGapMessage($sentimentGap),
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

        $staff = User::findOrFail($staffId);

        // Get reviews WITH calculated rating in one query
        $reviews = ReviewNew::where('business_id', $businessId)
            ->where('staff_id', $staffId)
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->withCalculatedRating()
            ->get();

        // Calculate average rating from calculated_rating field
        $avgRating = $reviews->isNotEmpty()
            ? round($reviews->avg('calculated_rating'), 1)
            : 0;

        $tenure = $this->reviewService->calculateTenure($staff->join_date);
        $ratingTrend = $this->reviewService->getRatingTrendFromReviewValue($reviews);
        $reviewSamples = $this->aiProcessorService->getReviewSamples($reviews);
        $recommendedTraining = $this->aiProcessorService->getRecommendedTraining($reviews);
        $skillGapAnalysis = $this->aiProcessorService->analyzeSkillGaps($reviews);
        $customerTone = $this->aiProcessorService->calculateCustomerTone($reviews);

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
                    'sentiment_distribution' => $this->aiProcessorService->calculateSentimentDistribution($reviews)
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
     *      path="/v1.0/reports/review-analytics/{businessId}",
     *      operationId="reviewAnalytics",
     *      tags={"review_management"},
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
            ->globalReviewFilters(0)
            ->filterByDateRange()
            ->withCalculatedRating();

        $reviewsQuery = $this->reviewService->applyFilters($reviewsQuery, $filters);
        $reviews = (clone $reviewsQuery)->get();

        // Calculate performance overview using ReviewValueNew
        $performance_overview = $this->aiProcessorService->calculatePerformanceOverviewFromReviewValue($reviews);

        $submissionsOverTime = $this->reviewMetricsService->getSubmissionsOverTime((clone $reviewsQuery), $filters['period']);

        $recentSubmissions = $this->recentReviewService->getRecentSubmissions($reviews);

        // NEW: Get top three staff
        $topStaff = $this->staffPerformanceService->getTopThreeStaff($businessId, $filters);

        $filterSummary = $this->getFilterSummary($filters, $business);

        return response()->json([
            'success' => true,
            'message' => 'Review analytics retrieved successfully',
            'data' => [
                'business_id' => (int) $businessId,
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
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month"})
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
        $user = $request->user();

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


        // Metrics and breakdown now handle date filtering internally via
        $metrics = $this->reviewService->calculateDashboardMetrics($businessId, null);

        // Get rating breakdown
        $ratingBreakdown = $this->reviewService->extractRatingBreakdown(
            ReviewNew::withCalculatedRating()
                ->globalReviewFilters(0)
                ->filterByDateRange()
                ->get()
        );

        // For other services that might still need explicit dateRange
        $dateRange = getDateRangeByPeriod($request->get('period', 'last_30_days'));

        // Get tags breakdown (NEW)
        $tagsBreakdown = $this->reviewService->extractTagsBreakdown($businessId, $dateRange, $user);

        // Get AI insights using existing AI pipeline
        $aiInsights = $this->businessAnalyticsService->getAiInsightsPanel($businessId, $dateRange);

        // Get staff performance using existing staff suggestions
        $staffPerformance = $this->staffPerformanceService->getStaffPerformanceSnapshot($businessId, $dateRange);

        // Get recent reviews feed
        $reviewFeed = $this->reviewFeedService->getReviewFeed($businessId, $dateRange);

        // Get available filters
        $filters = $this->reviewService->getAvailableFilters($businessId);

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
     * @OA\Get(
     *      path="/v1.0/dashboard/recent-reviews",
     *      operationId="getRecentReviews",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get recent reviews for authenticated user's business",
     *      description="Retrieve recent reviews feed with optional period filtering",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Parameter(
     *          name="is_overall",
     *          in="query",
     *          required=false,
     *          description="Filter reviews by overall status (1 = overall reviews, 0 = non-overall reviews, not specified = all reviews)",
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *          example=1
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Recent reviews retrieved successfully"),
     *              @OA\Property(property="data", type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="customer_name", type="string", example="John Doe"),
     *                      @OA\Property(property="rating", type="number", format="float", example=4.5),
     *                      @OA\Property(property="sentiment", type="string", example="positive"),
     *                      @OA\Property(property="comment", type="string", example="Great service!"),
     *                      @OA\Property(property="staff_name", type="string", example="Jane Smith", nullable=true),
     *                      @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-08T10:30:00Z"),
     *                      @OA\Property(property="is_voice_review", type="boolean", example=false),
     *                      @OA\Property(property="tags", type="array", @OA\Items(type="string"))
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation Error - Invalid period",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="period", type="array", @OA\Items(type="string", example="Invalid period. Only allowed: last_30_days, last_7_days, this_month, last_month, all_time"))
     *              )
     *          )
     *      )
     * )
     */
    public function getRecentReviews(Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        if (!$user->business_id) {
            throw new AuthorizationException('User does not have an associated business');
        }

        // Period validation and date range handling is now internal to the feed service/scope
        $reviewFeed = $this->reviewFeedService->getReviewFeed(
            businessId: $businessId,
            dateRange: null, // Let scope handle it from request
            limit: 10,
            user: $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Recent reviews retrieved successfully',
            'data' => $reviewFeed
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/rating-breakdown",
     *      operationId="getRatingBreakdown",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get rating breakdown for authenticated user's business",
     *      description="Retrieve rating distribution and statistics",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Rating breakdown retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="average_rating", type="number", format="float", example=4.2),
     *                  @OA\Property(property="total_reviews", type="integer", example=150),
     *                  @OA\Property(property="distribution", type="object",
     *                      @OA\Property(property="5_star", type="integer", example=75),
     *                      @OA\Property(property="4_star", type="integer", example=45),
     *                      @OA\Property(property="3_star", type="integer", example=20),
     *                      @OA\Property(property="2_star", type="integer", example=7),
     *                      @OA\Property(property="1_star", type="integer", example=3)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business")
     *          )
     *      )
     * )
     */
    public function getRatingBreakdown(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $businessId = $user->business_id;

        if (!$businessId) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have an associated business');
        }

        $ratingBreakdown = $this->reviewService->extractRatingBreakdown(
            ReviewNew::withCalculatedRating()
                ->globalReviewFilters(0)
                ->filterByDateRange()
                ->where('business_id', $businessId)
                ->get()
        );

        return response()->json([
            'success' => true,
            'message' => 'Rating breakdown retrieved successfully',
            'data' => $ratingBreakdown
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/tags-breakdown",
     *      operationId="getTagsBreakdown",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get tags breakdown for authenticated user's business",
     *      description="Retrieve tag distribution and analysis",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Tags breakdown retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="top_tags", type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="tag", type="string", example="service"),
     *                          @OA\Property(property="count", type="integer", example=45),
     *                          @OA\Property(property="percentage", type="number", format="float", example=30.0)
     *                      )
     *                  ),
     *                  @OA\Property(property="total_tags", type="integer", example=150)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business")
     *          )
     *      )
     * )
     */
    public function getTagsBreakdown(Request $request)
    {
        $user = $request->user();

        if (!$user->business_id) {
            throw new AuthorizationException('User does not have an associated business');
        }

        $businessId = $user->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );

        // Get tags breakdown
        $tagsBreakdown = $this->reviewService->extractTagsBreakdown($businessId, $dateRange, $user);

        return response()->json([
            'success' => true,
            'message' => 'Tags breakdown retrieved successfully',
            'data' => $tagsBreakdown
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/ai-insights",
     *      operationId="getAiInsights",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get AI insights for authenticated user's business",
     *      description="Retrieve AI-generated insights and recommendations",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="AI insights retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="summary", type="string", example="Customer satisfaction is improving"),
     *                  @OA\Property(property="key_insights", type="array", @OA\Items(type="string")),
     *                  @OA\Property(property="recommendations", type="array", @OA\Items(type="string")),
     *                  @OA\Property(property="sentiment_trend", type="string", example="positive")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business")
     *          )
     *      )
     * )
     */
    public function getAiInsights(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->business_id) {
            throw new AuthorizationException('User does not have an associated business');
        }

        $businessId = $user->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );

        // Get AI insights
        $aiInsights = $this->businessAnalyticsService->getAiInsightsPanel($businessId, $dateRange, $user);

        return response()->json([
            'success' => true,
            'message' => 'AI insights retrieved successfully',
            'data' => $aiInsights
        ], 200);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/staff-performance-snapshot",
     *      operationId="getStaffPerformanceSnapshot",
     *      tags={"dashboard_management.staff"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get staff performance snapshot for authenticated user's business",
     *      description="Retrieve staff performance metrics and rankings",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Staff performance retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="top_performers", type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="staff_id", type="integer", example=1),
     *                          @OA\Property(property="staff_name", type="string", example="John Doe"),
     *                          @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                          @OA\Property(property="review_count", type="integer", example=25)
     *                      )
     *                  ),
     *                  @OA\Property(property="average_performance", type="number", format="float", example=4.2)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business")
     *          )
     *      )
     * )
     */
    public function getStaffPerformanceSnapshot(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->business_id) {
            throw new AuthorizationException('User does not have an associated business');
        }

        $businessId = $user->business_id;
        $period = $request->get('period', 'last_30_days');

        // Get period dates
        $dateRange = $period === 'all_time' ? null : getDateRangeByPeriod($period);

        // Get staff performance
        $staffPerformance = $this->staffPerformanceService->getStaffPerformanceSnapshot($businessId, $dateRange);

        return response()->json([
            'success' => true,
            'message' => 'Staff performance retrieved successfully',
            'data' => $staffPerformance
        ], 200);
    }


    /**
     * @OA\Get(
     *      path="/v1.0/dashboard/metrics",
     *      operationId="getDashboardMetrics",
     *      tags={"dashboard_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get dashboard metrics for authenticated user's business",
     *      description="Get comprehensive dashboard metrics data with AI insights and analytics for the authenticated user's business",
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          required=false,
     *          description="Period: last_30_days, last_7_days, this_month, last_month, all_time",
     *          example="last_30_days",
     *         @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month", "all_time"})
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Dashboard metrics retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="avg_overall_rating", type="object",
     *                      @OA\Property(property="value", type="number", example=4.2),
     *                      @OA\Property(property="change", type="number", example=5.0, nullable=true),
     *                      @OA\Property(property="previous_value", type="number", example=4.0),
     *                      @OA\Property(property="calculated_from", type="string", example="review_value_news (via calculated_rating)"),
     *                      @OA\Property(property="review_count", type="integer", example=150)
     *                  ),
     *                  @OA\Property(property="ai_sentiment_score", type="object",
     *                      @OA\Property(property="value", type="number", example=7.5),
     *                      @OA\Property(property="max", type="integer", example=10),
     *                      @OA\Property(property="change", type="number", example=3.2, nullable=true),
     *                      @OA\Property(property="review_count", type="integer", example=150)
     *                  ),
     *                  @OA\Property(property="total_reviews", type="object",
     *                      @OA\Property(property="value", type="integer", example=150),
     *                      @OA\Property(property="change", type="number", example=25.0, nullable=true)
     *                  ),
     *                  @OA\Property(property="positive_negative_ratio", type="object",
     *                      @OA\Property(property="positive", type="integer", example=70),
     *                      @OA\Property(property="negative", type="integer", example=15),
     *                      @OA\Property(property="positive_count", type="integer", example=105),
     *                      @OA\Property(property="negative_count", type="integer", example=22),
     *                      @OA\Property(property="review_count", type="integer", example=150)
     *                  ),
     *                  @OA\Property(property="staff_linked_reviews", type="object",
     *                      @OA\Property(property="percentage", type="integer", example=60),
     *                      @OA\Property(property="count", type="integer", example=90),
     *                      @OA\Property(property="total", type="integer", example=150),
     *                      @OA\Property(property="review_count", type="integer", example=150)
     *                  ),
     *                  @OA\Property(property="voice_reviews", type="object",
     *                      @OA\Property(property="percentage", type="integer", example=25),
     *                      @OA\Property(property="count", type="integer", example=38),
     *                      @OA\Property(property="total", type="integer", example=150),
     *                      @OA\Property(property="review_count", type="integer", example=150)
     *                  ),
     *                  @OA\Property(property="rating_distribution", type="object",
     *                      @OA\Property(property="5_star", type="integer", example=75),
     *                      @OA\Property(property="4_star", type="integer", example=45),
     *                      @OA\Property(property="3_star", type="integer", example=20),
     *                      @OA\Property(property="2_star", type="integer", example=7),
     *                      @OA\Property(property="1_star", type="integer", example=3)
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - Invalid period",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object",
     *                  @OA\Property(property="period", type="array", @OA\Items(type="string", example="Invalid period. Only allowed: last_30_days, last_7_days, this_month, last_month, all_time"))
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User has no business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User does not have an associated business"),
     *              @OA\Property(property="data", type="null")
     *          )
     *      )
     * )
     */
    public function getDashboardMetrics(Request $request)
    {
        // Get business_id from authenticated user
        $user = auth()->user();

        if (!$user->business_id) {
            throw new NotFoundHttpException('Business not found');
        }

        $businessId = $user->business_id;

        // Validate period and get date range using service
        $dateRange = $this->dashboardService->validateAndGetDateRange(
            $request->get('period', 'last_30_days')
        );
        // Calculate metrics using DashboardService
        $metrics = $this->dashboardService->calculateMetrics($businessId, $dateRange, $user);


        return response()->json([
            'success' => true,
            'message' => 'Dashboard metrics retrieved successfully',
            'data' => $metrics
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
        $query = ReviewNew::with(['value'])
            ->where("business_id", $businessId)
            ->globalReviewFilters(0)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('order_no', 'asc')
            ->withCalculatedRating();



        $data = $this->reviewService->extractRatingBreakdown($query->get());

        return response($data, 200);
    }
}
