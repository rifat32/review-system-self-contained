<?php

namespace App\Http\Controllers;

use App\Models\BranchMember;
use App\Models\Branch;
use App\Models\User;
use App\Http\Requests\BranchMemberRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="Branch",
 *     type="object",
 *     title="Branch",
 *     description="Branch model representing a business location",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="business_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Main Branch"),
 *     @OA\Property(property="address", type="string", example="123 Main Street"),
 *     @OA\Property(property="street", type="string", example="Main Street"),
 *     @OA\Property(property="door_no", type="string", example="123"),
 *     @OA\Property(property="city", type="string", example="New York"),
 *     @OA\Property(property="country", type="string", example="USA"),
 *     @OA\Property(property="postcode", type="string", example="10001"),
 *     @OA\Property(property="phone", type="string", example="+1-555-0123"),
 *     @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(property="is_geo_enabled", type="boolean", example=true),
 *     @OA\Property(property="branch_code", type="string", example="MAIN001"),
 *     @OA\Property(property="lat", type="number", format="float", example=40.7128),
 *     @OA\Property(property="long", type="number", format="float", example=-74.0060),
 *     @OA\Property(property="manager_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2026-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2026-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="business",
 *         type="object",
 *         description="Business information",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="My Business"),
 *         @OA\Property(property="email", type="string", format="email", example="business@example.com")
 *     ),
 *     @OA\Property(
 *         property="manager",
 *         type="object",
 *         description="Manager information",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="first_Name", type="string", example="John"),
 *         @OA\Property(property="last_Name", type="string", example="Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="manager@example.com")
 *     )
 * )
 */

/**
 * @OA\Schema(
 *     schema="BranchMember",
 *     type="object",
 *     title="BranchMember",
 *     description="Branch member assignment model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="branch_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="role", type="string", enum={"manager", "staff"}, example="staff"),
 *     @OA\Property(property="joining_date", type="string", format="date", example="2026-01-01"),
 *     @OA\Property(property="leaving_date", type="string", format="date", nullable=true, example=null),
 *     @OA\Property(property="remarks", type="string", nullable=true, example="Assigned via API"),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2026-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2026-01-01T00:00:00Z"),
 *     @OA\Property(
 *         property="branch",
 *         type="object",
 *         description="Branch information",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="business_id", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="Main Branch"),
 *         @OA\Property(property="address", type="string", example="123 Main St"),
 *         @OA\Property(property="city", type="string", example="New York"),
 *         @OA\Property(property="country", type="string", example="USA"),
 *         @OA\Property(property="phone", type="string", example="+1-555-0123"),
 *         @OA\Property(property="email", type="string", format="email", example="branch@example.com"),
 *         @OA\Property(property="is_active", type="boolean", example=true),
 *         @OA\Property(property="branch_code", type="string", example="MAIN001")
 *     ),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         description="User information",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="first_Name", type="string", example="John"),
 *         @OA\Property(property="last_Name", type="string", example="Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com")
 *     )
 * )
 */
class BranchMemberController extends Controller
{
    /**
     * @OA\Post(
     *     path="/v1.0/branch-members/assign",
     *     operationId="assignBranchMember",
     *     tags={"branch_management"},
     *     summary="Assign a user to a branch",
     *     description="Assigns a user to a specific branch with a defined role. The user and branch must belong to the authenticated user's business.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"branch_id", "user_id", "role"},
     *             @OA\Property(property="branch_id", type="integer", example=1, description="ID of the branch"),
     *             @OA\Property(property="user_id", type="integer", example=1, description="ID of the user to assign"),
     *             @OA\Property(property="role", type="string", enum={"manager", "staff"}, example="staff", description="Role in the branch"),
     *             @OA\Property(property="joining_date", type="string", format="date", example="2026-01-01", description="Date when the user joined the branch"),
     *             @OA\Property(property="remarks", type="string", example="Assigned via API", description="Optional remarks about the assignment")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Branch member assigned successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch member assigned successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/BranchMember"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "branch_id": {"The branch id field is required."},
     *                     "role": {"Role must be either manager or staff."}
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Branch or user not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Branch not found or does not belong to your business")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="User already assigned to branch",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User is already an active member of this branch")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to assign branch member"),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function assignBranchMember(BranchMemberRequest $request)
    {
        // Validation is handled by BranchMemberRequest
        $validatedData = $request->validated();

        $businessId = auth()->user()->business->id;

        // Verify the branch belongs to the user's business
        $branch = Branch::where('id', $validatedData['branch_id'])
            ->where('business_id', $businessId)
            ->first();

        if (!$branch) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found or does not belong to your business'
            ], 404);
        }

        // Verify the user belongs to the same business
        $user = User::where('id', $validatedData['user_id'])
            ->where('business_id', $businessId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or does not belong to your business'
            ], 404);
        }

        // Check if user is already a member of this branch
        $existingMember = BranchMember::where('branch_id', $validatedData['branch_id'])
            ->where('user_id', $validatedData['user_id'])
            ->whereNull('leaving_date')
            ->first();

        if ($existingMember) {
            return response()->json([
                'success' => false,
                'message' => 'User is already an active member of this branch'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $branchMember = BranchMember::create([
                'branch_id' => $validatedData['branch_id'],
                'user_id' => $validatedData['user_id'],
                'role' => $validatedData['role'],
                'joining_date' => $validatedData['joining_date'] ?? now()->toDateString(),
                'remarks' => $validatedData['remarks'] ?? null
            ]);

            $branchMember->load(['user', 'branch']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Branch member assigned successfully',
                'data' => $branchMember
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign branch member',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/v1.0/branch-members/{id}/unassign",
     *     operationId="unassignBranchMember",
     *     tags={"branch_management"},
     *     summary="Unassign a user from a branch",
     *     description="Removes a user from a branch by setting the leaving date. The branch member must belong to the authenticated user's business.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Branch member ID",
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="leaving_date", type="string", format="date", example="2026-01-01", description="Date when the user left the branch"),
     *             @OA\Property(property="remarks", type="string", example="Transferred to another branch", description="Optional remarks about the unassignment")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Branch member unassigned successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch member unassigned successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Branch member not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Branch member not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to unassign branch member"),
     *             @OA\Property(property="error", type="string", example="Database connection failed")
     *         )
     *     )
     * )
     */
    public function unassignBranchMember(Request $request, $id)
    {
        $request->validate([
            'leaving_date' => 'nullable|date|before_or_equal:today',
            'remarks' => 'nullable|string|max:1000'
        ]);

        $businessId = auth()->user()->business->id;

        $branchMember = BranchMember::whereHas('branch', function ($q) use ($businessId) {
            $q->where('business_id', $businessId);
        })
            ->find($id);

        if (!$branchMember) {
            return response()->json([
                'success' => false,
                'message' => 'Branch member not found'
            ], 404);
        }

        // Check if already unassigned
        if ($branchMember->leaving_date) {
            return response()->json([
                'success' => false,
                'message' => 'Branch member is already unassigned'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'leaving_date' => $request->leaving_date ?? now()->toDateString()
            ];

            if ($request->filled('remarks')) {
                $updateData['remarks'] = $branchMember->remarks
                    ? $branchMember->remarks . ' | ' . $request->remarks
                    : $request->remarks;
            }

            $branchMember->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Branch member unassigned successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to unassign branch member',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/v1.0/branch-members",
     *     operationId="getAllBranchMembers",
     *     tags={"branch_management"},
     *     summary="Get all branch members",
     *     description="Retrieves a paginated list of branch members for the authenticated user's business with optional filtering.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="branch_id",
     *         in="query",
     *         required=false,
     *         description="Filter by specific branch ID",
     *         @OA\Schema(type="integer"),
     *         example=1
     *     ),
     *
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         required=false,
     *         description="Filter by role",
     *         @OA\Schema(type="string", enum={"manager", "staff"}),
     *         example="staff"
     *     ),
     *
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         required=false,
     *         description="Show only active members (no leaving_date)",
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=15
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", minimum=1),
     *         example=1
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Branch members retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Branch members retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BranchMember")
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", example="https://api.example.com/v1.0/branch-members?page=1"),
     *                 @OA\Property(property="last", type="string", example="https://api.example.com/v1.0/branch-members?page=10"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/v1.0/branch-members?page=2")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10),
     *                 @OA\Property(property="path", type="string", example="https://api.example.com/v1.0/branch-members"),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=150)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    public function getAllBranchMembers(Request $request)
    {
        $businessId = auth()->user()->business->id;

        $query = BranchMember::with(['user', 'branch'])
            ->whereHas('branch', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
            });

        // Filter by branch if specified
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by role if specified
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        // Filter by active members (no leaving_date)
        if ($request->boolean('active_only')) {
            $query->whereNull('leaving_date');
        }

        $branchMembers = retrieve_data($query);

        return response()->json([
            'success' => true,
            'message' => 'Branch members retrieved successfully',
            'meta' => $branchMembers['meta'],
            'data' => $branchMembers['data']
        ], 200);
    }
}
