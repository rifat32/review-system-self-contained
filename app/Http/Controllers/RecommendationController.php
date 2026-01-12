<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Helpers\InsightAggregationHelper;
use App\Helpers\RecommendationGenerator;
use App\Helpers\RuleEngineHelper;
use App\Helpers\ConfidenceCalculator;
use App\Models\Recommendation;
use App\Models\InsightRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RecommendationController extends Controller
{
    /**
     * Generate recommendations
     * POST /api/recommendations/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'days' => 'integer|min:1|max:90'
        ]);

        $businessId = $request->business_id;
        $days = $request->days ?? 30;

        try {
            // Step 1: Aggregate insights
            $aggregationResult = InsightAggregationHelper::aggregateReviewsForBusiness($businessId, $days);

            // Step 2: Generate recommendations
            $recommendations = RecommendationGenerator::generateFromInsights($businessId, $days);

            return response()->json([
                'success' => true,
                'aggregation' => $aggregationResult,
                'recommendations' => $recommendations,
                'generated_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List recommendations
     * GET /api/recommendations
     */
    public function index(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'limit' => 'integer|min:1|max:50',
            'type' => 'in:business,staff,area'
        ]);

        $query = Recommendation::where('business_id', $request->business_id);

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $recommendations = $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($request->limit ?? 10)
            ->get()
            ->map(function ($rec) {
                return [
                    'id' => $rec->id,
                    'type' => $rec->type,
                    'text' => $rec->text,
                    'confidence' => $rec->confidence,
                    'priority' => $rec->priority,
                    'evidence' => json_decode($rec->evidence, true),
                    'created_at' => $rec->created_at->format('Y-m-d H:i:s'),
                    'explain_url' => route('recommendations.explain', $rec->id)
                ];
            });

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
            'count' => $recommendations->count()
        ]);
    }

    /**
     * Get single recommendation with explanation
     * GET /api/recommendations/{id}/explain
     */
    public function explain($id)
    {
        $recommendation = Recommendation::findOrFail($id);
        $insight = $recommendation->insight;

        // Get confidence analysis
        $confidence = ConfidenceCalculator::calculateInsightConfidence($insight);

        // Get rule that triggered this
        $rule = $recommendation->rule;

        return response()->json([
            'success' => true,
            'recommendation' => [
                'id' => $recommendation->id,
                'text' => $recommendation->text,
                'type' => $recommendation->type,
                'confidence' => $recommendation->confidence,
                'priority' => $recommendation->priority,
                'created_at' => $recommendation->created_at->format('Y-m-d H:i:s')
            ],
            'explainability' => [
                'confidence_analysis' => $confidence,
                'insight_data' => [
                    'main_category' => $insight->main_category,
                    'sub_category' => $insight->sub_category,
                    'mentions' => $insight->mentions_count,
                    'severity' => $insight->severity,
                    'trend' => $insight->trend,
                    'time_period' => [
                        'start' => $insight->time_window_start->format('Y-m-d'),
                        'end' => $insight->time_window_end->format('Y-m-d')
                    ]
                ],
                'rule_used' => $rule ? [
                    'id' => $rule->id,
                    'name' => $rule->rule_name,
                    'category' => $rule->category,
                    'priority' => $rule->priority,
                    'conditions' => json_decode($rule->conditions, true)
                ] : null,
                'review_count' => count($insight->review_ids ?? []),
                'generation_logic' => 'Based on repeated customer feedback patterns and business rules'
            ]
        ]);
    }

    /**
     * Get dashboard insights
     * GET /api/dashboard/insights
     */
    public function dashboardInsights(Request $request)
    {
        $request->validate([
            'business_id' => 'required|integer|exists:businesses,id'
        ]);

        $insights = InsightAggregationHelper::getDashboardInsights($request->business_id, 5);
        $recommendations = RecommendationGenerator::getDashboardRecommendations($request->business_id, 3);

        return response()->json([
            'success' => true,
            'insights' => $insights,
            'recommendations' => $recommendations,
            'updated_at' => now()->format('Y-m-d H:i:s')
        ]);
    }
}