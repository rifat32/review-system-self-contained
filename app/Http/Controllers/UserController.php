<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Http\Requests\UserRequest;

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

        $userQuery =  User::where([
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
        $userQuery =  User::with(["business", "roles"])->where([
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
            return response()->json([
                "success" => false,
                "message" => "You are not authorized to delete user"
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "User not found"
            ], 404);
        }

        /** SUPER ADMIN RULE */
        if ($authUser->hasRole('superadmin')) {

            // superadmin can delete ONLY business owners
            if (!$user->hasRole('business_owner')) {
                return response()->json([
                    "success" => false,
                    "message" => "Super admin can delete only business owners"
                ], 403);
            }
        }

        /** BUSINESS OWNER RULE */
        if ($authUser->hasRole('business_owner')) {

            // must be same business
            if ($user->business_id !== $authUser->business_id) {
                return response()->json([
                    "success" => false,
                    "message" => "You can delete users only from your business"
                ], 403);
            }

            // business owner cannot delete another business owner
            if ($user->hasRole('business_owner')) {
                return response()->json([
                    "success" => false,
                    "message" => "You cannot delete a business owner"
                ], 403);
            }
        }

        // if business exist then delete
        if ($user->business) {
            $user->business->delete();
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
        $business_id = $request->user()->business->id;

        if (!$business_id) {
            return response()->json([
                'success' => false,
                'message' => 'No business found for the authenticated user'
            ], 403);
        }

        // Build query for users in the same business
        $userQuery = User::with('roles')->where('business_id', $business_id);

        // Filter by role - if no role specified, show only staff and manager roles
        if (request()->filled('role')) {
            $userQuery->whereHas('roles', fn($r) => $r->where('name', $request->role));
        } else {
            $userQuery->whereHas('roles', fn($r) => $r->whereIn('name', ['branch_manager', 'business_staff']));
        }

        // Search functionality
        if (!empty($request->search_key)) {
            $userQuery = $userQuery->where(function ($query) use ($request) {
                $term = $request->search_key;
                $query->where("first_Name", "like", "%" . $term . "%")
                    ->orWhere("last_Name", "like", "%" . $term . "%");
            });
        }

        // Get paginated data
        $data = retrieve_data($userQuery);

        // Return response
        return response()->json([
            "success" => true,
            "message" => "Users retrieved successfully",
            "meta" => $data['meta'],
            "data" => $data['data'],
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
        $business_id = $request->user()->business()->id;

        if (!$business_id) {
            return response()->json([
                'success' => false,
                'message' => 'No business found for the authenticated user'
            ], 403);
        }

        // Find the user and ensure they belong to the same business
        $user = User::where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or access denied'
            ], 403);
        }

        // Validate request data (excluding password for updates)
        $validatedData = $request->validated();

        // Update the user
        $user->update($validatedData);

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user
        ], 200);
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
            return response()->json([
                'success' => false,
                'message' => 'No business found for the authenticated user'
            ], 403);
        }

        // Find the user and ensure they belong to the same business
        $user = User::where('id', $id)
            ->where('business_id', $business_id)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found or access denied'
            ], 403);
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
        $business_id = $request->user()->business->id;

        // ADD BUSINESS ID
        $validatedData['business_id'] = $business_id;

        // Hash the password
        $validatedData['password'] = Hash::make($validatedData['password']);

        // Generate remember token
        $validatedData['remember_token'] = Str::random(10);

        // Create the user
        $user = User::create($validatedData);

        // ASSIGN ROLE
        if ($validatedData['role'] === User::USER_ROLE['BUSINESS_STAFF']) {
            $user->assignRole(User::USER_ROLE['BUSINESS_STAFF']);
        } elseif ($validatedData['role'] === User::USER_ROLE['BRANCH_MANAGER']) {
            $user->assignRole(User::USER_ROLE['BRANCH_MANAGER']);
        }

        // Save the user
        $user->save();

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }
}
