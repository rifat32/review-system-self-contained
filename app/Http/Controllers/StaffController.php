<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStaffRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
     *       required={"first_name","last_name","email","password","phone","date_of_birth","role"},
     *       @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
     *       @OA\Property(property="last_name",  type="string", maxLength=255, example="Doe"),
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@example.com"),
     *       @OA\Property(property="password",   type="string", format="password", minLength=8, example="StrongP@ssw0rd"),
     *       @OA\Property(property="phone",      type="string", maxLength=255, example="+8801765432109"),
     *       @OA\Property(property="date_of_birth", type="string", example="1995-06-15"),
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
     *         @OA\Property(property="first_name", type="string", example="John"),
     *         @OA\Property(property="last_name", type="string", example="Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="date_of_birth", type="string", example="1995-06-15"),
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


    public function createStaff(Request $request)
    {
        try {
            DB::beginTransaction();
            $request->validate([
                'first_name'     => 'required|string|max:255',
                'last_name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'phone'     => 'required|string|max:255',
                'date_of_birth'     => 'required|string|max:255',
                'role'     => 'required|string|in:staff',
            ]);

            $user = User::create([
                'first_name'     => $request->first_name,
                'last_name'     => $request->last_name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'phone'     => $request->phone,
                'date_of_birth'     => $request->date_of_birth,
                'business_id' => auth()->user()->business_id
            ]);

            // $user->assignRole("$request->role" . "#" . $request->business_id);
            $user->assignRole($request->role);


            // Generate Passport token
            $token = $user->createToken('API Token')->accessToken;


            // Commit the transaction
            DB::commit();
            // Return success response
            return response()->json(
                [
                    'success' => true,
                    'message' => 'User registered successfully',
                    'data' => [
                        'id' => $user->id,
                        'first_name'    => $user->first_name,
                        'last_name'    => $user->last_name,
                        'email'   => $user->email,
                        'phone'    => $user->phone,
                        'date_of_birth'    => $user->date_of_birth,
                        'role'    => $user->roles->pluck('name')->first(),
                        'token'   => $token,
                        'business' => auth()->user()->business
                    ]
                ],
                201
            );
        } catch (\Throwable $th) {
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
     *     required=true,
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="first_name", type="string", maxLength=255, example="John"),
     *       @OA\Property(property="last_name",  type="string", maxLength=255, example="Doe"),
     *       @OA\Property(property="email",      type="string", format="email", example="john.doe@example.com"),
     *       @OA\Property(property="phone",      type="string", maxLength=50, example="+8801765432109"),
     *       @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-15"),
     *       @OA\Property(property="password",   type="string", format="password", minLength=8, example="NewPassw0rd!")
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
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="first_name", type="string", example="John"),
     *         @OA\Property(property="last_name", type="string", example="Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-15"),
     *         @OA\Property(property="role", type="string", example="staff")
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
            $user = User::where('business_id', auth()->user()->business_id)
                ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
                ->find($id);

            if (!$user) {
                return response()->json(['message' => 'Staff not found'], 404);
            }

            $request_payload = $request->validated();

            $user->update($request_payload);


            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Staff updated',
                'data' => [
                    'id'           => $user->id,
                    'first_name'   => $user->first_name,
                    'last_name'    => $user->last_name,
                    'email'        => $user->email,
                    'phone'        => $user->phone,
                    'date_of_birth' => $user->date_of_birth,
                    'role'         => $user->roles->pluck('name')->first(),
                ]
            ], 200);
        } catch (\Throwable $e) {
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
        $user = User::where('business_id', auth()->user()->business_id)
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
            ->find($id);

        if (!$user) {
            return response()->json(['message' => 'Staff not found'], 404);
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
     *   summary="List staff (paginated)",
     *   description="Returns staff users for the authenticated user's business. Supports simple name search and pagination.",
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="search_key",
     *     in="query",
     *     required=false,
     *     description="Search by first_name or last_name (LIKE %search_key%).",
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
     *           @OA\Property(property="first_name", type="string", example="John"),
     *           @OA\Property(property="last_name", type="string", example="Doe"),
     *           @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *           @OA\Property(property="phone", type="string", example="+8801765432109"),
     *           @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-15"),
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

        $q = User::query()
            ->where('business_id', auth()->user()->business_id)
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
            ->when($request->filled('search_key'), function ($qq) use ($request) {
                $s = $request->input('search_key');
                $qq->where(function ($w) use ($s) {
                    $w->where('first_name', 'like', "%$s%")
                        ->orWhere('last_name', 'like', "%$s%");
                });
            })
            ->orderByDesc('id');

        // $perPage = (int)($request->input('per_page', 15));
        $data = $q->get()->toArray();

        return response()->json($data, 200);
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
     *         @OA\Property(property="first_name", type="string", example="John"),
     *         @OA\Property(property="last_name", type="string", example="Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *         @OA\Property(property="phone", type="string", example="+8801765432109"),
     *         @OA\Property(property="date_of_birth", type="string", format="date", example="1995-06-15"),
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
        })->where('business_id', auth()->user()->business_id)
            ->whereHas('roles', fn($r) => $r->where('name', 'staff'))
            ->find($id);

        if (!$user) {
            return response()->json(['message' => 'Staff not found'], 404);
        }

        return response()->json([
            'data' => $user
        ], 200);
    }
}
