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
     * 1. Sentiment Analysis Report
     * GET /api/reports/sentiment-analysis
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
     * 2. Emotion Intensity Report
     * GET /api/reports/emotion-intensity
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
     * 3. Rating/Comment Mismatch Report
     * GET /api/reports/rating-comment-mismatch
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
     * 4. Category Issues Report
     * GET /api/reports/category-issues
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
     * 5. Service Types Report
     * GET /api/reports/service-types
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
     * 6. Business Areas Report
     * GET /api/reports/business-areas
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
     * 7. Staff Mentions Report
     * GET /api/reports/staff-mentions
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
     * 8. Staff Performance Risk Report
     * GET /api/reports/staff-performance-risk
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
     * 9. Flagged Reviews Report
     * GET /api/reports/flagged-reviews
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
