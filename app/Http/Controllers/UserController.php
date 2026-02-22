<?php

namespace App\Http\Controllers;


use App\Http\Resources\UserResource;
use App\Mail\ManagerWelcomeMail;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Requests\UserRequest;
use App\Models\BranchMember;
use Mail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class UserController extends Controller
{
    /**
     *
     * @OA\Get(
     *      path="/v1.0/customer-list",
     *      operationId="getCustomerReportSuperadmin",
     *      tags={"user_management.super_admin"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *    @OA\Parameter(
     * name="page",
     * in="query",
     * description="page",
     * required=true,
     * example="1"
     * ),
     *    @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="per_page",
     * required=true,
     * example="1"
     * ),
     *    @OA\Parameter(
     * name="search_term",
     * in="query",
     * description="search_term",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to get  Customer report",
     *      description="This method is to get Customer  report",
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
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function getCustomerReportSuperadmin(Request $request)
    {

        $userQuery = User::where([
            "type" => "customer"
        ]);

        // Search Term
        if (!empty($request->search_term)) {
            $userQuery = $userQuery->where(function ($query) use ($request) {
                $term = $request->search_term;


                $query->where("first_Name", "like", "%" . $term . "%");
                $query->orWhere("last_Name", "like", "%" . $term . "%");
                $query->orWhere("email", "like", "%" . $term . "%");
                $query->orWhere("password", "like", "%" . $term . "%");
                $query->orWhere("phone", "like", "%" . $term . "%");
                $query->orWhere("type", "like", "%" . $term . "%");
                $query->orWhere("post_code", "like", "%" . $term . "%");
                $query->orWhere("Address", "like", "%" . $term . "%");
                $query->orWhere("door_no", "like", "%" . $term . "%");
            });
        }

        //
        $data = retrieve_data($userQuery);

        //
        return response()->json([
            "success" => true,
            "message" => "Customer Report",
            "meta" => $data['meta'],
            "data" => $data['data'],
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/owner-list",
     *      operationId="getOwnerReport",
     *      tags={"user_management.super_admin"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *    @OA\Parameter(
     * name="page",
     * in="query",
     * description="page",
     * required=true,
     * example="1"
     * ),
     *    @OA\Parameter(
     * name="per_page",
     * in="query",
     * description="per_page",
     * required=true,
     * example="1"
     * ),
     *    @OA\Parameter(
     * name="search_term",
     * in="query",
     * description="search_term",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to get  Customer report",
     *      description="This method is to get Customer  report",
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
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request"
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found"
     *   ),
     *@OA\JsonContent()
     *      )
     *     )
     */

    public function getOwnerReport(Request $request)
    {
        $userQuery = User::with(["business", "roles"])->where([
            "type" => "business_Owner"
        ]);
        if (!empty($request->search_term)) {
            $userQuery = $userQuery->where(function ($query) use ($request) {
                $term = $request->search_term;


                $query->where("first_Name", "like", "%" . $term . "%");
                $query->orWhere("last_Name", "like", "%" . $term . "%");
                $query->orWhere("email", "like", "%" . $term . "%");
                $query->orWhere("password", "like", "%" . $term . "%");
                $query->orWhere("phone", "like", "%" . $term . "%");
                $query->orWhere("type", "like", "%" . $term . "%");
                $query->orWhere("post_code", "like", "%" . $term . "%");
                $query->orWhere("Address", "like", "%" . $term . "%");
                $query->orWhere("door_no", "like", "%" . $term . "%");
            });
        }

        //
        $data = retrieve_data($userQuery);

        //
        return response()->json([
            "success" => true,
            "message" => "Owner Report",
            "meta" => $data['meta'],
            "data" => $data['data'],
        ], 200);
    }

    /**
     * @OA\Delete(
     *     path="/v1.0/users/{id}",
     *     operationId="deleteUserById",
     *     tags={"user_management", "user_management.super_admin"},
     *     security={{"bearerAuth":{}}},
     *     summary="Delete user by id",
     *     description="
     *     Rules:
     *     - Superadmin can delete ONLY business owners and it's business
     *     - Business owner can delete users within their own business
     *     - Business owner cannot delete another business owner
     *     ",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User deleted successfully"),
     *             @OA\Property(property="data", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You are not authorized to delete user")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Bad Request")
     *         )
     *     )
     * )
     */

    public function deleteUserById(int $id, Request $request)
    {
        $authUser = $request->user();

        // Only superadmin or business_owner allowed
        if (
            !$authUser->hasRole('superadmin') &&
            !$authUser->hasRole('business_owner')
        ) {
            throw new AccessDeniedHttpException('Access denied : You can not perform thi action');
        }

        $user = User::findOrFail($id);

        /** SUPER ADMIN RULE */
        if ($authUser->hasRole('superadmin')) {

            $userRole = $user->roles->first();

            // superadmin can delete ONLY business owners
            if ($userRole->name !== User::USER_ROLE['CUSTOMER'] && $userRole->name !== User::USER_ROLE['BUSINESS_OWNER']) {
                throw new AccessDeniedHttpException('Super Admin can do business owner and custom delete only');
            }
        }

        /** BUSINESS OWNER RULE */
        if ($authUser->hasRole('business_owner')) {

            // must be same business
            if ($user->business_id !== $authUser->business_id) {
                throw new AccessDeniedHttpException('Access denied : You can not delete user from another business');
            }

            // business owner cannot delete
            if ($authUser->id === $user->id) {
                throw new AccessDeniedHttpException('Access denied : You can not delete yourself');
            }
        }

        // delete user
        $user->delete();


        return response()->json([
            "success" => true,
            "message" => "User deleted successfully",
            "data" => true
        ], 200);
    }




    /**
     * @OA\Get(
     *      path="/v1.0/users",
     *      operationId="getAllBusinessUsers",
     *      tags={"user_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get all users for the authenticated business",
     *      description="Retrieve a paginated list of all users belonging to the authenticated user's business. If no role filter is specified, only users with 'branch_manager' and 'business_staff' roles are returned.",
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number",
     *          required=false,
     *          example=1,
     *          @OA\Schema(type="integer", minimum=1)
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page",
     *          required=false,
     *          example=10,
     *          @OA\Schema(type="integer", minimum=1, maximum=100)
     *      ),
     *      @OA\Parameter(
     *          name="role",
     *          in="query",
     *          description="Filter users by role. If not specified, defaults to 'branch_manager' and 'business_staff' roles only",
     *          required=false,
     *          example="branch_manager",
     *          @OA\Schema(type="string", enum={"branch_manager", "business_staff"})
     *      ),
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term to filter users by name, email, phone, etc.",
     *          required=false,
     *          example="john",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="without_branch",
     *          in="query",
     *          description="Filter users without branch assignment",
     *          required=false,
     *          @OA\Schema(type="boolean")
     *      ),
     *      @OA\Parameter(
     *          name="ignore_id",
     *          in="query",
     *          description="User ID to include in results even if they have a branch (works with without_branch filter)",
     *          required=false,
     *          example=5,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="order_by",
     *          in="query",
     *          description="Field to order the users by, e.g., 'name', 'email', etc.",
     *          required=false,
     *          example="name",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="Sort order for the users, e.g., 'asc' or 'desc'.",
     *          required=false,
     *          example="desc",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Users retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *              @OA\Property(property="meta", type="object",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="per_page", type="integer", example=10),
     *                  @OA\Property(property="total", type="integer", example=25),
     *                  @OA\Property(property="last_page", type="integer", example=3),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="to", type="integer", example=10)
     *              ),
     *              @OA\Property(property="data", type="array",
     *                  @OA\Items(
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="first_Name", type="string", example="John"),
     *                      @OA\Property(property="last_Name", type="string", example="Doe"),
     *                      @OA\Property(property="email", type="string", example="john.manager@yopmail.com"),
     *                      @OA\Property(property="phone", type="string", example="+1234567890"),
     *                      @OA\Property(property="role", type="string", example="branch_manager"),
     *                      @OA\Property(property="post_code", type="string", example="12345"),
     *                      @OA\Property(property="Address", type="string", example="123 Main St"),
     *                      @OA\Property(property="door_no", type="string", example="Apt 4B"),
     *                      @OA\Property(property="business_id", type="integer", example=1),
     *                      @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                      @OA\Property(property="image", type="string", example="path/to/image.jpg"),
     *                      @OA\Property(property="job_title", type="string", example="Manager"),
     *                      @OA\Property(property="join_date", type="string", format="date", example="2023-01-01"),
     *                      @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *                      @OA\Property(property="created_at", type="string", format="date-time"),
     *                      @OA\Property(property="updated_at", type="string", format="date-time")
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      ),
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
    public function getAllBusinessUsers(Request $request)
    {
        // Validate query parameters
        // $request->validate([
        //     'role' => 'nullable|string|in:branch_manager,business_staff',
        // ]);

        // Get the authenticated user's business ID
        $user = auth()->user();
        $business_id = $user->business_id;

        $userBranchId = null;

        if ($user->hasRole('branch_manager') || $user->hasRole('business_owner')) {
            $userBranchId = $user->default_branch_id;
        }

        if (!$business_id) {
            throw new AuthorizationException('No business found for the authenticated user');
        }

        // Build query for users in the same business
        $userQuery = User::with([
            'roles:id,name',
            'branches',
            'branch',
            'branch.branch:id,name',
            'branch.branch.manager:id,first_Name,last_Name,email'
        ])->where('business_id', $business_id);

        // Filter by role - if no role specified, show only staff and manager roles
        if (request()->filled('role')) {
            $userQuery->whereHas('roles', fn($r) => $r->where('name', $request->role));
        } else {
            $userQuery->whereHas('roles', fn($r) => $r->whereIn('name', ['branch_manager', 'business_staff']));
        }

        // Filter users without branch assignment
        // If ignore_id is provided, include that user even if they have a branch
        if (request()->filled('without_branch')) {
            if (request()->filled('ignore_id')) {
                $ignoreId = request()->get('ignore_id');
                $userQuery->where(function ($query) use ($ignoreId) {
                    $query->whereDoesntHave('branch')
                        ->orWhere('id', $ignoreId);
                });
            } else {
                $userQuery->whereDoesntHave('branch');
            }
        }

        // Search functionality
        if (!empty($request->search_key)) {
            $userQuery = $userQuery->where(function ($query) use ($request) {
                $term = $request->search_key;
                $query->where("first_Name", "like", "%" . $term . "%")
                    ->orWhere("last_Name", "like", "%" . $term . "%");
            });
        }

        // Filter users by branch - if user is branch manager or business owner, show only users in their branch
        if ($userBranchId) {
            $userQuery->whereHas('branches', function ($query) use ($userBranchId) {
                $query->where('branches.id', $userBranchId);
            });
        }

        // Get paginated data
        $data = retrieve_data($userQuery);

        // Return response
        return response()->json([
            "success" => true,
            "message" => "Users retrieved successfully",
            "meta" => $data['meta'],
            "data" => UserResource::collection($data['data']),
        ], 200);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/users/{id}",
     *      operationId="updateBusinessUser",
     *      tags={"user_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Update an existing user",
     *      description="Update user information for users in the authenticated business",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="User ID",
     *          required=true,
     *          example=1,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="first_Name", type="string", example="John", description="User's first name"),
     *              @OA\Property(property="last_Name", type="string", example="Doe", description="User's last name"),
     *              @OA\Property(property="email", type="string", format="email", example="john.manager@yopmail.com", description="User's email address"),
     *              @OA\Property(property="phone", type="string", example="+1234567890", description="User's phone number"),
     *              @OA\Property(property="role", type="string", enum={"branch_manager", "business_staff"}, example="branch_manager", description="User role"),
     *              @OA\Property(property="post_code", type="string", example="12345", description="Postal code"),
     *              @OA\Property(property="Address", type="string", example="123 Main St", description="User's address"),
     *              @OA\Property(property="door_no", type="string", example="Apt 4B", description="Door number"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Date of birth"),
     *              @OA\Property(property="image", type="string", example="path/to/image.jpg", description="Profile image path"),
     *              @OA\Property(property="job_title", type="string", example="Manager", description="Job title"),
     *              @OA\Property(property="join_date", type="string", format="date", example="2023-01-01", description="Join date"),
     *              @OA\Property(property="skills", type="string", example="PHP, Laravel", description="User skills")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User updated successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="first_Name", type="string", example="John"),
     *                  @OA\Property(property="last_Name", type="string", example="Doe"),
     *                  @OA\Property(property="email", type="string", example="john.manager@yopmail.com"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="role", type="string", example="branch_manager"),
     *                  @OA\Property(property="post_code", type="string", example="12345"),
     *                  @OA\Property(property="Address", type="string", example="123 Main St"),
     *                  @OA\Property(property="door_no", type="string", example="Apt 4B"),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                  @OA\Property(property="image", type="string", example="path/to/image.jpg"),
     *                  @OA\Property(property="job_title", type="string", example="Manager"),
     *                  @OA\Property(property="join_date", type="string", format="date", example="2023-01-01"),
     *                  @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User not found or not in your business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found or access denied")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found")
     *          )
     *      ),
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
    public function updateBusinessUser(UserRequest $request, $id)
    {
        // Get the authenticated user's business ID
        $business_id = $request->user()->business->id;

        if (!$business_id) {
            throw new AccessDeniedHttpException('This user does not belong to your business');
        }

        // Find the user and ensure they belong to the same business
        $user = User::findOrFail($id);

        if ($user->business_id !== $business_id) {
            throw new AccessDeniedHttpException('This user does not belong to your business');
        }

        // Validate request data (excluding password for updates)
        $validatedData = $request->validated();

        try {
            // START TRANSACTION
            DB::beginTransaction();

            // Update the user
            $user->update($validatedData);

            if (!empty($validatedData['branch_id'])) {
                // Get the new branch
                $newBranch = Branch::where('id', $validatedData["branch_id"])
                    ->where('business_id', $business_id)
                    ->first();

                if (!$newBranch) {
                    throw new NotFoundHttpException('Branch Not Found');
                }

                // Get user's current branch (if any)
                $currentBranchMember = BranchMember::where('user_id', $id)
                    ->where('is_active', true)
                    ->first();

                // Handle branch manager logic ONLY if user has BRANCH_MANAGER role
                if (!empty($validatedData['role']) && $validatedData['role'] === User::USER_ROLE['BRANCH_MANAGER']) {
                    // Check if new branch already has a different manager
                    if ($newBranch->manager_id !== null && $newBranch->manager_id !== $user->id) {
                        throw new AccessDeniedHttpException('Branch Already Has a Manager');
                    }

                    // If user was in a different branch, remove them as manager from old branch
                    if ($currentBranchMember && $currentBranchMember->branch_id !== $validatedData['branch_id']) {
                        $oldBranch = Branch::find($currentBranchMember->branch_id);
                        if ($oldBranch && $oldBranch->manager_id === $user->id) {
                            $oldBranch->manager_id = null;
                            $oldBranch->save();
                        }
                    }

                    // Set user as manager of new branch
                    $newBranch->manager_id = $user->id;
                    $newBranch->save();
                } else {
                    // If user is BUSINESS_STAFF and moving branches, remove manager assignment from old branch
                    if ($currentBranchMember && $currentBranchMember->branch_id !== $validatedData['branch_id']) {
                        $oldBranch = Branch::find($currentBranchMember->branch_id);
                        if ($oldBranch && $oldBranch->manager_id === $user->id) {
                            $oldBranch->manager_id = null;
                            $oldBranch->save();
                        }
                    }
                }

                // Update or create branch member record (for both staff and manager)
                BranchMember::updateOrCreate(
                    ['user_id' => $id], // condition to check
                    ['branch_id' => $validatedData['branch_id']] // values to update or create
                );
            }

            // COMMIT TRANSACTION
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            // ROLLBACK TRANSACTION ON ERROR
            DB::rollBack();

            // Re-throw the exception to be handled by Laravel's exception handler
            throw $e;
        }
    }

    /**
     * @OA\Get(
     *      path="/v1.0/users/{id}",
     *      operationId="getUserById",
     *      tags={"user_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get a specific user by ID",
     *      description="Retrieve detailed information for a specific user within the authenticated business",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="User ID",
     *          required=true,
     *          example=1,
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="first_Name", type="string", example="John"),
     *                  @OA\Property(property="last_Name", type="string", example="Doe"),
     *                  @OA\Property(property="email", type="string", example="john.manager@yopmail.com"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="role", type="string", example="branch_manager"),
     *                  @OA\Property(property="post_code", type="string", example="12345"),
     *                  @OA\Property(property="Address", type="string", example="123 Main St"),
     *                  @OA\Property(property="door_no", type="string", example="Apt 4B"),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                  @OA\Property(property="image", type="string", example="path/to/image.jpg"),
     *                  @OA\Property(property="job_title", type="string", example="Manager"),
     *                  @OA\Property(property="join_date", type="string", format="date", example="2023-01-01"),
     *                  @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User not found or not in your business",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found or access denied")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found")
     *          )
     *      )
     * )
     */
    public function getUserById(Request $request, $id)
    {
        // Get the authenticated user's business ID
        $business_id = $request->user()->business->id;

        if (!$business_id) {
            throw new AccessDeniedHttpException('This user does not belong to your business');
        }

        // Find the user and ensure they belong to the same business
        $user = User::where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$user) {
            throw new AccessDeniedHttpException('This user does not belong to your business');
        }

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ], 200);
    }

    /**
     * @OA\Post(
     *      path="/v1.0/users",
     *      operationId="createUser",
     *      tags={"user_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Create a new user",
     *      description="Create a new user with the provided information",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="first_Name", type="string", example="John", description="User's first name"),
     *              @OA\Property(property="last_Name", type="string", example="Doe", description="User's last name"),
     *              @OA\Property(property="email", type="string", format="email", example="john.manager@yopmail.com", description="User's email address"),
     *              @OA\Property(property="password", type="string", example="password123", description="User's password (min 6 characters)"),
     *              @OA\Property(property="phone", type="string", example="+1234567890", description="User's phone number"),
     *              @OA\Property(property="role", type="string", enum={"branch_manager", "business_staff"}, example="branch_manager", description="User role"),
     *              @OA\Property(property="post_code", type="string", example="12345", description="Postal code"),
     *              @OA\Property(property="Address", type="string", example="123 Main St", description="User's address"),
     *              @OA\Property(property="door_no", type="string", example="Apt 4B", description="Door number"),
     *              @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01", description="Date of birth"),
     *              @OA\Property(property="image", type="string", example="path/to/image.jpg", description="Profile image path"),
     *              @OA\Property(property="job_title", type="string", example="Manager", description="Job title"),
     *              @OA\Property(property="join_date", type="string", format="date", example="2023-01-01", description="Join date"),
     *              @OA\Property(property="skills", type="string", example="PHP, Laravel", description="User skills")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="User created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User created successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="first_Name", type="string", example="John"),
     *                  @OA\Property(property="last_Name", type="string", example="Doe"),
     *                  @OA\Property(property="email", type="string", example="john.manager@yopmail.com"),
     *                  @OA\Property(property="phone", type="string", example="+1234567890"),
     *                  @OA\Property(property="role", type="string", example="branch_manager"),
     *                  @OA\Property(property="post_code", type="string", example="12345"),
     *                  @OA\Property(property="Address", type="string", example="123 Main St"),
     *                  @OA\Property(property="door_no", type="string", example="Apt 4B"),
     *                  @OA\Property(property="business_id", type="integer", example=1),
     *                  @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                  @OA\Property(property="image", type="string", example="path/to/image.jpg"),
     *                  @OA\Property(property="job_title", type="string", example="Manager"),
     *                  @OA\Property(property="join_date", type="string", format="date", example="2023-01-01"),
     *                  @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *                  @OA\Property(property="created_at", type="string", format="date-time"),
     *                  @OA\Property(property="updated_at", type="string", format="date-time")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Forbidden")
     *          )
     *      )
     * )
     */
    public function createUser(UserRequest $request)
    {
        // Validate request data
        $validatedData = $request->validated();
        $business_id = auth()->user()->business_id;
        $business_name = auth()->user()->business->Name;

        // ADD BUSINESS ID
        $validatedData['business_id'] = $business_id;

        // Generate random password: 8 digits + @ + uppercase letter + lowercase letter
        $plainPassword = sprintf(
            '%08d@%s%s',
            rand(10000000, 99999999),
            chr(rand(65, 90)),  // Random uppercase letter (A-Z)
            chr(rand(97, 122))  // Random lowercase letter (a-z)
        );
        // Hash the password
        $validatedData['password'] = Hash::make($plainPassword);

        // Generate remember token
        $validatedData['remember_token'] = Str::random(10);

        try {
            // START TRANSACTION
            DB::beginTransaction();

            // Create the user
            $user = User::create($validatedData);

            // ASSIGN ROLE
            if (!empty($validatedData['role'])) {
                $user->assignRole($validatedData['role']);
            }


            // No need to save - assignRole() already persists the role to database

            if (!empty($validatedData["branch_id"])) {
                $branch = Branch::where('id', $validatedData["branch_id"])
                    ->where('business_id', $business_id)
                    ->first();

                if (!$branch) {
                    throw new NotFoundHttpException('Branch Not Found');
                }

                // Check if branch already has a manager ONLY when assigning BRANCH_MANAGER role
                if ($validatedData['role'] === User::USER_ROLE['BRANCH_MANAGER']) {
                    if ($branch->manager_id !== null) {
                        throw new AccessDeniedHttpException('Branch Already Has a Manager');
                    }

                    // Set this user as branch manager
                    $branch->manager_id = $user->id;
                    $branch->save();
                }

                // CREATE BRANCH MEMBER (for both staff and manager)
                BranchMember::create([
                    'user_id' => $user->id,
                    'branch_id' => $validatedData["branch_id"],
                ]);
            }

            // COMMIT TRANSACTION
            DB::commit();

            // Send welcome email with credentials (outside transaction)
            try {
                Mail::to($user->email)->send(new ManagerWelcomeMail($user, $plainPassword, $business_name));
            } catch (\Exception $e) {
                // Log error but don't fail user creation
                \Log::error('Failed to send welcome email to: ' . $user->email . ' - Error: ' . $e->getMessage());
            }

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'User created successfully. Welcome email sent to ' . $user->email,
                'data' => $user
            ], 201);
        } catch (\Exception $e) {
            // ROLLBACK TRANSACTION ON ERROR
            DB::rollBack();

            throw $e;
        }
    }


    /**
     * @OA\Get(
     *      path="/v1.0/clients/users/{business_id}",
     *      operationId="getUserClient",
     *      tags={"user_management"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get user clients for authenticated user's business",
     *      description="Retrieve users (clients/staff) for the authenticated user's business with optional filtering and pagination",
     *      @OA\Parameter(
     *          name="business_id",
     *          in="path",
     *          required=true,
     *          description="Filter by user role",
     *          @OA\Schema(type="integer"),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="role",
     *          in="query",
     *          required=false,
     *          description="Filter by user role",
     *          @OA\Schema(type="string"),
     *          example="business_staff"
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          required=false,
     *          description="Page number",
     *          @OA\Schema(type="integer", minimum=1),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="Number of items per page",
     *          @OA\Schema(type="integer", minimum=1, maximum=100),
     *          example=15
     *      ),
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          required=false,
     *          description="Column to sort by",
     *          @OA\Schema(type="string"),
     *          example="created_at"
     *      ),
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          required=false,
     *          description="Sort order (asc or desc)",
     *          @OA\Schema(type="string", enum={"asc", "desc"}),
     *          example="desc"
     *      ),
     *      @OA\Parameter(
     *          name="branch_id",
     *          in="query",
     *          required=false,
     *          description="Filter by branch id",
     *          @OA\Schema(type="integer"),
     *          example=1
     *      ),
     *      @OA\Parameter(
     *          name="is_treat_manager_as_staff",
     *          in="query",
     *          required=false,
     *          description="Filter by is_treat_manager_as_staff",
     *          @OA\Schema(type="boolean"),
     *          example=false
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User client fetched successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="first_Name", type="string", example="John"),
     *                      @OA\Property(property="last_Name", type="string", example="Doe"),
     *                      @OA\Property(property="email", type="string", example="john@example.com"),
     *                      @OA\Property(property="business_id", type="integer", example=5)
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="total", type="integer", example=50)
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent()
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     *          @OA\JsonContent()
     *      )
     * )
     */
    public function getUserClient($business_id, Request $request)
    {


        // QUERY
        $query = User::with('branch')->filterStaff(businessId: $business_id);

        $users = retrieve_data($query);

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'User client fetched successfully',
            'meta' => $users['meta'],
            'data' => $users['data'],
        ], 200);
    }

    /**
     * @OA\Patch(
     *      path="/v1.0/users/toggle-status",
     *      operationId="toggleUserStatus",
     *      tags={"user_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Toggle user active status",
     *      description="Enable or disable a user account",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"user_id", "is_active"},
     *              @OA\Property(property="user_id", type="integer", example=1),
     *              @OA\Property(property="is_active", type="boolean", example=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="User status updated successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User status updated successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated"
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden"
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error"
     *      )
     * )
     */
    public function toggleUserStatus(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'is_active' => 'required|boolean'
        ]);

        $user = User::findOrFail($request->user_id);

        try {
            DB::beginTransaction();

            $user->is_active = $request->is_active;
            $user->save();

            if(!$request->is_active && $user->hasRole('manager')) {
                // Remove manager from branch
                $branch = $user->branch;
                $branch->manager_id = null;
                $branch->save();

                // Remove manager from branch members
                BranchMember::where('user_id', $user->id)
                ->where('branch_id', $branch->id)
                ->update(['is_active' => false]);
            }

            // COMMIT TRANSACTION
            DB::commit();

            // SEND RESPONSE
            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user
            ], Response::HTTP_OK);

        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
