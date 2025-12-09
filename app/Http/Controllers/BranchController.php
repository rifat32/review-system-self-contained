<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * @OA\Get(
     *      path="/v1.0/branches",
     *      operationId="getBranches",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get all branches for the authenticated user's businesses",
     *      description="Retrieve a list of all branches belonging to businesses owned by the authenticated user.",
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branches retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branches retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="Main Branch"),
     *                      @OA\Property(property="address", type="string", example="123 Main St"),
     *                      @OA\Property(property="phone", type="string", example="+1234567890"),
     *                      @OA\Property(property="email", type="string", example="branch@example.com"),
     *                      @OA\Property(property="is_active", type="boolean", example=true),
     *                      @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                      @OA\Property(property="branch_code", type="string", example="BR001"),
     *                      @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                      @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                      @OA\Property(property="created_at", type="string", format="date-time"),
     *                      @OA\Property(property="updated_at", type="string", format="date-time")
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      )
     * )
     */
    public function getBranches(Request $request)
    {
        $user = $request->user();
        $businessIds = Business::where('OwnerID', $user->id)->pluck('id');

        $branches = Branch::whereIn('business_id', $businessIds)->get();

        return response()->json([
            'success' => true,
            'message' => 'Branches retrieved successfully',
            'data' => $branches
        ]);
    }

    /**
     * @OA\Post(
     *      path="/v1.0/branches",
     *      operationId="createBranch",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Create a new branch",
     *      description="Create a new branch for a business. Only business owners can perform this action.",
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"business_id", "name"},
     *              @OA\Property(property="business_id", type="integer", example=1, description="ID of the business"),
     *              @OA\Property(property="name", type="string", example="Main Branch", description="Name of the branch"),
     *              @OA\Property(property="address", type="string", example="123 Main St", description="Address of the branch"),
     *              @OA\Property(property="phone", type="string", example="+1234567890", description="Phone number of the branch"),
     *              @OA\Property(property="email", type="string", example="branch@example.com", description="Email of the branch"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="Whether the branch is active"),
     *              @OA\Property(property="is_geo_enabled", type="boolean", example=false, description="Whether geolocation is enabled for the branch"),
     *              @OA\Property(property="branch_code", type="string", example="BR001", description="Unique code for the branch"),
     *              @OA\Property(property="lat", type="string", format="float", example="40.7128", description="lat coordinate of the branch"),
     *              @OA\Property(property="long", type="string", format="float", example="-74.0060", description="long coordinate of the branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=201,
     *          description="Branch created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch created successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Main Branch"),
     *                  @OA\Property(property="address", type="string", example="123 Main St"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *              @OA\Property(property="email", type="string", example="branch@example.com"),
     *              @OA\Property(property="is_active", type="boolean", example=true),
     *              @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *              @OA\Property(property="branch_code", type="string", example="BR001"),
     *              @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *              @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to create branches for this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function createBranch(BranchRequest $request)
    {
        $validatedData = $request->validated();

        // Additional ownership check
        $business = Business::find($validatedData['business_id']);
        if ($business->OwnerID != $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create branches for this business'
            ], 403);
        }

        $branch = Branch::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'data' => $branch
        ], 201);
    }

    /**
     * @OA\Get(
     *      path="/v1.0/branches/{id}",
     *      operationId="getBranchById",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get a specific branch by ID",
     *      description="Retrieve details of a specific branch. Only business owners can access branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Main Branch"),
     *                  @OA\Property(property="address", type="string", example="123 Main St"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="email", type="string", example="branch@example.com"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
                        @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time"),
     *                  @OA\Property(
     *                      property="business",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="Business Name")
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to view this branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      )
     * )
     */
    public function getBranchById($id)
    {
        $branch = Branch::with('business')->find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Check ownership
        $user = auth('api')->user();
        if ($branch->business->OwnerID != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this branch'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Branch retrieved successfully',
            'data' => $branch
        ]);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/branches/{id}",
     *      operationId="updateBranch",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Update a specific branch",
     *      description="Update details of a specific branch. Only business owners or super admins can update branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="id", type="integer", example=1, description="Branch ID (required for PATCH/PUT)"),
     *              @OA\Property(property="business_id", type="integer", example=1, description="ID of the business"),
     *              @OA\Property(property="name", type="string", example="Updated Branch", description="Name of the branch"),
     *              @OA\Property(property="address", type="string", example="456 Updated St", description="Address of the branch"),
     *              @OA\Property(property="phone", type="string", example="+0987654321", description="Phone number of the branch"),
     *              @OA\Property(property="email", type="string", example="updated@example.com", description="Email of the branch"),
     *              @OA\Property(property="is_active", type="boolean", example=true, description="Whether the branch is active"),
     *              @OA\Property(property="is_geo_enabled", type="boolean", example=false, description="Whether geolocation is enabled for the branch"),
     *              @OA\Property(property="branch_code", type="string", example="BR001", description="Unique code for the branch"),
     *              @OA\Property(property="lat", type="string", format="float", example="40.7128", description="lat coordinate of the branch"),
     *              @OA\Property(property="long", type="string", format="float", example="-74.0060", description="long coordinate of the branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch updated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Updated Branch"),
     *                  @OA\Property(property="address", type="string", example="456 Updated St"),
     *                  @OA\Property(property="phone", type="string", example="+0987654321"),
     *                  @OA\Property(property="email", type="string", example="updated@example.com"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
     *                  @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to update this branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function updateBranch(BranchRequest $request, $id)
    {
        $validatedData = $request->validated();

        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Check ownership
        $user = $request->user();
        if ($branch->business->OwnerID != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this branch'
            ], 403);
        }

        $branch->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    /**
     * @OA\Delete(
     *      path="/v1.0/branches/{id}",
     *      operationId="deleteBranches",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Delete a specific branch",
     *      description="Delete a specific branch. Only business owners or super admins can delete branches of their businesses.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Branch ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch deleted successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to delete this branch")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Branch not found")
     *          )
     *      )
     * )
     */
    public function deleteBranches($id)
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Check ownership
        $user = auth('api')->user();
        if ($branch->business->OwnerID != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this branch'
            ], 403);
        }

        $branch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Branch deleted successfully'
        ]);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/branches/{id}/toggle-active",
     *      operationId="toggleBranchActive",
     *      tags={"branches"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Toggle branch active status",
     *      description="Toggle the active status of a specific branch.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          description="Branch ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Branch status toggled successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Branch activated successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="name", type="string", example="Main Branch"),
     *                  @OA\Property(property="is_active", type="boolean", example=true),
     *                  @OA\Property(property="is_geo_enabled", type="boolean", example=false),
     *                  @OA\Property(property="branch_code", type="string", example="BR001"),
     *                  @OA\Property(property="lat", type="string", format="float", example="40.7128"),
     *                  @OA\Property(property="long", type="string", format="float", example="-74.0060"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Branch not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="Branch not found")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Unauthorized",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\JsonContent(
     *                  @OA\Property(property="message", type="string", example="You do not own this branch")
     *              )
     *          )
     *      )
     * )
     */
    public function toggleBranchActive($id)
    {
        $user = auth('api')->user();

        // Find the branch
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        // Check if the user owns this branch through business ownership
        $business = Business::where('id', $branch->business_id)
            ->where('OwnerID', $user->id)
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'You do not own this branch'
            ], 403);
        }

        // Toggle the active status
        $branch->is_active = !$branch->is_active;
        $branch->save();

        $message = $branch->is_active ? 'Branch activated successfully' : 'Branch deactivated successfully';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $branch
        ]);
    }
}
