<?php

namespace App\Http\Controllers;

use App\Services\Rule\RuleReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RuleReportController extends Controller
{
    protected RuleReportService $reportService;

    public function __construct(RuleReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/sentiment-analysis",
     *     operationId="sentimentAnalysisReport",
     *     tags={"Reports"},
     *     summary="Get sentiment analysis report",
     *     description="Aggregates and returns sentiment data for a business, including counts and trends.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         description="ID of the branch to filter by",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function sentimentAnalysis(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'branch_id' => 'nullable|exists:branches,id'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getSentimentAnalysisReport(
                $businessId,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
                $validated['branch_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            Log::error('Sentiment analysis report failed', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate sentiment analysis report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/emotion-intensity",
     *     operationId="emotionIntensityReport",
     *     tags={"Reports"},
     *     summary="Get emotion intensity report",
     *     description="Returns analysis of emotional strength (joy, frustration, etc.) within reviews.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function emotionIntensity(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getEmotionIntensityReport(
                $businessId,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate emotion intensity report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/rating-comment-mismatch",
     *     operationId="ratingCommentMismatchReport",
     *     tags={"Reports"},
     *     summary="Get rating/comment mismatch report",
     *     description="Detects reviews where the numeric rating and text sentiment do not align.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function ratingCommentMismatch(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getRatingCommentMismatchReport(
                $businessId,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate mismatch report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/category-issues",
     *     operationId="categoryIssuesReport",
     *     tags={"Reports"},
     *     summary="Get category issues report",
     *     description="Analyzes performance across business categories like Price, Quality, etc.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function categoryIssues(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getBasicRuleReport('CATEGORY_ISSUE_DETECTION', $businessId);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate category issues report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/service-types",
     *     operationId="serviceTypesReport",
     *     tags={"Reports"},
     *     summary="Get service types report",
     *     description="Identifies trends in specific service areas like Installation or Maintenance.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function serviceTypes(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getBasicRuleReport('SERVICE_TYPE_DETECTION', $businessId);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate service types report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/business-areas",
     *     operationId="businessAreasReport",
     *     tags={"Reports"},
     *     summary="Get business areas report",
     *     description="Tracks satisfaction scores across different physical locations or business units.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function businessAreas(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getBasicRuleReport('BUSINESS_AREA_DETECTION', $businessId);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate business areas report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/staff-mentions",
     *     operationId="staffMentionsReport",
     *     tags={"Reports"},
     *     summary="Get staff mentions report",
     *     description="Aggregates individual staff performance based on customer text mentions.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function staffMentions(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getBasicRuleReport('STAFF_MENTION_DETECTION', $businessId);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate staff mentions report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/staff-performance-risk",
     *     operationId="staffPerformanceRiskReport",
     *     tags={"Reports"},
     *     summary="Get staff performance risk report",
     *     description="Identifies staff members with recurring negative mentions or behavioral concerns.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function staffPerformanceRisk(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getBasicRuleReport('STAFF_PERFORMANCE_RISK', $businessId);

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate staff performance risk report'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/reports/flagged-reviews",
     *     operationId="flaggedReviewsReport",
     *     tags={"Reports"},
     *     summary="Get flagged reviews report",
     *     description="List and analysis of reviews flagged by AI for urgent attention.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Start date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="End date for filtering reviews (Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful report generation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server Error")
     * )
     */
    public function flaggedReviews(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $businessId = $request->user()->business_id;

        try {
            $report = $this->reportService->getFlaggedReviewsReport(
                $businessId,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate flagged reviews report'
            ], 500);
        }
    }
}
