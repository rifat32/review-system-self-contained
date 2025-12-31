<?php

namespace App\Http\Controllers;

use App\Helpers\AIProcessor;
use App\Http\Requests\StaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\ReviewNew;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use Carbon\Carbon;

class StaffController extends Controller
{
    /**
     * @OA\Post(
     *   path="/v1.0/staffs",
     *   operationId="createStaff",
     *   tags={"z.unused"},
     *   summary="Create a staff member",
     *   description="Registers a new staff user in the authenticated user's business.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       required={"first_Name","last_Name","email","password","role"},
     *       @OA\Property(property="first_Name", type="string", maxLength=255, example="John"),
     *       @OA\Property(property="last_Name",  type="string", maxLength=255, example="Doe"),
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@yopmail.com"),
     *       @OA\Property(property="password",   type="string", format="password", minLength=8, example="StrongP@ssw0rd"),
     *       @OA\Property(property="phone",      type="string", maxLength=255, example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", maxLength=255, example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", enum={"business_staff"}, example="business_staff"),
     *       @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *       @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=201,
     *     description="User registered successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="User registered successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=42),
     *         @OA\Property(property="first_Name", type="string", example="John"),
     *         @OA\Property(property="last_Name", type="string", example="Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@yopmail.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="role", type="string", example="business_staff"),
     *         @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *         @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01"),
     *         @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOi..."),
     *         @OA\Property(
     *           property="business",
     *           type="object",
     *           description="Authenticated user's business object",
     *           example={"id":1,"name":"Acme Corp"}
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(property="errors", type="object",
     *         example={
     *           "email": {"The email has already been taken."},
     *           "password": {"The password must be at least 8 characters."}
     *         }
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Forbidden"))
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Server error",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Server error"))
     *   )
     * )
     */


    public function createStaff(StaffRequest $request)
    {
        try {
            DB::beginTransaction();
            $request_payload = $request->validated();


            $request_payload['password'] = Hash::make($request_payload['password']);
            $request_payload['business_id'] =  auth()->user()->business->id;


            $user = User::create($request_payload);
            $user->assignRole($request_payload['role']);


            // Generate Passport token
            $token = $user->createToken('API Token')->accessToken;

            // Add token and business to user for response
            $user->token = $token;
            $user->business = auth()->user()->business;

            // Commit the transaction
            DB::commit();
            // Return success response
            return response()->json(
                [
                    'success' => true,
                    'message' => 'User registered successfully',
                    'data' => $user
                ],
                201
            );
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * @OA\Patch(
     *   path="/v1.0/staffs/{id}",
     *   operationId="updateStaff",
     *   tags={"z.unused"},
     *   summary="Update a staff member (partial)",
     *   description="Updates selected fields of a staff user in the authenticated user's business.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Staff user ID",
     *     @OA\Schema(type="integer"),
     *     example=12
     *   ),
     *
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="first_Name", type="string", maxLength=255, example="John"),
     *       @OA\Property(property="last_Name",  type="string", maxLength=255, example="Doe"),
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@yopmail.com"),
     *       @OA\Property(property="phone",      type="string", maxLength=50, example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", maxLength=255, example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", enum={"business_staff"}, example="business_staff"),
     *       @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *       @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff updated"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *       @OA\Property(property="id", type="integer", example=12),
     *       @OA\Property(property="first_Name", type="string", example="John"),
     *       @OA\Property(property="last_Name", type="string", example="Doe"),
     *       @OA\Property(property="email", type="string", format="email", example="john.doe@yopmail.com"),
     *       @OA\Property(property="phone", type="string", example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", example="business_staff"),
     *       @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *       @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Staff not found"))
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthenticated."))
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(@OA\Property(property="message", type="string", example="Forbidden"))
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="The given data was invalid."),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   )
     * )
     */


    // UPDATE (partial)
    public function updateStaff(UpdateStaffRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = User::where('business_id', auth()->user()->business->id)
                ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
                ->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff not found'
                ], 404);
            }

            $request_payload = $request->validated();

            $user->update($request_payload);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Staff updated',
                'data' => $user
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * @OA\Delete(
     *   path="/v1.0/staffs/{id}",
     *   operationId="deleteStaff",
     *   tags={"z.unused"},
     *   summary="Delete a staff member",
     *   description="Soft-deletes the staff user (if the model uses SoftDeletes) within the authenticated user's business.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Staff user ID",
     *     @OA\Schema(type="integer"),
     *     example=12
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff deleted")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Staff not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */


    // DELETE (soft delete if your model uses SoftDeletes)
    public function deleteStaff($id)
    {
        $user = User::where('business_id', auth()->user()->business->id)
            ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff deleted'
        ], 200);
    }
    /**
     * @OA\Get(
     *   path="/v1.0/staffs",
     *   operationId="getAllStaffs",
     *   tags={"z.unused"},
     *   summary="List staff",
     *   description="Returns staff users for the authenticated user's business. Supports name search, pagination, and sorting.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="search_key",
     *     in="query",
     *     required=false,
     *     description="Search by first_Name or last_Name (LIKE %search_key%).",
     *     @OA\Schema(type="string", example="john")
     *   ),
     *
     *   @OA\Parameter(
     *     name="order_by",
     *     in="query",
     *     required=false,
     *     description="Sort column (e.g. id, first_Name, last_Name, email, joining_date, created_at).",
     *     @OA\Schema(type="string", example="joining_date")
     *   ),
     *
     *   @OA\Parameter(
     *     name="sort_order",
     *     in="query",
     *     required=false,
     *     description="Sort direction",
     *     @OA\Schema(type="string", enum={"asc","desc"}, example="desc")
     *   ),
     *
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Number of staff per page (optional).",
     *     @OA\Schema(type="integer", minimum=1, example=15)
     *   ),
     *
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     description="Page number",
     *     @OA\Schema(type="integer", minimum=1, example=1)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=12),
     *           @OA\Property(property="first_Name", type="string", example="John"),
     *           @OA\Property(property="last_Name", type="string", example="Doe"),
     *           @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *           @OA\Property(property="phone", type="string", example="+8801765432109"),
     *           @OA\Property(property="business_id", type="integer", example=3),
     *           @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *           @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *         )
     *       ),
     *       @OA\Property(
     *         property="links",
     *         type="object",
     *         @OA\Property(property="first", type="string", example="https://api.example.com/v1.0/staffs?page=1"),
     *         @OA\Property(property="last", type="string", example="https://api.example.com/v1.0/staffs?page=10"),
     *         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *         @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/v1.0/staffs?page=2")
     *       ),
     *       @OA\Property(
     *         property="meta",
     *         type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="from", type="integer", example=1),
     *         @OA\Property(property="last_page", type="integer", example=10),
     *         @OA\Property(property="path", type="string", example="https://api.example.com/v1.0/staffs"),
     *         @OA\Property(property="per_page", type="integer", example=15),
     *         @OA\Property(property="to", type="integer", example=15),
     *         @OA\Property(property="total", type="integer", example=150)
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */


    // LIST with simple filters & pagination
    public function getAllStaffs(Request $request)
    {
        try {
            $businessId =  auth()->user()->business->id;

            $query = User::filterStaff($businessId);

            $staff = retrieve_data($query);

            // Check if pagination is requested
            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully',
                'meta' => $staff['meta'],
                'data' => $staff['data'],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *   path="/v1.0/staffs/{id}",
     *   operationId="getStaffById",
     *   tags={"z.unused"},
     *   summary="Get a single staff member by ID",
     *   description="Returns a staff user for the authenticated user's business.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Staff user ID",
     *     @OA\Schema(type="integer"),
     *     example=12
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="first_Name", type="string", example="John"),
     *         @OA\Property(property="last_Name", type="string", example="Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="role", type="string", example="business_staff"),
     *         @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *         @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Staff not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */


    // READ
    public function getStaffById($id)
    {

        $user = User::with('roles', function ($query) {
            $query->select('name', 'id');
        })->where('business_id', auth()->user()->business->id)
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
            ->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully',
            'data' => $user
        ], 200);
    }


    /**
     * @OA\Get(
     *   path="/v1.0/client/staffs",
     *   operationId="getClientAllStaffs",
     *   tags={"z.unused"},
     *   summary="List staff (paginated)",
     *   description="Returns staff users for the authenticated user's business. Supports simple name search and pagination.",
     *
     *   @OA\Parameter(
     *     name="search_key",
     *     in="query",
     *     required=false,
     *     description="Search by first_Name or last_name (LIKE %search_key%).",
     *     @OA\Schema(type="string"),
     *     example="john"
     *   ),
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Items per page (default 15).",
     *     @OA\Schema(type="integer", minimum=1, maximum=200),
     *     example=15
     *   ),
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     description="Page number (default 1).",
     *     @OA\Schema(type="integer", minimum=1),
     *     example=1
     *   ),
     *   @OA\Parameter(
     *     name="business_id",
     *     in="query",
     *     required=false,
     *     description="Business number (default 1).",
     *     @OA\Schema(type="integer", minimum=1),
     *     example=1
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=12),
     *           @OA\Property(property="first_Name", type="string", example="John"),
     *           @OA\Property(property="last_name", type="string", example="Doe"),
     *           @OA\Property(property="email", type="string", format="email", example="john.doe@yopmaill.com"),
     *           @OA\Property(property="phone", type="string", example="+8801765432109"),
     *           @OA\Property(property="business_id", type="integer", example=3),
     *           @OA\Property(property="skills", type="string", example="PHP, Laravel"),
     *           @OA\Property(property="joining_date", type="string", format="date", example="2023-01-01")
     *         )
     *       ),
     *       @OA\Property(
     *         property="links",
     *         type="object",
     *         @OA\Property(property="first", type="string", example="https://api.example.com/v1.0/staffs?page=1"),
     *         @OA\Property(property="last", type="string", example="https://api.example.com/v1.0/staffs?page=10"),
     *         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *         @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/v1.0/staffs?page=2")
     *       ),
     *       @OA\Property(
     *         property="meta",
     *         type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="from", type="integer", example=1),
     *         @OA\Property(property="last_page", type="integer", example=10),
     *         @OA\Property(property="path", type="string", example="https://api.example.com/v1.0/staffs"),
     *         @OA\Property(property="per_page", type="integer", example=15),
     *         @OA\Property(property="to", type="integer", example=15),
     *         @OA\Property(property="total", type="integer", example=150)
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */

    // LIST with simple filters & pagination
    public function getClientAllStaffs(Request $request)
    {
        try {
            if (!$request->query('business_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business id is required'
                ], 400);
            }

            $businessId = $request->input('business_id');
            $query = User::filterStaff($businessId)->orderByDesc('id');

            // Check if pagination is requested
            if ($request->filled('per_page')) {
                $perPage = (int) $request->per_page;

                // Validate per_page parameter
                if ($perPage < 1 || $perPage > 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid per_page value. Must be between 1 and 100'
                    ], 400);
                }

                $paginatedStaff = $query->paginate($perPage);

                return response()->json([
                    'success' => true,
                    'message' => 'Staff retrieved successfully',
                    'data' => $paginatedStaff->items(),
                    'meta' => [
                        'current_page' => $paginatedStaff->currentPage(),
                        'per_page' => $paginatedStaff->perPage(),
                        'total' => $paginatedStaff->total(),
                        'last_page' => $paginatedStaff->lastPage(),
                        'from' => $paginatedStaff->firstItem(),
                        'to' => $paginatedStaff->lastItem(),
                    ]
                ], 200);
            } else {
                // Return all staff without pagination
                $staff = $query->get();

                return response()->json([
                    'success' => true,
                    'message' => 'Staff retrieved successfully',
                    'data' => $staff
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *   path="/v1.0/staff-image",
     *   operationId="uploadStaffImage",
     *   tags={"staff_management"},
     *   security={{"bearerAuth": {}}},
     *   summary="Upload a staff image",
     *   description="Upload and store a staff image (returns the stored path)",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         type="object",
     *         required={"image"},
     *         @OA\Property(
     *           property="image",
     *           description="Image to upload",
     *           type="string",
     *           format="binary"
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Image uploaded successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Image uploaded successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="image", type="string", example="/image/uuid.jpg")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation failed"),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   ),
     *   @OA\Response(
     *     response=500,
     *     description="Upload failed",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Upload failed")
     *     )
     *   )
     * )
     */
    public function uploadStaffImage(Request $request)
    {
        try {
            $data = $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg|max:5024', // 5MB max
            ]);

            $location = "image";
            $directory = public_path($location);

            // Ensure the directory exists
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            // Generate a unique filename
            $extension = $data['image']->getClientOriginalExtension();
            $newFileName = Str::uuid() . '.' . $extension;

            $data['image']->move($directory, $newFileName);

            $image_path = "/" . $location . "/" . $newFileName;

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image' => $image_path
                ],
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *   path="/v1.0/staffs/{staffId}/performance-report",
     *   operationId="staffPerformanceReport",
     *   tags={"staff_management"},
     *   summary="Get staff performance report",
     *   description="Returns performance report for a specific staff member within the authenticated user's business.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="staffId",
     *     in="path",
     *     required=true,
     *     description="Staff user ID",
     *     @OA\Schema(type="integer"),
     *     example=12
     *   ),
     *
     *   @OA\Parameter(
     *     name="date_range",
     *     in="query",
     *     required=false,
     *     description="Date range filter for the report",
     *     @OA\Schema(type="string", enum={"last_30_days", "last_7_days", "this_month", "last_month"}),
     *     example="last_30_days"
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff performance report retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff performance report retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         description="Performance report data"
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Invalid date range",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Invalid date range provided")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Staff not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */
    public function staffPerformanceReport(Request $request, $staffId)
    {
        $filterable_fields = [
            "last_30_days",
            "last_7_days",
            "this_month",
            "last_month"
        ];

        $businessId = $request->user()->business->id;

        // Validate staff exists and belongs to the business
        $staff = User::where('business_id', $businessId)
            ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
            ->find($staffId);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        // Get date range from query parameter
        // Get period dates
        $period = $request->query('date_range', 'last_30_days');

        // Validate date range
        if (!in_array($period, $filterable_fields)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date range provided. Valid options: ' . implode(', ', $filterable_fields)
            ], 400);
        }

        $dateRange = getDateRangeByPeriod($period);
        // Get staff performance using existing staff suggestions
        $staffPerformance = AIProcessor::getStaffPerformanceSnapshot($businessId, $dateRange, $staffId);

        return response()->json([
            'success' => true,
            'message' => 'Staff performance report retrieved successfully',
            'data' => $staffPerformance
        ], 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/staffs/{staffId}/rating-trends",
     *   operationId="staffRatingTrends",
     *   tags={"staff_management"},
     *   summary="Get staff rating trends",
     *   description="Returns rating trends for a specific staff member within the specified date range, grouped by weeks.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="staffId",
     *     in="path",
     *     required=true,
     *     description="Staff user ID",
     *     @OA\Schema(type="integer")
     *   ),
     *
     *   @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     required=true,
     *     description="Start date for the trends (DD-MM-YYYY)",
     *     @OA\Schema(type="string", format="date")
     *   ),
     *
     *   @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     required=true,
     *     description="End date for the trends (DD-MM-YYYY)",
     *     @OA\Schema(type="string", format="date")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff rating trends retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff rating trends retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="name", type="string", example="Week 1"),
     *           @OA\Property(property="rating", type="number", format="float", example=4.2)
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Invalid date range",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Invalid date range provided")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Staff not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */
    public function staffRatingTrends(Request $request, $staffId)
    {
        $businessId = $request->user()->business->id;

        // Validate staff exists and belongs to the business
        $staff = User::where('business_id', $businessId)
            ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
            ->find($staffId);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        // Validate start_date and end_date
        $request->validate([
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::createFromFormat('d-m-Y', $request->query('start_date'))->startOfDay();
        $endDate = Carbon::createFromFormat('d-m-Y', $request->query('end_date'))->endOfDay();

        // Get weekly rating trends using calculated ratings
        $reviews = ReviewNew::withCalculatedRating()
            ->where('staff_id', $staffId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        // Group by week and calculate average rating
        $weeklyTrends = $reviews->groupBy(function ($review) {
            return Carbon::parse($review->created_at)->format('oW'); // Year and week number
        })->map(function ($weekReviews) {
            return [
                'week' => $weekReviews->first()->created_at->format('oW'),
                'avg_rating' => $weekReviews->avg('calculated_rating')
            ];
        })->sortBy('week');

        $ratingData = [];
        $weekNumber = 1;

        foreach ($weeklyTrends as $trend) {
            $ratingData[] = [
                'name' => 'Week ' . $weekNumber,
                'rating' => round((float) $trend['avg_rating'], 2)
            ];
            $weekNumber++;
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff rating trends retrieved successfully',
            'data' => $ratingData
        ], 200);
    }

    /**
     * @OA\Get(
     *   path="/v1.0/staffs/{staffId}/reviews",
     *   operationId="getStaffReviews",
     *   tags={"staff_management"},
     *   summary="Get filtered staff reviews",
     *   description="Returns reviews for a specific staff member filtered by sentiment (positive, negative, neutral) with pagination.",
     *   security={{"bearerAuth":{}}},
     *
     *
     *   @OA\Parameter(
     *     name="filter",
     *     in="query",
     *     required=false,
     *     description="Filter reviews by sentiment",
     *     @OA\Schema(type="string", enum={"positive", "negative", "neutral"}),
     *     example="positive"
     *   ),
     *
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Number of reviews per page",
     *     @OA\Schema(type="integer", minimum=1, maximum=100)
     *   ),
     *
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     description="Page number",
     *     @OA\Schema(type="integer", minimum=1)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Staff reviews retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Staff reviews retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="array",
     *         @OA\Items(
     *           type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="description", type="string", example="Great service!"),
     *           @OA\Property(property="calculated_rating", type="number", format="float", example=4.5),
     *           @OA\Property(
     *             property="staff",
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=12),
     *             @OA\Property(property="first_Name", type="string", example="John"),
     *             @OA\Property(property="last_Name", type="string", example="Doe")
     *           )
     *         )
     *       ),
     *       @OA\Property(
     *         property="links",
     *         type="object",
     *         @OA\Property(property="first", type="string", example="https://api.example.com/v1.0/staffs/12/reviews?page=1"),
     *         @OA\Property(property="last", type="string", example="https://api.example.com/v1.0/staffs/12/reviews?page=10"),
     *         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *         @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/v1.0/staffs/12/reviews?page=2")
     *       ),
     *       @OA\Property(
     *         property="meta",
     *         type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="from", type="integer", example=1),
     *         @OA\Property(property="last_page", type="integer", example=10),
     *         @OA\Property(property="path", type="string", example="https://api.example.com/v1.0/staffs/12/reviews"),
     *         @OA\Property(property="per_page", type="integer", example=15),
     *         @OA\Property(property="to", type="integer", example=15),
     *         @OA\Property(property="total", type="integer", example=150)
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=400,
     *     description="Invalid filter",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Invalid filter provided. Valid options: positive, negative, neutral")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=404,
     *     description="Staff not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="success", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Staff not found")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="Forbidden")
     *     )
     *   )
     * )
     */
    public function getStaffReviews(Request $request, $staffId)
    {
        $businessId = $request->user()->business->id;

        // Validate staff exists and belongs to the business
        $staff = User::where('business_id', $businessId)
            ->whereHas('roles', fn($r) => $r->where('name', 'business_staff'))
            ->find($staffId);

        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff not found'
            ], 404);
        }

        $validFilters = ['positive', 'negative', 'neutral'];

        $filter = $request->query('filter');

        if ($filter && !in_array($filter, $validFilters)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid filter provided. Valid options: ' . implode(', ', $validFilters)
            ], 400);
        }

        $query = ReviewNew::with('staff')
            ->where('staff_id', $staffId)
            ->where('business_id', $businessId)
            ->withCalculatedRating();

        // Apply filter based on calculated_rating
        if ($filter === 'positive') {
            $query->where('calculated_rating', '>=', 4);
        } elseif ($filter === 'negative') {
            $query->where('calculated_rating', '<=', 2);
        } elseif ($filter === 'neutral') {
            $query->whereBetween('calculated_rating', [2.1, 3.9]);
        } else {
            $query->where('calculated_rating', '>=', 4);
        }

        // Get the reviews using retrieve_data for consistent pagination
        $reviews = retrieve_data($query);

        return response()->json([
            'success' => true,
            'message' => 'Staff reviews retrieved successfully',
            'data' => $reviews['data'],
            'meta' => $reviews['meta']
        ], 200);
    }
}
