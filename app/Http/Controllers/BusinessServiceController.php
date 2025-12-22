<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessServiceRequest;
use App\Models\Business;
use App\Models\BusinessService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessServiceController extends Controller
{
    /**
     * @OA\Post(
     *      path="/v1.0/business-services",
     *      operationId="createBusinessService",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to create business service",
     *      description="This method is to create business service",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name"},
     *    @OA\Property(property="name", type="string", format="string", example="Room Service"),
     *    @OA\Property(property="description", type="string", format="string", example="24/7 room service available"),
     *    @OA\Property(property="question_title", type="string", format="string", example="How was our room service?"),
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

    public function createBusinessService(BusinessServiceRequest $request)
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

                // Create the business service
                $businessService = BusinessService::create($payload_data);

                // Load relationships for response
                $businessService->load('business_areas');

                // Return success response
                return response()->json([
                    "success" => true,
                    "message" => "Business service created successfully",
                    "data" => $businessService
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
     *      path="/v1.0/business-services",
     *      operationId="getAllBusinessServices",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get all business services",
     *      description="This method is to get all business services",
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
     *         description="Search in name and description",
     *         required=false,
     *         example="room service"
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

    public function getAllBusinessServices(Request $request)
    {
        try {
            $user = $request->user();

            $query = BusinessService::with('business_areas');
            $business = $user->business()->first();

            if (!$business) {
                return response()->json([
                    "success" => false,
                    "message" => "No business associated with your account"
                ], 403);
            }
            // Regular users see only their business services
            $query->where('business_id', $business->id);
            // Apply filters
            $query->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', $request->boolean('is_active'));
            })->when($request->filled('search_key'), function ($q) use ($request) {
                $searchTerm = $request->search_key;
                $q->where(function ($sq) use ($searchTerm) {
                    $sq->where('name', 'like', '%' . $searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $searchTerm . '%');
                });
            });

            $businessServices = retrieve_data($query);

            return response()->json([
                "success" => true,
                "message" => "Business services retrieved successfully",
                "data" => $businessServices
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
     *      path="/v1.0/business-services/{id}",
     *      operationId="businessServiceById",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Business service ID",
     *         required=true,
     *  example="1"
     *      ),
     *      summary="This method is to get business service by id",
     *      description="This method is to get business service by id",
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

    public function businessServiceById($id, Request $request)
    {
        try {
            // Check authorization
            $user = $request->user();
            $business = $user->business()->first();
            $businessId = $business ? $business->id : null;

            $businessService = BusinessService::with('business_areas')->find($id);

            if (!$businessService) {
                return response()->json([
                    "success" => false,
                    "message" => "Business service not found"
                ], 404);
            }

            if ($businessService->business_id !== $businessId) {
                return response()->json([
                    "success" => false,
                    "message" => "You do not own this business service"
                ], 403);
            }

            return response()->json([
                "success" => true,
                "message" => "Business service retrieved successfully",
                "data" => $businessService
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
     *      path="/v1.0/business-services/{id}",
     *      operationId="updateBusinessService",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update business service",
     *      description="This method is to update business service",
     *
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Business service ID",
     *         required=true,
     *         example=1
     *      ),
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name"},
     *    @OA\Property(property="name", type="string", format="string", example="Updated Room Service"),
     *    @OA\Property(property="description", type="string", format="string", example="Updated 24/7 room service available"),
     *    @OA\Property(property="question_title", type="string", format="string", example="How was our updated room service?"),
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

    public function updateBusinessService(BusinessServiceRequest $request, $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                // Check authorization
                $user = $request->user();
                $business = $user->business()->first();
                $businessId = $business ? $business->id : null;
                $businessService = BusinessService::find($id);

                if (!$businessService) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business service not found"
                    ], 404);
                }

                if ($businessService->business_id !== $businessId) {
                    return response()->json([
                        "success" => false,
                        "message" => "You do not own this business service"
                    ], 403);
                }

                $payload_data = $request->validated();

                // Update the business service
                $businessService->update($payload_data);

                // Load relationships for response
                $businessService->load('business_areas');

                return response()->json([
                    "success" => true,
                    "message" => "Business service updated successfully",
                    "data" => $businessService
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
     *      path="/v1.0/business-services/{ids}",
     *      operationId="deleteBusinessServices",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete business services by IDs",
     *      description="This method is to delete business services by IDs",
     *
     *      @OA\Parameter(
     *          name="ids",
     *          in="path",
     *          description="Comma-separated list of business service IDs to delete (e.g., 1,2,3)",
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
     *              @OA\Property(property="message", type="string", example="Business services deleted successfully."),
     *              @OA\Property(property="deleted_count", type="integer", example=3)
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Invalid IDs provided",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid business service IDs provided.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Business services not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business services not found: 4, 5")
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
    public function deleteBusinessServices(string $ids, Request $request)
    {
        try {
            // Parse IDs (comma-separated)
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid business service IDs provided.'
                ], 400);
            }

            // Get all business services to verify they exist and check permissions
            $businessServices = BusinessService::whereIn('id', $idArray)->get();

            // Check if all requested IDs exist
            $foundIds = $businessServices->pluck('id')->toArray();
            $missingIds = array_diff($idArray, $foundIds);

            if (!empty($missingIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business services not found: ' . implode(', ', $missingIds)
                ], 404);
            }

            // Check authorization
            $user = $request->user();
            $business = $user->business()->first();
            $businessId = $business ? $business->id : null;

            $unauthorizedIds = [];
            foreach ($businessServices as $service) {
                if ($service->business_id !== $businessId) {
                    $unauthorizedIds[] = $service->id;
                }
            }

            if (!empty($unauthorizedIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete services: ' . implode(', ', $unauthorizedIds)
                ], 403);
            }

            // Delete the business services
            $deletedCount = BusinessService::whereIn('id', $idArray)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Business services deleted successfully.',
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
     *      path="/v1.0/business-services/toggle",
     *      operationId="businessServiceToggle",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to toggle business service active status",
     *      description="This method is to toggle business service active status",
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

    public function businessServiceToggle(Request $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $id = $request->id;

                if (!$id) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business service ID is required"
                    ], 400);
                }

                $businessService = BusinessService::find($id);

                if (!$businessService) {
                    return response()->json([
                        "success" => false,
                        "message" => "Business service not found"
                    ], 404);
                }

                // Check authorization
                $user = $request->user();
                $business = $user->business()->first();
                $businessId = $business ? $business->id : null;

                if ($businessService->business_id !== $businessId) {
                    return response()->json([
                        "success" => false,
                        "message" => "You do not have permission to toggle this business service"
                    ], 403);
                }

                // Toggle the active status
                $businessService->update(['is_active' => !$businessService->is_active]);

                // Load relationships for response
                $businessService->load('business');

                return response()->json([
                    "success" => true,
                    "message" => "Business service status toggled successfully",
                    "data" => $businessService
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
