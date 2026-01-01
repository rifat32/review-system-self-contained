<?php

namespace App\Http\Controllers;

use App\Helpers\OpenAIProcessor;
use App\Models\Business;
use App\Models\BusinessAIModule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BusinessAIModuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/v1.0/business-ai-modules/{businessId}",
     *     operationId="getBusinessAIModules",
     *     tags={"business_ai_modules"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Get AI modules configuration for a business",
     *     description="Retrieve the AI modules configuration for a specific business. If no configuration exists, creates default configuration.",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="AI modules retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="AI modules retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="business_id", type="integer", example=1),
     *                 @OA\Property(property="language_translation", type="boolean", example=true),
     *                 @OA\Property(property="sentiment_analysis", type="boolean", example=true),
     *                 @OA\Property(property="emotion_detection", type="boolean", example=true),
     *                 @OA\Property(property="abuse_detection", type="boolean", example=true),
     *                 @OA\Property(property="explainability", type="boolean", example=true),
     *                 @OA\Property(property="category_analysis", type="boolean", example=true),
     *                 @OA\Property(property="staff_intelligence", type="boolean", example=true),
     *                 @OA\Property(property="service_unit_intelligence", type="boolean", example=true),
     *                 @OA\Property(property="business_recommendations", type="boolean", example=true),
     *                 @OA\Property(property="alerts", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="datetime", example="2025-01-01T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="datetime", example="2025-01-15T12:00:00.000000Z"),
     *                 @OA\Property(
     *                     property="business",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="Name", type="string", example="My Restaurant")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Super admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access AI modules")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business not found")
     *         )
     *     )
     * )
     */
    public function getBusinessAIModules($businessId, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to access AI modules"
            ], 403);
        }

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        try {
            // GET OR CREATE AI MODULES CONFIGURATION
            $aiModules = BusinessAIModule::firstOrCreate(
                ['business_id' => $businessId],
                BusinessAIModule::getDefaultForBusiness($businessId)
            );

            // LOAD BUSINESS RELATIONSHIP
            $aiModules->load('business');

            return response()->json([
                "success" => true,
                "message" => "AI modules retrieved successfully",
                "data" => $aiModules
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get business AI modules', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve AI modules",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/v1.0/business-ai-modules/{businessId}",
     *     operationId="updateBusinessAIModules",
     *     tags={"business_ai_modules"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Update AI modules configuration for a business (Super Admin only)",
     *     description="Update the AI modules configuration for a specific business. Only optional modules can be updated.",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="HTTP method override",
     *         required=true,
     *         example="PATCH"
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Optional modules configuration (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="category_analysis", type="boolean", example=true, description="Enable category analysis module"),
     *             @OA\Property(property="staff_intelligence", type="boolean", example=true, description="Enable staff intelligence module"),
     *             @OA\Property(property="service_unit_intelligence", type="boolean", example=false, description="Enable service unit intelligence module"),
     *             @OA\Property(property="business_recommendations", type="boolean", example=true, description="Enable business recommendations module"),
     *             @OA\Property(property="alerts", type="boolean", example=true, description="Enable alerts module")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="AI modules updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="AI modules updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="business_id", type="integer", example=1),
     *                 @OA\Property(property="language_translation", type="boolean", example=true),
     *                 @OA\Property(property="sentiment_analysis", type="boolean", example=true),
     *                 @OA\Property(property="emotion_detection", type="boolean", example=true),
     *                 @OA\Property(property="abuse_detection", type="boolean", example=true),
     *                 @OA\Property(property="explainability", type="boolean", example=true),
     *                 @OA\Property(property="category_analysis", type="boolean", example=true),
     *                 @OA\Property(property="staff_intelligence", type="boolean", example=true),
     *                 @OA\Property(property="service_unit_intelligence", type="boolean", example=false),
     *                 @OA\Property(property="business_recommendations", type="boolean", example=true),
     *                 @OA\Property(property="alerts", type="boolean", example=true),
     *                 @OA\Property(property="updated_at", type="string", format="datetime", example="2025-01-15T12:00:00.000000Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - Invalid module names",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid module names provided")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Super admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to update AI modules")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateBusinessAIModules($businessId, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to update AI modules"
            ], 403);
        }

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // VALIDATE REQUEST
        $validated = $request->validate([
            'category_analysis' => 'nullable|boolean',
            'staff_intelligence' => 'nullable|boolean',
            'service_unit_intelligence' => 'nullable|boolean',
            'business_recommendations' => 'nullable|boolean',
            'alerts' => 'nullable|boolean',
        ]);

        // CHECK FOR INVALID MODULE NAMES (TRYING TO UPDATE REQUIRED MODULES)
        $requiredModules = [
            'language_translation',
            'sentiment_analysis',
            'emotion_detection',
            'abuse_detection',
            'explainability'
        ];

        $invalidModules = array_intersect($requiredModules, array_keys($request->all()));
        if (!empty($invalidModules)) {
            return response()->json([
                "success" => false,
                "message" => "Cannot update required modules: " . implode(', ', $invalidModules)
            ], 400);
        }

        try {
            // UPDATE BUSINESS AI MODULES USING HELPER
            $result = OpenAIProcessor::updateBusinessAIModules($businessId, $validated);

            if (!$result) {
                return response()->json([
                    "success" => false,
                    "message" => "Failed to update AI modules"
                ], 500);
            }

            // GET UPDATED CONFIGURATION
            $aiModules = BusinessAIModule::where('business_id', $businessId)->first();

            return response()->json([
                "success" => true,
                "message" => "AI modules updated successfully",
                "data" => $aiModules
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update business AI modules', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to update AI modules",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/business-ai-modules/{businessId}/enabled",
     *     operationId="getEnabledBusinessAIModules",
     *     tags={"business_ai_modules"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Get enabled AI modules for a business",
     *     description="Retrieve the list of enabled AI modules for a specific business. This endpoint can be used by business owners to see their configuration.",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Enabled AI modules retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Enabled AI modules retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="business_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="enabled_modules",
     *                     type="object",
     *                     @OA\Property(property="language_translation", type="boolean", example=true),
     *                     @OA\Property(property="sentiment_analysis", type="boolean", example=true),
     *                     @OA\Property(property="emotion_detection", type="boolean", example=true),
     *                     @OA\Property(property="abuse_detection", type="boolean", example=true),
     *                     @OA\Property(property="explainability", type="boolean", example=true),
     *                     @OA\Property(property="category_analysis", type="boolean", example=true),
     *                     @OA\Property(property="staff_intelligence", type="boolean", example=true),
     *                     @OA\Property(property="service_unit_intelligence", type="boolean", example=false),
     *                     @OA\Property(property="business_recommendations", type="boolean", example=true),
     *                     @OA\Property(property="alerts", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="business",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="Name", type="string", example="My Restaurant")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Business owner access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access this business AI modules")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business not found")
     *         )
     *     )
     * )
     */
    public function getEnabledBusinessAIModules($businessId, Request $request)
    {
        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // CHECK IF USER IS BUSINESS OWNER OR SUPER ADMIN
        $isOwner = $business->OwnerID == $request->user()->id;
        $isSuperAdmin = $request->user()->hasRole("superadmin");

        if (!$isOwner && !$isSuperAdmin) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to access this business AI modules"
            ], 403);
        }

        try {
            // GET OR CREATE AI MODULES CONFIGURATION
            $aiModules = BusinessAIModule::firstOrCreate(
                ['business_id' => $businessId],
                BusinessAIModule::getDefaultForBusiness($businessId)
            );

            // LOAD BUSINESS RELATIONSHIP
            $aiModules->load('business');

            return response()->json([
                "success" => true,
                "message" => "Enabled AI modules retrieved successfully",
                "data" => [
                    'business_id' => $businessId,
                    'enabled_modules' => $aiModules->getEnabledModules(),
                    'business' => [
                        'id' => $business->id,
                        'Name' => $business->Name
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get enabled business AI modules', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve enabled AI modules",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/business-ai-modules/{businessId}/token-usage",
     *     operationId="getBusinessAITokenUsage",
     *     tags={"business_ai_modules"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Get AI token usage statistics for a business",
     *     description="Retrieve token usage statistics for a specific business over different periods.",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example=1,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Time period for statistics",
     *         required=false,
     *         example="month",
     *         @OA\Schema(type="string", enum={"day", "week", "month", "quarter", "year"}, default="month")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token usage statistics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token usage statistics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="period", type="string", example="month"),
     *                 @OA\Property(property="total_prompt_tokens", type="integer", example=15000),
     *                 @OA\Property(property="total_completion_tokens", type="integer", example=5000),
     *                 @OA\Property(property="total_tokens", type="integer", example=20000),
     *                 @OA\Property(property="total_cost", type="number", format="float", example=12.50),
     *                 @OA\Property(property="total_requests", type="integer", example=100),
     *                 @OA\Property(property="avg_tokens_per_request", type="number", format="float", example=200),
     *                 @OA\Property(
     *                     property="business",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="Name", type="string", example="My Restaurant")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Super admin access required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to access token usage")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Business not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business not found")
     *         )
     *     )
     * )
     */
    public function getBusinessAITokenUsage($businessId, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to access token usage"
            ], 403);
        }

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        try {
            // GET PERIOD FROM REQUEST OR DEFAULT TO MONTH
            $period = $request->input('period', 'month');

            // GET TOKEN USAGE STATISTICS
            $usageStats = OpenAIProcessor::getTokenUsageStatistics($businessId, $period);

            return response()->json([
                "success" => true,
                "message" => "Token usage statistics retrieved successfully",
                "data" => array_merge($usageStats, [
                    'business' => [
                        'id' => $business->id,
                        'Name' => $business->Name
                    ]
                ])
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to get business AI token usage', [
                'business_id' => $businessId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                "success" => false,
                "message" => "Failed to retrieve token usage statistics",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}