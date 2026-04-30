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
use App\Services\Rule\RuleReportService;
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
    private $ruleReportService;

    public function __construct(
        DashboardService $dashboardService,
        AIProcessorService $aiProcessorService,
        BusinessAnalyticsService $businessAnalyticsService,
        RecentReviewService $recentReviewService,
        ReviewMetricsService $reviewMetricsService,
        ReviewService $reviewService,
        StaffPerformanceService $staffPerformanceService,
        ReviewFeedService $reviewFeedService,
        ReviewTopicService $reviewTopicService,
        RuleReportService $ruleReportService
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
        $this->ruleReportService = $ruleReportService;
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
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
        ]);

        // Parse branch IDs
        $branchIds = explode(',', $request->branch_ids);
        $branchIds = array_map('intval', $branchIds);
        $branchIds = array_slice($branchIds, 0, 5); // Limit to max 5 branches

        if (count($branchIds) === 0) {
            throw new AuthorizationException('At least one branch ID is required');
        }

        // Get date range (default: last 30 days)
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::now()->endOfDay();

        // Get branches with business info
        $branches = Branch::with(['business', 'manager'])
            ->whereIn('id', $branchIds)
            ->get();

        if ($branches->isEmpty()) {
            throw new NotFoundHttpException('No branches found');
        }


        // Collect all branch data
        $comparisonData = [];
        $allBranchMetrics = [];

        foreach ($branches as $branch) {
            $branchData = $this->dashboardService->getBranchComparisonData($branch, $startDate, $endDate);
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

        // Get category-level sentiment comparison
        $categorySentiment = $this->aiProcessorService->getBranchCategorySentiment($branches, $startDate, $endDate);

        $data = [
            'selected_branches' => $branches->pluck('name'),
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'period_days' => $startDate->diffInDays($endDate)
            ],
            'branches' => $comparisonData,
            'category_sentiment' => $categorySentiment,
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
            ->globalReviewFilters(0, 0, 1)
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
     *      path="/v1.0/reports/staff-comparison",
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
     *       security={
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
    public function staffComparison(Request $request)
    {
        $request->validate([
            'staff_a_id' => 'required|integer|exists:users,id',
            'staff_b_id' => 'required|integer|exists:users,id'
        ]);
        $businessId = auth()->user()->business_id;

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
        $sentimentGap = $staffAMetrics['sentiment_breakdown']['positive']['percentage'] - $staffBMetrics['sentiment_breakdown']['positive']['percentage'];

        return response()->json([
            "success" => true,
            "message" => "Staff comparison data retrieved successfully",
            "data" => [
                'business_id' => (int) $business->id,
                'business_name' => $business->Name,
                'comparison' => [
                    'rating_gap' => $ratingGap,
                    'rating_gap_message' => $this->aiProcessorService->getRatingGapMessage($ratingGap, $staffA->first_Name . ' ' . $staffA->last_Name, $staffB->first_Name . ' ' . $staffB->last_Name),
                    'sentiment_gap' => $sentimentGap,
                    'sentiment_gap_message' => $this->aiProcessorService->getSentimentGapMessage($sentimentGap, $staffA->first_Name . ' ' . $staffA->last_Name, $staffB->first_Name . ' ' . $staffB->last_Name),
                    'better_performer' => $ratingGap >= 0 ? $staffA->name : $staffB->name
                ],
                'staff_a' => $staffAMetrics,
                'staff_b' => $staffBMetrics
            ]
        ], 200);
    }



    private function getStaffInsightsData($businessId, $dateRange)
    {
        // Get reviews with staff for the current period
        $staffReviewQuery = ReviewNew::where('business_id', $businessId)
            ->whereNotNull('staff_id')
            ->when($dateRange, fn($query) => $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]))
            ->globalReviewFilters(0)
            ->with('staff')
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
                $staff = $reviewsArray[0]->staff ?? null;
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
                    'id' => $topStaff['staff_id'],
                    'name' => $topStaff['staff_name'],
                    'rating' => $topStaff['avg_rating'],
                    'staff_image' => $topStaff['staff_image'],
                    'review_count' => $topStaff['review_count']
                ];
            }
        }

        return [
            'overall_sentiment' => $sentiment_status,
            'top_performer' => $topPerformer,
            'action_text' => 'Details'
        ];
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
     * Get unified dashboard data
     * GET /v1.0/dashboard/unified
     */
    public function getUnifiedDashboardData(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->business_id) {
            throw new AuthorizationException('User does not have an associated business');
        }

        $businessId = $user->business_id;
        $period = $request->get('period', 'last_30_days');

        // 1. Validate period and get date range
        $dateRange = $this->dashboardService->validateAndGetDateRange($period);

        // 2. Aggregate Data
        $data = [
            'metrics' => $this->dashboardService->calculateMetrics($businessId, $dateRange, $user),
            'boxes' => $this->ruleReportService->getDashboardBoxes($businessId, $period),
            'ai_insights' => $this->businessAnalyticsService->getAiInsightsPanel($businessId, $dateRange),
            'top_worst_services' => $this->businessAnalyticsService->analyzeBusinessServicesPerformance($businessId, $dateRange),
            'rating_breakdown' => $this->reviewService->extractRatingBreakdown($businessId, $dateRange, $user),
            'tags_breakdown' => $this->reviewService->extractTagsBreakdown($businessId, $dateRange, $user),
            'review_trends' => $this->reviewMetricsService->getSubmissionsOverTime(
                reviews: ReviewNew::where('business_id', $businessId)
                    ->globalReviewFilters(0)
                    ->filterByDateRange()
                    ->withCalculatedRating(),
                period: $request->get('trend_period', '30d')
            ),
            'staff_insights' => $this->getStaffInsightsData($businessId, $dateRange),
            'survey_insights' => [
                'active_surveys' => Survey::where('business_id', $businessId)
                    ->where('is_active', true)
                    ->when($dateRange, function ($query) use ($dateRange) {
                        $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                    })->count(),
                'recent_submissions' => ReviewNew::where('business_id', $businessId)
                    ->globalReviewFilters(0)
                    ->when($dateRange, function ($query) use ($dateRange) {
                        $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                    })->count(),
                'action_text' => 'Manage'
            ],
            'recent_reviews' => $this->recentReviewService->getRecentReviews($businessId, $dateRange, 5)
        ];

        return response()->json([
            'success' => true,
            'message' => 'Unified dashboard data retrieved successfully',
            'data' => $data
        ], 200);
    }
}
