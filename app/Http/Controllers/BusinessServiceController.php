<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessServiceRequest;
use App\Models\Business;
use App\Models\BusinessService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createBusinessService(BusinessServiceRequest $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can create business services.');
            }

            return DB::transaction(function () use ($request, $user) {
                $payload_data = $request->validated();

                // ==================== BUSINESS VALIDATION ====================
                if (!$user->business_id) {
                    throw new AccessDeniedHttpException('No business associated with your account.');
                }

                $payload_data['business_id'] = $user->business_id;

                // ==================== CREATE BUSINESS SERVICE ====================
                $businessService = BusinessService::create($payload_data);
                $businessService->load('business_areas');

                return response()->json([
                    "success" => true,
                    "message" => "Business service created successfully",
                    "data" => $businessService
                ], 201);
            });
        } catch (Exception $e) {
            throw $e;
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
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function getAllBusinessServices(Request $request)
    {
        try {
            $user = $request->user();

            $query = BusinessService::with('business_areas');
            $businessId = $user->business_id;

            if (!$businessId) {
                throw new AccessDeniedHttpException('No business associated with your account');
            }
            // Regular users see only their business services
            $query->where('business_id', $businessId);
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
            throw $e;
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
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            // if (!$user->hasRole('business_owner')) {
            //     throw new AccessDeniedHttpException('Only business owners can view business services.');
            // }

            // ==================== FIND BUSINESS SERVICE ====================
            $businessService = BusinessService::with('business_areas')->findOrFail($id);

            // ==================== OWNERSHIP VALIDATION ====================
            if ($businessService->business_id !== $user->business_id) {
                throw new AccessDeniedHttpException('This business service does not belong to your business.');
            }

            return response()->json([
                "success" => true,
                "message" => "Business service retrieved successfully",
                "data" => $businessService
            ], 200);
        } catch (Exception $e) {
            throw $e;
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
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function updateBusinessService(BusinessServiceRequest $request, $id)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can update business services.');
            }

            return DB::transaction(function () use ($request, $id, $user) {
                // ==================== BUSINESS VALIDATION ====================
                if (!$user->business_id) {
                    throw new AccessDeniedHttpException('No business associated with your account.');
                }

                // ==================== FIND BUSINESS SERVICE ====================
                $businessService = BusinessService::where('id', $id)
                    ->where('business_id', $user->business_id)
                    ->firstOrFail();

                // ==================== UPDATE BUSINESS SERVICE ====================
                $payload_data = $request->validated();
                $businessService->update($payload_data);
                $businessService->load('business_areas');

                return response()->json([
                    "success" => true,
                    "message" => "Business service updated successfully",
                    "data" => $businessService
                ], 200);
            });
        } catch (Exception $e) {
            throw $e;
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
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can delete business services.');
            }

            // ==================== PARSE AND VALIDATE IDS ====================
            $idArray = array_filter(array_map('intval', explode(',', $ids)));

            if (empty($idArray)) {
                throw new BadRequestHttpException('Invalid business service IDs provided.');
            }

            return DB::transaction(function () use ($idArray, $user) {
                // ==================== FETCH BUSINESS SERVICES ====================
                $businessServices = BusinessService::whereIn('id', $idArray)->get();

                // Check if all requested IDs exist
                $foundIds = $businessServices->pluck('id')->toArray();
                $missingIds = array_diff($idArray, $foundIds);

                if (!empty($missingIds)) {
                    throw new NotFoundHttpException('Business services not found: ' . implode(', ', $missingIds));
                }

                // ==================== VALIDATE PERMISSIONS ====================
                $unauthorizedIds = [];

                foreach ($businessServices as $service) {
                    if ($service->business_id !== $user->business_id) {
                        $unauthorizedIds[] = $service->id;
                    }
                }

                if (!empty($unauthorizedIds)) {
                    throw new AccessDeniedHttpException('You do not have permission to delete services: ' . implode(', ', $unauthorizedIds));
                }

                // ==================== DELETE BUSINESS SERVICES ====================
                $deletedCount = BusinessService::whereIn('id', $idArray)->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Business services deleted successfully.',
                    'data' => ['deleted_count' => $deletedCount]
                ], 200);
            });
        } catch (Exception $e) {
            throw $e;
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
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can toggle business service status.');
            }

            return DB::transaction(function () use ($request, $user) {
                // ==================== VALIDATE REQUEST ====================
                $id = $request->id;

                if (!$id) {
                    throw new BadRequestHttpException('Business service ID is required.');
                }

                // ==================== FIND BUSINESS SERVICE ====================
                $businessService = BusinessService::findOrFail($id);

                // ==================== OWNERSHIP VALIDATION ====================
                if ($businessService->business_id !== $user->business_id) {
                    throw new AccessDeniedHttpException('This business service does not belong to your business.');
                }

                // ==================== TOGGLE STATUS ====================
                $businessService->update(['is_active' => !$businessService->is_active]);
                $businessService->load('business');

                return response()->json([
                    "success" => true,
                    "message" => "Business service status toggled successfully",
                    "data" => $businessService
                ], 200);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }
    /**
     * @OA\Post(
     *      path="/v1.0/business-services/with-area",
     *      operationId="createBusinessServiceWithArea",
     *      tags={"business_service_management"},
     *       security={
     *           {"bearerAuth": {}}
     *      },
     *      summary="Create business service and areas together",
     *      description="Create a new business service and link it with multiple areas",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name", "areas"},
     *            @OA\Property(property="name", type="string", format="string", example="Spa Service"),
     *            @OA\Property(property="description", type="string", format="string", example="Luxury spa treatments"),
     *            @OA\Property(property="question_title", type="string", format="string", example="How was your spa experience?"),
     *            @OA\Property(property="is_active", type="boolean", example=true),
     *            @OA\Property(
     *                property="areas",
     *                type="array",
     *                @OA\Items(
     *                    type="object",
     *                    required={"area_name"},
     *                    @OA\Property(property="area_name", type="string", example="Main Spa Area"),
     *                    @OA\Property(property="is_active", type="boolean", example=true)
     *                )
     *            )
     *         ),
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Created successfully",
     *       @OA\JsonContent(),
     *       ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     * @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *   @OA\JsonContent()
     * ),
     *  @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   )
     * )
     */
    public function createBusinessServiceWithArea(Request $request)
    {
        try {
            $user = $request->user();

            // ==================== AUTHORIZATION ====================
            if (!$user->hasRole('business_owner')) {
                throw new AccessDeniedHttpException('Only business owners can create business services.');
            }

            // ==================== VALIDATION ====================
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'question_title' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'areas' => 'array|present',
                'areas.*.area_name' => 'required|string|max:255',
                'areas.*.is_active' => 'nullable|boolean',
            ]);

            return DB::transaction(function () use ($request, $user) {
                // ==================== BUSINESS VALIDATION ====================
                if (!$user->business_id) {
                    throw new AccessDeniedHttpException('No business associated with your account.');
                }

                // 1. Create Business Service
                $servicePayload = [
                    'business_id' => $user->business_id,
                    'name' => $request->name,
                    'description' => $request->description,
                    'question_title' => $request->question_title,
                    'is_active' => $request->input('is_active', true),
                ];

                $businessService = BusinessService::create($servicePayload);

                // 2. Handle Business Areas
                if ($request->has('areas')) {
                    foreach ($request->areas as $areaData) {
                        $areaPayload = [
                            'business_id' => $user->business_id,
                            'business_service_id' => $businessService->id,
                            'area_name' => $areaData['area_name'],
                            'is_active' => $areaData['is_active'] ?? true,
                        ];
                        \App\Models\BusinessArea::create($areaPayload);
                    }
                }

                $businessService->load('business_areas');

                return response()->json([
                    "success" => true,
                    "message" => "Business service and areas created successfully",
                    "data" => $businessService
                ], 201);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }
}
