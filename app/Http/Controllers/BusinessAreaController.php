<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessAreaRequest;
use App\Models\Business;
use App\Models\BusinessArea;
use App\Models\BusinessService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessAreaController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1.0/business-areas",
     *      operationId="createBusinessArea",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to create business area",
     *      description="This method is to create business area",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"business_service_id","area_name"},
     *    @OA\Property(property="business_service_id", type="integer", format="integer", example=1),
     *    @OA\Property(property="area_name", type="string", format="string", example="Main Hall"),
     *
     *         ),
     *      ),
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

    public function createBusinessArea(BusinessAreaRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $payload_data = $request->validated();

                $authUser = $request->user();
                $business = $authUser->business()->first();
                if (!$business) {
                    return response()->json([
                        "success" => false,
                        "message" => "No business associated with your account"
                    ], 403);
                }
                $payload_data['business_id'] = $business->id;

                // Check if the business_service belongs to the user's business
                $businessService = BusinessService::find($payload_data['business_service_id']);
                if (!$businessService) {
                    return response()->json([
                        "success" => false,
                        "message" => "The selected business service does not exist"
                    ], 400);
                }
                if ($businessService->business_id !== $business->id) {
                    return response()->json([
                        "success" => false,
                        "message" => "The selected business service does not belong to your business"
                    ], 403);
                }

                // Create the business area
                $businessArea = BusinessArea::create($payload_data);

                // Load relationships for response
                $businessArea->load('business_service');

                // Return success response
                return response()->json([
                    "success" => true,
                    "message" => "Business area created successfully",
                    "data" => $businessArea
                ], 201);
            });
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/v1.0/business-areas",
     *      operationId="getAllBusinessAreas",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get all business areas",
     *      description="This method is to get all business areas",
     *
     *      @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         example=20
     *      ),
     *      @OA\Parameter(
     *         name="business_service_id",
     *         in="query",
     *         description="Filter by business service ID",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         example=1
     *      ),
     *      @OA\Parameter(
     *         name="order_by",
     *         in="query",
     *         description="Order by column name",
     *         required=false,
     *         example="area_name"
     *      ),
     *      @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         example="desc"
     *      ),
     *      @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="Search in area name",
     *         required=false,
     *         example="main hall"
     *      ),
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
     *          description="Unprocessable Content",
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

    public function getAllBusinessAreas(Request $request)
    {
        try {
            $user = $request->user();

            $query = BusinessArea::with('business_service');

            // Regular users see only their business areas
            $business = $user->business()->first();
            $query->where('business_id', $business->id);

            // Apply filters
            $query->when($request->filled('business_service_id'), function ($q) use ($request) {
                $q->where('business_service_id', $request->business_service_id);
            })->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })->when($request->filled('search_key'), function ($q) use ($request) {
                $searchTerm = $request->search_key;
                $q->where('area_name', 'like', '%' . $searchTerm . '%');
            });

            $businessAreas = retrieve_data($query);

            return response()->json([
                "success" => true,
                "message" => "Business areas retrieved successfully",
                "data" => $businessAreas
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/v1.0/business-areas/{id}",
     *      operationId="businessAreaById",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Business area ID",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business area by id",
     *      description="This method is to get business area by id",
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
     *          description="Unprocessable Content",
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

    public function businessAreaById($id, Request $request)
    {
        try {
            // Check authorization
            $user = $request->user();
            $business = $user->business()->first();
            $businessId = $business ? $business->id : null;

            $businessArea = BusinessArea::with('business_service')->find($id);

            if (!$businessArea) {
                return response()->json([
                    "success" => false,
                    "message" => "Business area not found"
                ], 404);
            }

            if ($businessArea->business_id !== $businessId) {
                return response()->json([
                    "success" => false,
                    "message" => "The selected business area does not belong to your business"
                ], 403);
            }

            return response()->json([
                "success" => true,
                "message" => "Business area retrieved successfully",
                "data" => $businessArea
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/business-areas/{id}",
     *      operationId="updateBusinessArea",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update business area",
     *      description="This method is to update business area",
     *
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Business area ID",
     *         required=true,
     *         example=1
     *      ),
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"area_name"},
     *    @OA\Property(property="business_service_id", type="integer", format="integer", example=1),
     *    @OA\Property(property="area_name", type="string", format="string", example="Updated Main Hall"),
     *
     *         ),
     *      ),
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

    public function updateBusinessArea(BusinessAreaRequest $request, $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                // Check authorization
                $user = $request->user();
                $business = $user->business;
                $businessId = $business ? $business->id : null;
                $businessArea = BusinessArea::find($id);

                if (!$businessArea) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business area not found"
                    ], 404);
                }

                if ($businessArea->business_id !== $businessId) {
                    return response()->json([
                        "success" => false,
                        "message" => "The selected business area does not belong to your business"
                    ], 403);
                }

                $payload_data = $request->validated();

                // If business_service_id is provided, check it belongs to the business
                if (isset($payload_data['business_service_id'])) {
                    $businessService = BusinessService::find($payload_data['business_service_id']);
                    if (!$businessService->business_id !== $businessArea->business_id) {
                        return response()->json([
                            "success" => false,
                            "message" => "The selected business area does not belong to the business service"
                        ], 403);
                    }
                }

                // Update the business area
                $businessArea->update($payload_data);

                // Load relationships for response
                $businessArea->load('business_service');

                return response()->json([
                    "success" => true,
                    "message" => "Business area updated successfully",
                    "data" => $businessArea
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *      path="/v1.0/business-areas/{ids}",
     *      operationId="deleteBusinessAreas",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete business areas by IDs",
     *      description="This method is to delete business areas by IDs",
     *
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          description="Comma-separated list of business area IDs to delete (e.g., 1,2,3)",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              example="1,2,3"
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business areas deleted successfully."),
     *              @OA\Property(property="deleted_count", type="integer", example=3)
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Invalid IDs provided",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid business area IDs provided.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Business areas not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business areas not found: 4, 5")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal server error",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function deleteBusinessAreas(string $ids, Request $request)
    {
        try {
            // Parse IDs (comma-separated)
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid business area IDs provided.'
                ], 400);
            }

            // Get all business areas to verify they exist and check permissions
            $businessAreas = BusinessArea::whereIn('id', $idArray)->get();

            // Check if all requested IDs exist
            $foundIds = $businessAreas->pluck('id')->toArray();
            $missingIds = array_diff($idArray, $foundIds);

            if (!empty($missingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business areas not found: ' . implode(', ', $missingIds)
                ], 404);
            }

            // Check authorization for each area
            // Check authorization
            $user = $request->user();
            $business = $user->business()->first();
            $businessId = $business ? $business->id : null;
            $unauthorizedIds = [];

            foreach ($businessAreas as $area) {
                if ($area->business_id !== $businessId) {
                    $unauthorizedIds[] = $area->id;
                }
            }
            if (!empty($unauthorizedIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete areas: ' . implode(', ', $unauthorizedIds)
                ], 403);
            }

            // Delete the business areas
            $deletedCount = BusinessArea::whereIn('id', $idArray)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Business areas deleted successfully.',
                'data' => ['deleted_count' => $deletedCount]
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/business-areas/toggle",
     *      operationId="toggleBusinessArea",
     *      tags={"business_area_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle business area active status",
     *      description="This method is to toggle business area active status",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id"},
     *    @OA\Property(property="id", type="integer", format="integer", example=1),
     *
     *         ),
     *      ),
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
     *          description="Unprocessable Content",
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

    public function toggleBusinessArea(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $id = $request->id;

                if (!$id) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business area ID is required"
                    ], 400);
                }

                $businessArea = BusinessArea::find($id);

                if (!$businessArea) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business area not found"
                    ], 404);
                }

                // Check authorization
                $user = $request->user();
                $business = $user->business()->first();
                $businessId = $business ? $business->id : null;

                if ($businessArea->business_id !== $businessId) {
                    return response()->json([
                        "success" => false,
                        "message" => "The selected business area does not belong to your business"
                    ], 403);
                }

                // Toggle the active status
                $businessArea->update(['is_active' => !$businessArea->is_active]);

                // Load relationships for response
                $businessArea->load('business', 'business_service');

                return response()->json([
                    "success" => true,
                    "message" => "Business area status toggled successfully",
                    "data" => $businessArea
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Something went wrong",
                "original_message" => $e->getMessage()
            ], 500);
        }
    }
}
