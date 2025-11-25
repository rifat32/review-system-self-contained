<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffController extends Controller
{
    /**
     * @OA\Post(
     *   path="/v1.0/staffs",
     *   operationId="createStaff",
     *   tags={"staff_management"},
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
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@example.com"),
     *       @OA\Property(property="password",   type="string", format="password", minLength=8, example="StrongP@ssw0rd"),
     *       @OA\Property(property="phone",      type="string", maxLength=255, example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", maxLength=255, example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", enum={"staff"}, example="staff")
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
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="role", type="string", example="staff"),
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
            $request_payload['business_id'] =  auth()->user()->business()->value('id');


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
     *   tags={"staff_management"},
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
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@example.com"),
     *       @OA\Property(property="phone",      type="string", maxLength=50, example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", maxLength=255, example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", enum={"staff"}, example="staff")
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
     *       @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *       @OA\Property(property="phone", type="string", example="+8801765432109"),
     *       @OA\Property(property="job_title", type="string", example="Manager"),
     *       @OA\Property(property="image", type="string", example="/image/uuid.jpg"),
     *       @OA\Property(property="role", type="string", example="staff")
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
            $user = User::where('business_id', auth()->user()->business()->value('id'))
                ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
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
     *   tags={"staff_management"},
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
        $user = User::where('business_id', auth()->user()->business()->value('id'))
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
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
     *   tags={"staff_management"},
     *   summary="List staff",
     *   description="Returns staff users for the authenticated user's business. Supports simple name search and pagination.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="search_key",
     *     in="query",
     *     required=false,
     *     description="Search by first_Name or last_name (LIKE %search_key%).",
     *     @OA\Schema(type="string"),
     *     example=""
     *   ),
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Number of staff per page (optional, if not provided returns all staff)",
     *     @OA\Schema(type="integer"),
     *     example=""
     *   ),
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     required=false,
     *     description="Page number",
     *     @OA\Schema(type="integer"),
     *     example=""
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
     *           @OA\Property(property="business_id", type="integer", example=3)
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
    public function getAllStaffs(Request $request)
    {
        try {
            $businessId = auth()->user()->business()->value('id');
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
     * @OA\Get(
     *   path="/v1.0/staffs/{id}",
     *   operationId="getStaffById",
     *   tags={"staff_management"},
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
     *         @OA\Property(property="role", type="string", example="staff")
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
        })->where('business_id', auth()->user()->business()->value('id'))
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
     *   tags={"staff_management"},
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
     *           @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *           @OA\Property(property="phone", type="string", example="+8801765432109"),
     *           @OA\Property(property="business_id", type="integer", example=3)
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
}
