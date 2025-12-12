<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
        $userQuery =  User::with("business")->where([
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
     *
     * @OA\Delete(
     *      path="/v1.0/users/{id}",
     *      operationId="deleteCustomerById",
     *      tags={"user_management.super_admin"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  *  @OA\Parameter(
     * name="id",
     * in="path",
     * description="id",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to delete  Customer by id",
     *      description="This method is to delete Customer  by id",
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
     *  * @OA\Response(
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
    public function deleteCustomerById($id, Request $request)
    {
        User::where([
            "id" => $id,
        ])
            ->delete();
        return response()->json([
            "ok" => true
        ], 200);
    }
}
