<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
      /**
     *
     * @OA\Get(
     *      path="/superadmin/customer-list/{perPage}",
     *      operationId="getCustomerReportSuperadmin",
     *      tags={"super_admin_report.customer"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  *  @OA\Parameter(
            * name="perPage",
            * in="path",
            * description="perPage",
            * required=true,
            * example="1"
            * ),
             *  *  @OA\Parameter(
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
     *          description="Unprocesseble Content",
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
    public function getCustomerReportSuperadmin ($perPage,Request $request) {

        $userQuery =  User::where([
            "type" => "customer"
        ]);
        if(!empty($request->search_term)) {
            $userQuery = $userQuery->where(function($query) use ($request){
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
        $data = $userQuery
                  ->latest()
                  ->paginate($perPage)
                  ->withQueryString();
        return response()->json($data,200);
    }

      /**
     *
     * @OA\Get(
     *      path="/superadmin/owner-list/{perPage}",
     *      operationId="getOwnerReport",
     *      tags={"super_admin_report.customer"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
      *  *  @OA\Parameter(
            * name="perPage",
            * in="path",
            * description="perPage",
            * required=true,
            * example="1"
            * ),
             *  *  @OA\Parameter(
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
     *          description="Unprocesseble Content",
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

    public function getOwnerReport ($perPage,Request $request) {
        $userQuery =  User::with("business")->where([
            "type" => "business_Owner"
        ]);
        if(!empty($request->search_term)) {
            $userQuery = $userQuery->where(function($query) use ($request){
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
        $data = $userQuery
                  ->latest()
                  ->paginate($perPage)
                  ->withQueryString();
        return response()->json($data,200);

    }

     /**
     *
     * @OA\Delete(
     *      path="/superadmin/user-delete/{id}",
     *      operationId="deleteCustomerById",
     *      tags={"super_admin_report.customer"},
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
     *          description="Unprocesseble Content",
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
    public function deleteCustomerById ($id,Request $request) {
        User::where([
                      "id" => $id,
                  ])
                ->delete();
        return response()->json([
            "ok" => true
    ],200);
    }

}
