<?php

namespace App\Http\Controllers;

use App\Exports\BusinessExport;
use App\Models\DailyView;

use App\Models\Notification;

use App\Models\Question;
use App\Models\Business;
use App\Models\BusinessTable;
use App\Models\Review;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Tag;
use App\Models\User;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class BusinessController extends Controller
{

    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/business",
     *      operationId="storeRestaurent",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store business",
     *      description="This method is to store business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode","enable_question"},
     *             @OA\Property(property="Name", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="Address", type="string", format="string",example="How was this?"),
     *
     *
     *            @OA\Property(property="PostCode", type="string", format="string",example="How was this?"),

     * *  @OA\Property(property="enable_question", type="boolean", format="boolean",example="1"),
  
   
     *
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

    public function storeRestaurent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Name' => 'required|unique:businesses,Name',
            'Address' => 'required|string',
            'PostCode' => 'required',
            'enable_question' => 'required',

        

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 422]);
        }
        $validatedData = $validator->validated();
        $validatedData["OwnerID"] = $request->user()->id;
        $validatedData["Status"] = "Inactive";

        $validatedData["Key_ID"] = Str::random(10);
        $validatedData["expiry_date"] = Date('y:m:d', strtotime('+15 days'));



     


        $business =  Business::create($validatedData);


        return response($business, 200);
    }
    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/business/by-owner-id",
     *      operationId="storeRestaurentByOwnerId",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store business by owner id",
     *      description="This method is to store business by owner id",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode","enable_question"},
     *             @OA\Property(property="Name", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="Address", type="string", format="string",example="How was this?"),

     *
     *            @OA\Property(property="PostCode", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="OwnerID", type="string", format="string",example="How was this?"),
     *

     * *  @OA\Property(property="enable_question", type="boolean", format="boolean",example="1"),
   
  
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

    public function storeRestaurentByOwnerId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'Name' => 'required|unique:businesses,Name',
            'Address' => 'required|string',
            'PostCode' => 'required',
            'OwnerID' => 'required',
            'enable_question' => 'required',

     

        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors(), 422]);
        }
        $validatedData = $validator->validated();

        $validatedData["Status"] = "Inactive";

        $validatedData["Key_ID"] = Str::random(10);
        $validatedData["expiry_date"] = Date('y:m:d', strtotime('+15 days'));

       




        $business =  Business::create($validatedData);


        return response($business, 200);
    }
    /**
     *
     * @OA\Delete(
     *      path="/business/delete/{id}",
     *      operationId="deleteBusinessByRestaurentId",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="id",
     * in="path",
     * description="id",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to delete business by id",
     *      description="This method is to delete business by id",
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

    public function deleteBusinessByRestaurentId($id, Request $request)
    {

        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission", 401]);
        }



        Business::where(["id" => $id])->delete();



        DailyView::where(["business_id" => $id])->delete();



        return response(["ok" => true], 200);
    }

    /**
     *
     * @OA\Delete(
     *      path="/business/delete/force-delete/{email}",
     *      operationId="deleteBusinessByRestaurentIdForceDelete",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\Parameter(
     * name="email",
     * in="path",
     * description="email",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to delete business by id",
     *      description="This method is to delete business by id",
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

    public function deleteBusinessByRestaurentIdForceDelete($email, Request $request)
    {

        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission", 401]);
        }



        $business = Business::where(["EmailAddress" => $email])->first();
        $id = $business->id;

        if ($business && $business->created_at >= Carbon::now()->subMinutes(5)) {
            $business->forceDelete();
            User::where([
                "id" => $business->OwnerID
            ])
                ->delete();
            DailyView::where(["business_id" => $id])->delete();
 
            Notification::where(["business_id" => $id])->delete();
           
            Question::where(["business_id" => $id])->delete();
           
            Review::where(["business_id" => $id])->delete();
            ReviewNew::where(["business_id" => $id])->delete();
            ReviewValue::where(["business_id" => $id])->delete();
            Tag::where(["business_id" => $id])->delete();
   
        }

        return response(["ok" => true], 200);
    }


    // ##################################################
    // This method is to upload business image
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/business/uploadimage/{restaurentId}",
     *      operationId="uploadRestaurentImage",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to upload business image",
     *      description="This method is to upload business image",
     *        @OA\Parameter(
     *         name="restaurentId",
     *         in="path",
     *         description="restaurent Id",
     *         required=false,
     *      ),
     *            @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="method",
     *         required=false,
     * example="PATCH"
     *      ),
     *
     *  @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="file to upload",
     *                     property="logo",
     *                     type="file",
     *                ),
     *                 required={"logo"}
     *             )
     *         )
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
     *          description="Unprocesseble Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     * @OA\JsonContent()
     * ),
     *  * @OA\Response(
     *      response=400,
     *      description="Bad Request",
     * @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *     @OA\JsonContent()
     *   )

     *      )
     *     )
     */
    public function uploadRestaurentImage($restaurentId, Request $request)
    {

        $request->validate([

            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

        ]);
        $checkBusiness =    Business::where(["id" => $restaurentId])->first();
        if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "This is not your business", 401]);
        }


        $imageName = time() . '.' . $request->logo->extension();



        $request->logo->move(public_path('img/business'), $imageName);

        $imageName = "img/business/" . $imageName;

        $data["restaurent"] =    tap(Business::where(["id" => $restaurentId]))->update([
            "Logo" => $imageName
        ])
            // ->with("somthing")

            ->first();


        if (!$data["restaurent"]) {
            return response()->json(["message" => "No User Found"], 404);
        }

        $data["message"] = "business image updates successfully";
        return response()->json($data, 200);
    }
    // ##################################################
    // This method is to update business details
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/business/UpdateResturantDetails/{restaurentId}",
     *      operationId="UpdateResturantDetails",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update business",
     *      description="This method is to update business",
     *
     *  *            @OA\Parameter(
     *         name="restaurentId",
     *         in="path",
     *         description="method",
     *         required=true,
     * example="1"
     *      ),
     *
     *            @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="method",
     *         required=true,
     * example="PATCH"
     *      ),
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={ "Name","Layout","Address","PostCode", "enable_question" },
     *
     *                 @OA\Property(property="GoogleMapApi", type="string", format="string",example="business name"),
     *
     *                 @OA\Property(property="Name", type="string", format="string",example="business name"),
     *
     *             

     *
     *
     *
     *                @OA\Property(property="EmailAddress", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="homeText", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="AdditionalInformation", type="string", format="string",example="How was this?"),
     *
     *
     *             @OA\Property(property="PostCode", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="Webpage", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="PhoneNumber", type="string", format="string",example="How was this?"),
     *
     *
     *             @OA\Property(property="About", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="Layout", type="string", format="string",example="How was this?"),
     *            @OA\Property(property="Address", type="string", format="string",example="How was this?"),

     *

     * *  @OA\Property(property="enable_question", type="boolean", format="boolean",example="1"),
    
     
     *    *      *  *  *  *   *               @OA\Property(property="show_image", type="string", format="string",example="0"),
     *


   
    
     *     *  *  *   *               @OA\Property(property="Key_ID", type="string", format="string",example="0"),
     *  *     *  *  *   *               @OA\Property(property="review_type", type="string", format="string",example="0"),
     *     @OA\Property(property="google_map_iframe", type="string", format="string",example="test"),
     *
     *  *  *        @OA\Property(property="Is_guest_user", type="boolean", format="boolean",example="false"),
     *  *        @OA\Property(property="is_review_silder", type="boolean", format="boolean",example="false"),
   
    
     *
     *     *   *   *    *   *  *        @OA\Property(property="header_image", type="string", format="string",example="/header_image/default.png"),
     *
   
     *
     *
    
     *
     *  *    *   *  *        @OA\Property(property="primary_color", type="string", format="string",example="red"),
     *  *  *    *   *  *        @OA\Property(property="secondary_color", type="string", format="string",example="red"),
     * *  *  *  *    *   *  *        @OA\Property(property="client_primary_color", type="string", format="string",example="red"),
     *
     *  *  *  *    *   *  *        @OA\Property(property="client_secondary_color", type="string", format="string",example="red"),
     *
     *  *  *  *    *   *  *        @OA\Property(property="client_tertiary_color", type="string", format="string",example="red"),
     *         @OA\Property(property="user_review_report", type="boolean", format="boolean",example="1"),
     *  *       @OA\Property(property="guest_user_review_report", type="boolean", format="boolean",example="1"),
     *
     
     *    *   * *  *       @OA\Property(property="is_report_email_enabled", type="boolean", format="boolean",example="1"),
     *
     *
     *     *  *       @OA\Property(property="pin", type="string", format="string",example="1"),
     * 
     *   *        @OA\Property(property="is_registered_user_overall_review", type="boolean", format="boolean",example="1"),
     *        @OA\Property(property="is_registered_user_survey", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_registered_user_survey_required", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_registered_user_show_stuffs", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_registered_user_show_stuff_image", type="boolean", format="boolean",example="1"),
     *      *         @OA\Property(property="is_registered_user_show_stuff_name", type="boolean", format="boolean",example="1"),
     * 
     * 
     *      @OA\Property(property="enable_ip_check", type="boolean", format="boolean",example="1"),
     *      @OA\Property(property="enable_location_check", type="boolean", format="boolean",example="1"), 
     *      @OA\Property(property="latitude", type="boolean", format="boolean",example="1"),
     * 
      *      @OA\Property(property="longitude", type="boolean", format="boolean",example="1"),
     * 
     *      @OA\Property(property="review_distance_limit", type="boolean", format="boolean",example="1"),
     * 
     * 
     *      @OA\Property(property="threshold_rating", type="boolean", format="boolean",example="1"), 
     * 
     * 
     * 
     *        @OA\Property(property="is_guest_user_overall_review", type="boolean", format="boolean",example="1"),
     *        @OA\Property(property="is_guest_user_survey", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_guest_user_survey_required", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_guest_user_show_stuffs", type="boolean", format="boolean",example="1"),
     *         @OA\Property(property="is_guest_user_show_stuff_image", type="boolean", format="boolean",example="1"),
     *      *         @OA\Property(property="is_guest_user_show_stuff_name", type="boolean", format="boolean",example="1"),

     *
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

    public function UpdateResturantDetails($restaurentId, Request $request)
    {


        $checkBusiness =    Business::where(["id" => $restaurentId])->first();

        if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "This is not your business", 401]);
        }

        $data["business"] =    tap(Business::where(["id" => $restaurentId]))->update($request->only(
            "show_image",

            "review_type",
            "google_map_iframe",
            "Key_ID",
            "Status",
            "About",
            "Name",
            "Layout",
            "Address",
            "PostCode",
            "enable_question",
            "Webpage",
            "PhoneNumber",
            "EmailAddress",
            "homeText",
            "AdditionalInformation",

            "GoogleMapApi",

            'Is_guest_user',
            'is_review_silder',
            "review_only",

            "header_image",

         

            "primary_color",
            "secondary_color",

            "client_primary_color",
            "client_secondary_color",
            "client_tertiary_color",
            "user_review_report",
            "guest_user_review_report",


            "pin",

            "is_report_email_enabled",


          
            "time_zone",

           'is_guest_user_overall_review',
                'is_guest_user_survey',
                'is_guest_user_survey_required',
                'is_guest_user_show_stuffs',
                'is_guest_user_show_stuff_image',
                'is_guest_user_show_stuff_name',

                // Registered user fields
                'is_registered_user_overall_review',
                'is_registered_user_survey',
                'is_registered_user_survey_required',
                'is_registered_user_show_stuffs',
                'is_registered_user_show_stuff_image',
                'is_registered_user_show_stuff_name',


        'enable_ip_check',
        'enable_location_check',
        'latitude',
        'longitude',
        'review_distance_limit',
        'threshold_rating',

        ))
            // ->with("somthing")

            ->first();


        if (!$data["business"]) {
            return response()->json(["message" => "No Business Found"], 404);
        }


        $data["message"] = "Business updates successfully";
        return response()->json($data, 200);
    }
    // ##################################################
    // This method is to update business details by admin
    // ##################################################
    public function UpdateResturantDetailsByAdmin($restaurentId, Request $request)
    {
        $checkBusiness =    Business::where(["id" => $restaurentId])->first();
        if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "This is not your business", 401]);
        }

        $data["business"] =    tap(Business::where(["id" => $restaurentId]))->update($request->only(
            "show_image",

            "review_type",
            "google_map_iframe",
            "Key_ID",
            "Status",
            "About",
            "Name",
            "Layout",
            "Address",
            "PostCode",
            "enable_question",
            "Webpage",
            "PhoneNumber",
            "EmailAddress",
            "homeText",
            "AdditionalInformation",

            "GoogleMapApi",


     
            'Is_guest_user',
            'is_review_silder',
            "review_only",

            "header_image",

           

            "primary_color",
            "secondary_color",

            "client_primary_color",
            "client_secondary_color",
            "client_tertiary_color",
            "user_review_report",
            "guest_user_review_report",
            "time_zone"
        ))
            // ->with("somthing")

            ->first();


        if (!$data["business"]) {
            return response()->json(["message" => "No Business Found"], 404);
        }


        $data["message"] = "Business updates successfully";
        return response()->json($data, 200);
    }
    // ##################################################
    // This method is to get business by id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/business/{businessId}",
     *      operationId="getbusinessById",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business by id",
     *      description="This method is to get business by id",
     *
     *  *            @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="method",
     *         required=true,
     * example="1"
     *      ),
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
    public function getbusinessById($businessId)
    {
        $data["business"] =   Business::with([
            "owner"
        ])
        
        ->where(["id" => $businessId])->first();
        $data["ok"] = true;

        if (!$data["business"]) {
            return response(["message" => "No Business Found"], 404);
        }
        return response($data, 200);
    }
    // ##################################################
    // This method is to get business all
    // ##################################################
    // ##################################################
    // This method is to get business by id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/business",
     *      operationId="getAllBusinesses",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },

     *    *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="business name"
     * ),
     *
     *
     *      summary="This method is to get all business ",
     *      description="This method is to get all business ",
     *
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
    public function getAllBusinesses(Request $request)
    {


        $businessQuery =  Business::with("owner");

        if (!empty($request->search_key)) {
            $businessQuery = $businessQuery->where(function ($query) use ($request) {
                $term = $request->search_key;
                $query->where("Name", "like", "%" . $term . "%");
            });
        }



        $data["business"] =   $businessQuery->get();
        $data["ok"] = true;






        //         if(!$data["business"]) {
        //   return response([ "message" => "No Business Found"], 404);
        //         }
        return response($data, 200);
    }
    /**
     *
     * @OA\Get(
     *      path="/businesses/{perPage}",
     *      operationId="getBusinesses",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  *   *              @OA\Parameter(
     *         name="response_type",
     *         in="query",
     *         description="response_type: in pdf,csv,json",
     *         required=true,
     *  example="json"
     *      ),
     *    *  @OA\Parameter(
     * name="perPage",
     * in="path",
     * description="perPage",
     * required=true,
     * example="10"
     * ),
     *    *  @OA\Parameter(
     * name="search_key",
     * in="query",
     * description="search_key",
     * required=true,
     * example="business name"
     * ),
     *
     *
     *      summary="This method is to get all business ",
     *      description="This method is to get all business ",
     *
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
    public function getBusinesses($perPage, Request $request)
    {

        //  customers

        $businessQuery =  Business::withCount(

                "customers"
            )
            ->with(
                "owner",

            )
            ->when(request()->filled("Status"), function ($query) {
                $query->where("Status", request()->input("Status"));
            })
          ;

        if (!empty($request->search_key)) {
            $businessQuery = $businessQuery->where(function ($query) use ($request) {
                $term = $request->search_key;
                $query->where("Name", "like", "%" . $term . "%")
                    ->orWhere("Address", "like", "%" . $term . "%")
                    ->orWhere("PostCode", "like", "%" . $term . "%")
                    ->orWhere("Status", "like", "%" . $term . "%")
                    ->orWhere("About", "like", "%" . $term . "%")
                    ->orWhere("PhoneNumber", "like", "%" . $term . "%")
                    ->orWhere("EmailAddress", "like", "%" . $term . "%");
            });
        }




        $businesses =   $businessQuery->paginate($perPage);

        if (!empty($request->response_type) && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
            if (strtoupper($request->response_type) == 'PDF') {
                if (empty($businesses->count())) {
                    $pdf = PDF::loadView('pdf.no_data', []);
                } else {
                    $pdf = PDF::loadView('pdf.businesses', ["businesses" => $businesses]);
                }

                return $pdf->download(((!empty($request->file_name) ? $request->file_name : 'attendance') . '.pdf'));
            } elseif (strtoupper($request->response_type) === 'CSV') {

                return Excel::download(new BusinessExport($businesses), ((!empty($request->file_name) ? $request->file_name : 'businesses') . '.csv'));
            }
        } else {
            return response()->json($businesses, 200);
        }
    }

    /**
     *
     * @OA\Get(
     *      path="/client/businesses/{perPage}",
     *      operationId="getBusinessesClients",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *    *  @OA\Parameter(
     * name="perPage",
     * in="path",
     * description="perPage",
     * required=true,
     * example="10"
     * ),
     *    *  @OA\Parameter(
     * name="sort_by",
     * in="query",
     * description="sort_by",
     * required=true,
     * example="sort_by"
     * ),
     *    *  @OA\Parameter(
     * name="sort_type",
     * in="query",
     * description="sort_type",
     * required=true,
     * example="sort_type"
     * ),


     *
     *
     *      summary="This method is to get all business ",
     *      description="This method is to get all business ",
     *
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
    public function getBusinessesClients($perPage, Request $request)
    {

        // Get today's day (0 for Sunday, 1 for Monday, ..., 6 for Saturday)
        $today = Carbon::now()->dayOfWeek;

        $businessQuery = Business::when((!empty($request->sort_type) && !empty($request->sort_by)), function ($query) use ($request) {
            $query->orderBy($request->sort_by, $request->sort_type);
        });



        if (!empty($request->search_key)) {
            $businessQuery->where(function ($query) use ($request) {
                $term = $request->search_key;
                $query->where("Name", "like", "%" . $term . "%");
            });
        }

        $businesses = $businessQuery
            ->select(
                "id",
                "Name",
                "header_image",
                "rating_page_image",
                "placeholder_image",
                "Logo",
                "Address"
            )

            ->paginate($perPage);


      $businesses->getCollection()->transform(function ($business) use ($today, $request) {
    $totalCount = 0;
    $totalRating = 0;

    foreach (Star::get() as $star) {
        $selectedCount = ReviewValueNew::leftjoin('review_news', 'review_value_news.review_id', '=', 'review_news.id')
            ->where([
                "review_news.business_id" => $business->id,
                "star_id" => $star->id,
            ])
            ->distinct("review_value_news.review_id", "review_value_news.question_id");

        if (!empty($request->start_date) && !empty($request->end_date)) {
            $selectedCount = $selectedCount->whereBetween('review_news.created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $selectedCount = $selectedCount->count();

        $totalCount += $selectedCount * $star->value;
        $totalRating += $selectedCount;
    }

    $average_rating = $totalCount > 0 ? $totalCount / $totalRating : 0;

    $timing = $business->times()->with("timeSlots")->where('day', $today)->first();

    $business->average_rating = $average_rating;
    $business->total_rating_count = $totalCount;
    $business->out_of = 5;
    $business->timing = $timing;

    return $business;
});

return response()->json($businesses, 200);

  
    }


    // ##################################################
    // This method is to get business table by business id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/business/Restuarant/tables/{businessId}",
     *      operationId="getbusinessTableByBusinessId",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get business with table by business id",
     *      description="This method is to get business with table by business id",
     *
     *  *            @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="method",
     *         required=true,
     * example="1"
     *      ),
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

    public function getbusinessTableByBusinessId($businessId)
    {
        $data["business"] =   Business::with("owner", "table")->where(["id" => $businessId])->first();
        $data["ok"] = true;

        if (!$data["business"]) {
            return response(["message" => "No Business Found"], 404);
        }
        return response($data, 200);
    }
}
