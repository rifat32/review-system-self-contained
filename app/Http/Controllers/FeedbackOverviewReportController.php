<?php

namespace App\Http\Controllers;

use App\Services\Report\FeedbackOverviewReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeedbackOverviewReportController extends Controller
{
    protected FeedbackOverviewReportService $feedbackOverviewReportService;

    public function __construct(FeedbackOverviewReportService $feedbackOverviewReportService)
    {
        $this->feedbackOverviewReportService = $feedbackOverviewReportService;
    }

    /**
     * @OA\Get(
     *      path="/v1.0/reports/feedback-overview",
     *      operationId="getFeedbackOverviewReport",
     *      tags={"Reports"},
     *      summary="Get Feedback Overview Report",
     *      description="Retrieves stacked channel feedback volume, rating distribution donut, branch volume chart, evidence feed, and recommended actions.",
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Parameter(
     *          name="business_id",
     *          in="query",
     *          required=false,
     *          description="Business ID",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="branch_id",
     *          in="query",
     *          required=false,
     *          description="Branch ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="survey_id",
     *          in="query",
     *          required=false,
     *          description="Survey ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          required=false,
     *          description="Start date (Y-m-d)",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          required=false,
     *          description="End date (Y-m-d)",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *          name="source",
     *          in="query",
     *          required=false,
     *          description="Source channel filter",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Feedback overview report retrieved successfully"
     *      )
     * )
     */
    public function getFeedbackOverview(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'nullable|integer',
            'branch_id'   => 'nullable|integer',
            'survey_id'   => 'nullable|integer',
            'staff_id'    => 'nullable|integer',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'source'      => 'nullable|string',
            'rating'      => 'nullable|integer|min:1|max:5',
            'sentiment'   => 'nullable|string',
            'category_id' => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $businessId = $validated['business_id'] ?? ($user ? $user->business_id : null);

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Business ID is required',
            ], 422);
        }

        try {
            $data = $this->feedbackOverviewReportService->getFeedbackOverviewReport((int)$businessId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Feedback overview report retrieved successfully',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Feedback overview report generation failed', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate feedback overview report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
