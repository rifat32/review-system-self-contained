<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OpenAITokenUsage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OpenAITokenReportController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/openai-tokens/report",
     *      operationId="getOpenAITokenUsageReport",
     *      tags={"OpenAI Tokens"},
     *      summary="Get OpenAI token usage report by business",
     *      description="Returns token usage summary including total tokens, costs, and breakdown by business",
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Parameter(
     *          name="business_id",
     *          in="query",
     *          description="Business ID to filter by specific business",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date for filtering (format: YYYY-MM-DD)",
     *          required=false,
     *          @OA\Schema(type="string", format="date", example="2024-01-01")
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date for filtering (format: YYYY-MM-DD)",
     *          required=false,
     *          @OA\Schema(type="string", format="date", example="2024-01-31")
     *      ),
     *      @OA\Parameter(
     *          name="model",
     *          in="query",
     *          description="OpenAI model to filter by",
     *          required=false,
     *          @OA\Schema(type="string", enum={"gpt-4o-mini", "gpt-4o", "gpt-3.5-turbo"})
     *      ),
     *      @OA\Parameter(
     *          name="branch_id",
     *          in="query",
     *          description="Branch ID to filter by specific branch",
     *          required=false,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="summary", type="object",
     *                      @OA\Property(property="total_requests", type="integer", example=150),
     *                      @OA\Property(property="total_tokens", type="integer", example=45000),
     *                      @OA\Property(property="prompt_tokens", type="integer", example=30000),
     *                      @OA\Property(property="completion_tokens", type="integer", example=15000),
     *                      @OA\Property(property="total_cost", type="number", format="float", example=12.75),
     *                      @OA\Property(property="avg_tokens_per_request", type="number", format="float", example=300),
     *                      @OA\Property(property="avg_cost_per_request", type="number", format="float", example=0.085),
     *                      @OA\Property(property="first_request", type="string", format="date-time", example="2024-01-01T10:00:00Z"),
     *                      @OA\Property(property="last_request", type="string", format="date-time", example="2024-01-31T15:30:00Z")
     *                  ),
     *                  @OA\Property(property="by_business", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="business_id", type="integer", example=1),
     *                          @OA\Property(property="business_name", type="string", example="Acme Corp"),
     *                          @OA\Property(property="total_requests", type="integer", example=50),
     *                          @OA\Property(property="total_tokens", type="integer", example=15000),
     *                          @OA\Property(property="total_cost", type="number", format="float", example=4.25),
     *                          @OA\Property(property="percentage_of_total", type="number", format="float", example=33.33)
     *                      )
     *                  ),
     *                  @OA\Property(property="by_model", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="model", type="string", example="gpt-4o-mini"),
     *                          @OA\Property(property="total_requests", type="integer", example=120),
     *                          @OA\Property(property="total_tokens", type="integer", example=36000),
     *                          @OA\Property(property="total_cost", type="number", format="float", example=9.0)
     *                      )
     *                  ),
     *                  @OA\Property(property="daily_trend", type="array",
     *                      @OA\Items(type="object",
     *                          @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                          @OA\Property(property="total_requests", type="integer", example=10),
     *                          @OA\Property(property="total_tokens", type="integer", example=3000),
     *                          @OA\Property(property="total_cost", type="number", format="float", example=0.75)
     *                      )
     *                  ),
     *                  @OA\Property(property="filters", type="object")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Server error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Internal server error")
     *          )
     *      )
     * )
     */
    public function getTokenUsageReport(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'business_id' => 'nullable|integer|exists:businesses,id',
                'branch_id' => 'nullable|integer|exists:branches,id',
                'start_date' => 'nullable|date|date_format:Y-m-d',
                'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
                'model' => 'nullable|string|in:gpt-4o-mini,gpt-4o,gpt-3.5-turbo',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $filters = $validator->validated();

            // 1. Get Overall Summary
            $summary = $this->getOverallSummary($filters);
            
            // 2. Get Breakdown by Business
            $byBusiness = $this->getBusinessBreakdown($filters);
            
            // 3. Get Breakdown by Model
            $byModel = $this->getModelBreakdown($filters);
            
            // 4. Get Daily Trend (last 30 days)
            $dailyTrend = $this->getDailyTrend($filters);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'by_business' => $byBusiness,
                    'by_model' => $byModel,
                    'daily_trend' => $dailyTrend,
                    'filters' => $filters
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Token usage report error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate token usage report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get overall summary statistics
     */
    private function getOverallSummary(array $filters): array
    {
        $query = OpenAITokenUsage::query();

        $this->applyFilters($query, $filters);

        $result = $query->selectRaw('
            COUNT(*) as total_requests,
            SUM(prompt_tokens) as total_prompt_tokens,
            SUM(completion_tokens) as total_completion_tokens,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost,
            AVG(total_tokens) as avg_tokens_per_request,
            AVG(estimated_cost) as avg_cost_per_request,
            MIN(created_at) as first_request,
            MAX(created_at) as last_request
        ')->first();

        return [
            'total_requests' => (int) ($result->total_requests ?? 0),
            'total_tokens' => (int) ($result->total_tokens ?? 0),
            'prompt_tokens' => (int) ($result->total_prompt_tokens ?? 0),
            'completion_tokens' => (int) ($result->total_completion_tokens ?? 0),
            'total_cost' => (float) ($result->total_cost ?? 0),
            'avg_tokens_per_request' => (float) ($result->avg_tokens_per_request ?? 0),
            'avg_cost_per_request' => (float) ($result->avg_cost_per_request ?? 0),
            'first_request' => $result->first_request ? $result->first_request->toISOString() : null,
            'last_request' => $result->last_request ? $result->last_request->toISOString() : null,
        ];
    }

    /**
     * Get breakdown by business
     */
    private function getBusinessBreakdown(array $filters): array
    {
        $query = OpenAITokenUsage::with('business');

        $this->applyFilters($query, $filters);

        $results = $query->selectRaw('
            business_id,
            COUNT(*) as total_requests,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost
        ')
        ->whereNotNull('business_id')
        ->groupBy('business_id')
        ->orderByDesc('total_tokens')
        ->limit(20) // Limit to top 20 businesses
        ->get();

        // Get total tokens for percentage calculation
        $totalTokens = $results->sum('total_tokens');

        return $results->map(function ($item) use ($totalTokens) {
            return [
                'business_id' => $item->business_id,
                'business_name' => optional($item->business)->name ?? 'Unknown Business',
                'total_requests' => (int) $item->total_requests,
                'total_tokens' => (int) $item->total_tokens,
                'total_cost' => (float) $item->total_cost,
                'percentage_of_total' => $totalTokens > 0 ? round(($item->total_tokens / $totalTokens) * 100, 2) : 0,
            ];
        })->toArray();
    }

    /**
     * Get breakdown by model
     */
    private function getModelBreakdown(array $filters): array
    {
        $query = OpenAITokenUsage::query();

        $this->applyFilters($query, $filters);

        $results = $query->selectRaw('
            model,
            COUNT(*) as total_requests,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost
        ')
        ->groupBy('model')
        ->orderByDesc('total_tokens')
        ->get();

        return $results->map(function ($item) {
            return [
                'model' => $item->model,
                'total_requests' => (int) $item->total_requests,
                'total_tokens' => (int) $item->total_tokens,
                'total_cost' => (float) $item->total_cost,
            ];
        })->toArray();
    }

    /**
     * Get daily trend for the last 30 days
     */
    private function getDailyTrend(array $filters): array
    {
        $query = OpenAITokenUsage::query();

        // Override start date to last 30 days for trend
        $filtersForTrend = $filters;
        $filtersForTrend['start_date'] = now()->subDays(30)->format('Y-m-d');
        
        $this->applyFilters($query, $filtersForTrend);

        $results = $query->selectRaw('
            DATE(created_at) as date,
            COUNT(*) as total_requests,
            SUM(total_tokens) as total_tokens,
            SUM(estimated_cost) as total_cost
        ')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        return $results->map(function ($item) {
            return [
                'date' => $item->date,
                'total_requests' => (int) $item->total_requests,
                'total_tokens' => (int) $item->total_tokens,
                'total_cost' => (float) $item->total_cost,
            ];
        })->toArray();
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['business_id'])) {
            $query->where('business_id', $filters['business_id']);
        }
        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }
        if (!empty($filters['model'])) {
            $query->where('model', $filters['model']);
        }
    }
}