<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Utils\ErrorUtil;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;


class RolesController extends Controller
{
    use ErrorUtil;
    /**
     *
     * @OA\Post(
     *      path="/v1.0/roles",
     *      operationId="createRole",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store role",
     *      description="This method is to store role",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"name","permissions"},
     *             @OA\Property(property="name", type="string", format="string",example=""),
     *            @OA\Property(property="permissions", type="string", format="array",example={"user_create","user_update"}),
     * *            @OA\Property(property="is_default_for_business", type="boolean", format="boolean",example="1"),

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
    public function createRole(RoleRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('role_create')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }



            $request_data = $request->validated();

            $payload_data = [
                "name" => $request_data["name"],
                "guard_name" => "api",
            ];

            if (empty(auth()->user()->business_id)) {
                $payload_data["business_id"] = NULL;
                $payload_data["is_default"] = 1;
            } else {
                $payload_data["business_id"] = auth()->user()->business_id;
                $payload_data["is_default"] = 0;
                $payload_data["is_default_for_business"] = 0;
            }

            if (!empty($payload_data["business_id"])) {

                $custom_roles_count =   Role::where([
                    "business_id" => $payload_data["business_id"],
                    "is_default_for_business" => 0
                ])
                    ->count();

                if ($custom_roles_count >= 5) {
                    return response()->json([
                        "message" => "You can not create more than 5"
                    ], 403);
                }

                throw new Exception($custom_roles_count, 409);
            }


            $role = Role::create($payload_data);
            $role->syncPermissions($request_data["permissions"]);


            return response()->json([
                "status" => true,
                "message" => "Role created successfully",
                "data" =>  $role,
            ], 201);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Put(
     *      path="/v1.0/roles",
     *      operationId="updateRole",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update role",
     *      description="This method is to update role",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"id","permissions"},
     *             @OA\Property(property="id", type="number", format="number",example="1"),
     *            @OA\Property(property="permissions", type="string", format="array",example={"user_create","user_update"}),
     *  *            @OA\Property(property="description", type="string", format="string", example="description"),
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
    public function updateRole(RoleUpdateRequest $request)
    {
        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('role_update')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $request_data = $request->validated();

            $currentUser = auth()->user();

            $currentUserRole = $currentUser->roles()->orderBy('id', 'asc')->first();

            if (empty($currentUserRole)) {
                throw new Exception("There is no role of the current user");
            }

            $role = Role::where(["id" => $request_data["id"]])
                ->when((empty(auth()->user()->business_id)), function ($query) use ($request) {
                    return $query->where('business_id', NULL)->where('is_default', 1);
                })
                ->when(!empty(auth()->user()->business_id), function ($query) use ($request) {
                    // return $query->where('business_id', auth()->user()->business_id)->where('is_default', 0);
                    return $query->where('business_id', auth()->user()->business_id);
                })
                ->first();

            if (empty($role)) {
                throw new Exception("no role found");
            }

            if ($role->id <= $currentUserRole->id) {
                throw new Exception("You can not update this role");
            }

            $role->name = $request_data['name'];

            $role->description = !empty($request_data['description']) ? $request_data['description'] : "";

            $role->save();


            $role->syncPermissions($request_data["permissions"]);

            $role->load(["permissions"]);

            return response()->json([
                "status" => true,
                "message" => "Role updated successfully",
                "data" =>  $role,
            ], 201);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/roles",
     *      operationId="getRoles",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get roles",
     *      description="This method is to get roles",
     *
     *              @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="per_page",
     *         required=true,
     *  example="6"
     *      ),

     *      * *  @OA\Parameter(
     * name="start_date",
     * in="query",
     * description="start_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="end_date",
     * in="query",
     * description="end_date",
     * required=true,
     * example="2019-06-29"
     * ),
     * *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="search_key"
     * ),
     * *  @OA\Parameter(
     * name="order_by",
     * in="query",
     * description="order_by",
     * required=true,
     * example="ASC"
     * ),
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
    public function getRoles(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('role_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }




            $roles = Role::with('permissions:name,id', "users")

                ->where("is_default_for_business", 0)

                ->when((empty(auth()->user()->business_id)), function ($query) use ($request) {
                    return $query->where('business_id', NULL)->where('is_default', 1)
                        ->when(!($request->user()->hasRole('superadmin')), function ($query) use ($request) {
                            return $query->where('name', '!=', 'superadmin')
                                ->where("id", ">", $this->getMainRoleId());
                        });
                })
                ->when(!(empty(auth()->user()->business_id)), function ($query) use ($request) {
                    return $query->where('business_id', auth()->user()->business_id)
                        ->where("id", ">", $this->getMainRoleId());
                })

                ->when(!empty($request->search_key), function ($query) use ($request) {
                    $term = $request->search_key;
                    $query->where("name", "like", "%" . $term . "%");
                })
                ->when(!empty($request->start_date), function ($query) use ($request) {
                    return $query->where('created_at', ">=", $request->start_date);
                })
                ->when(!empty($request->end_date), function ($query) use ($request) {
                    return $query->where('created_at', "<=", ($request->end_date . ' 23:59:59'));
                })
                ->when(!empty($request->order_by) && in_array(strtoupper($request->order_by), ['ASC', 'DESC']), function ($query) use ($request) {
                    return $query->orderBy("id", $request->order_by);
                }, function ($query) {
                    return $query->orderBy("id", "DESC");
                })
                ->when(!empty($request->per_page), function ($query) use ($request) {
                    return $query->paginate($request->per_page);
                }, function ($query) {
                    return $query->get();
                });

            $result = retrieve_data($roles);

            return response()->json([
                "success" => true,
                "message" => "Roles retrieved successfully",
                "meta" => $result['meta'],
                "data" => $result['data'],
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/roles/{id}",
     *      operationId="getRoleById",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get role by id",
     *      description="This method is to get role by id",
     *
     *              @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="id",
     *         required=true,
     *  example="1"
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
    public function getRoleById($id, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            $role = Role::with('permissions:name,id')
                ->where(["id" => $id])
                ->select("name", "id")->get();
            return response()->json([
                "success" => true,
                "message" => "Role retrieved successfully",
                "data" => $role,
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }

    /**
     *
     * @OA\Delete(
     *      path="/v1.0/roles/{ids}",
     *      operationId="deleteRolesByIds",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to delete role by id",
     *      description="This method is to delete role by id",
     *
     *              @OA\Parameter(
     *         name="ids",
     *         in="path",
     *         description="ids",
     *         required=true,
     *  example="1,2,3"
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
    public function deleteRolesByIds($ids, Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");

            if (!$request->user()->hasPermissionTo('role_delete')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $idsArray = explode(',', $ids);

            $existingIds = Role::whereIn('id', $idsArray)
                ->where("is_system_default", "!=", 1)
                ->when(empty(auth()->user()->business_id), function ($query) use ($request) {
                    return $query->where('business_id', NULL)->where('is_default', 1);
                })
                ->when(!empty(auth()->user()->business_id), function ($query) use ($request) {
                    return $query->where('business_id', auth()->user()->business_id)->where('is_default', 0);
                })
                ->when(!($request->user()->hasRole('superadmin')), function ($query) {
                    return $query->where('name', '!=', 'superadmin');
                })

                ->select('id')
                ->get()
                ->pluck('id')
                ->toArray();

            $nonExistingIds = array_diff($idsArray, $existingIds);
            if (!empty($nonExistingIds)) {

                return response()->json([
                    "message" => "Some or all of the data they can not be deleted or not exists."
                ], 404);
            }


            $conflicts = [];

            // Check if any users have these roles
            $rolesWithUsers = User::whereHas('roles', function ($query) use ($existingIds) {
                $query->whereIn('id', $existingIds);
            })->exists();

            if ($rolesWithUsers) {
                $conflicts[] = "Users associated with these Roles";
            }

            // Add more checks for other related models or conditions as needed

            // Return combined error message if conflicts exist
            if (!empty($conflicts)) {
                $conflictList = implode(', ', $conflicts);
                return response()->json([
                    "message" => "Cannot delete this data as there are records associated with it in the following areas: $conflictList. Please update these records before attempting to delete.",
                ], 409);
            }

            // Proceed with the deletion process if no conflicts are found.



            Role::destroy($existingIds);


            return response()->json(["message" => "data deleted sussfully", "deleted_ids" => $existingIds], 200);



            return response()->json([
                "success" => true,
                "message" => "Roles deleted successfully",
                "deleted_ids" => $existingIds
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }


    /**
     *
     * @OA\Get(
     *      path="/v1.0/initial-role-permissions",
     *      operationId="getInitialRolePermissions",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get initial role permissions",
     *      description="This method is to get initial role permissions",
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

    public function getInitialRolePermissions(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('role_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }

            $role_permissions_main = config("setup-config.roles_permission");
            $unchangeable_roles = config("setup-config.unchangeable_roles");
            $unchangeable_permissions = config("setup-config.unchangeable_permissions");
            $permissions_titles = config("setup-config.permissions_titles");

            $new_role_permissions = [];

            foreach ($role_permissions_main as $roleAndPermissions) {
                if (in_array($roleAndPermissions["role"], $unchangeable_roles)) {
                    // Skip unchangeable roles
                    continue;
                }

                if (!empty(auth()->user()->business_id)) {
                    if (in_array($roleAndPermissions["role"], ["superadmin", "reseller"])) {
                        // Skip specific roles
                        continue;
                    }
                }

                if (!($request->user()->hasRole('superadmin')) && $roleAndPermissions["role"] == "superadmin") {
                    // Skip superadmin role
                    continue;
                }

                $data = [
                    "role"        => $roleAndPermissions["role"],
                    "permissions" => [],
                ];

                foreach ($roleAndPermissions["permissions"] as $permission) {
                    if (in_array($permission, $unchangeable_permissions)) {
                        // Skip unchangeable permissions
                        continue;
                    }

                    $data["permissions"][] = [
                        "name"  => $permission,
                        "title" => $permissions_titles[$permission] ?? null,
                    ];
                }

                array_push($new_role_permissions, $data);
            }

            return response()->json([
                "success" => true,
                "message" => "Initial role permissions retrieved successfully",
                "data" => $new_role_permissions,
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
    /**
     *
     * @OA\Get(
     *      path="/v1.0/initial-permissions",
     *      operationId="getInitialPermissions",
     *      tags={"user_management.role"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get initial permissions",
     *      description="This method is to get initial permissions",
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
    public function getInitialPermissions(Request $request)
    {

        try {
            $this->storeActivity($request, "DUMMY activity", "DUMMY description");
            if (!$request->user()->hasPermissionTo('role_view')) {
                return response()->json([
                    "message" => "You can not perform this action"
                ], 401);
            }
            $permissions_main = config("setup-config.beautified_permissions");

            $permissions_titles = config("setup-config.beautified_permissions_titles");

            $user = auth()->user();
            $current_permissions = $user->getAllPermissions();

            $new_permissions = [];

            foreach ($permissions_main as $permissions) {

                $data = [
                    "header"        => $permissions["header"],
                    "permissions" => [],
                ];

                if (!empty($permissions["module"])) {
                    if ($this->isModuleEnabled($permissions["module"], false)) {
                        foreach ($permissions["permissions"] as $permission) {

                            $hasPermission = $current_permissions->contains('name', $permission);


                            if ($hasPermission) {
                                $data["permissions"][] = [
                                    "name"  => $permission,
                                    "title" => $permissions_titles[$permission] ?? null,
                                ];
                            }
                        }
                    }
                } else {
                    foreach ($permissions["permissions"] as $permission) {

                        $hasPermission = $current_permissions->contains('name', $permission);

                        if ($hasPermission) {
                            $data["permissions"][] = [
                                "name"  => $permission,
                                "title" => $permissions_titles[$permission] ?? null,
                            ];
                        }
                    }
                }

                if (!empty($data["permissions"])) {
                    array_push($new_permissions, $data);
                }
            }

            return response()->json([
                "success" => true,
                "message" => "Initial permissions retrieved successfully",
                "data" => $new_permissions,
            ], 200);
        } catch (Exception $e) {

            return $this->sendError($e, 500, $request);
        }
    }
}
