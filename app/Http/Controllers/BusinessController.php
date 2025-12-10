<?php

namespace App\Http\Controllers;

use App\Exports\BusinessExport;
use App\Models\DailyView;

use App\Models\Notification;

use App\Models\Question;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\ReviewValueNew;
use App\Models\Star;
use App\Models\Tag;
use App\Models\User;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\StoreBusinessByOwnerRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Services\BusinessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PDF;
use Maatwebsite\Excel\Facades\Excel;

class BusinessController extends Controller
{
    protected $businessService;

    /**
     * Constructor to inject BusinessService
     *
     * @param BusinessService $businessService
     */
    public function __construct(BusinessService $businessService)
    {
        $this->businessService = $businessService;
    }

    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/v1.0/business",
     *      operationId="storeRestaurant",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Store a new business",
     *      description="Create a new business with the provided details",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode"},
     *            @OA\Property(property="Name", type="string", example="My Restaurant"),
     *            @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *            @OA\Property(property="PostCode", type="string", example="SW1A 1AA")

     *         )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Business created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business created successfully"),
     *              @OA\Property(property="data", type="object",
     *                   @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                      @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                      @OA\Property(property="OwnerID", type="integer", example=1),
     *                      @OA\Property(property="Status", type="string", example="Inactive"),
     *                      @OA\Property(property="Key_ID", type="string", example="abc123"),
     *                      @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025")
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
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */

    public function storeRestaurant(StoreBusinessRequest $request)
    {
        // VALIDATE REQUEST
        $payload_data = $request->validated();

        // ADD OWNER ID
        $payload_data["OwnerID"] = $request->user()->id;
        // ADD STATUS
        $payload_data["Status"] = "Inactive";

        // ADD KEY AND EXPIRY DATE
        $payload_data["Key_ID"] = Str::random(10);
        $payload_data["expiry_date"] = now()->addDays(15)->format('d-m-Y');

        // CREATE BUSINESS
        $business =  Business::create($payload_data);


        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business created successfully",
            "data" => $business
        ], 201);
    }
    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/business/by-owner-id",
     *      operationId="storeRestaurantByOwnerId",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Store a new business by specifying owner ID",
     *      description="Create a new business with the provided details and specified owner ID",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode","OwnerID"},
     *            @OA\Property(property="Name", type="string", example="My Restaurant"),
     *            @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *            @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *            @OA\Property(property="OwnerID", type="integer", example=1)
     *         )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Business created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business created successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                  @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                  @OA\Property(property="OwnerID", type="integer", example=1),
     *                  @OA\Property(property="Status", type="string", example="Inactive"),
     *                  @OA\Property(property="Key_ID", type="string", example="abc123d"),
     *                  @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025")
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
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */

    public function storeRestaurantByOwnerId(StoreBusinessByOwnerRequest $request)
    {
        // VALIDATE REQUEST
        $payload_data = $request->validated();

        // ADD STATUS
        $payload_data["Status"] = "Inactive";

        // ADD KEY AND EXPIRY DATE
        $payload_data["Key_ID"] = Str::random(10);
        $payload_data["expiry_date"] = now()->addDays(15)->format('d-m-Y');

        // CREATE BUSINESS
        $business = Business::create($payload_data);


        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business created successfully",
            "data" => $business
        ], 201);
    }
    /**
     *
     * @OA\Delete(
     *      path="/v1.0/business/{id}",
     *      operationId="deleteBusinessById",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Delete business by ID (Super Admin only)",
     *      description="Delete a business and its associated daily views. Only super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Business ID to delete",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business deleted successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to delete businesses")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function deleteBusinessById($id, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to delete businesses"
            ], 403);
        }

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($id);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // EXECUTE DELETION WITHIN DATABASE TRANSACTION
        DB::transaction(function () use ($business, $id) {
            // DELETE BUSINESS
            $business->delete();

            // DELETE ASSOCIATED DAILY VIEWS
            DailyView::where("business_id", $id)->delete();
        });

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business deleted successfully"
        ], 200);
    }

    /**
     *
     * @OA\Delete(
     *      path="/v1.0/business/delete/force-delete/{email}",
     *      operationId="deleteBusinessByRestaurantIdForceDelete",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Force delete business by email (Super Admin only - within 5 minutes of creation)",
     *      description="Force delete a business and all its associated data by email address. Only super admins can perform this action, and only if the business was created within the last 5 minutes.",
     *
     *      @OA\Parameter(
     *          name="email",
     *          in="path",
     *          description="Email address of the business to force delete",
     *          required=true,
     *          example="business@example.com",
     *          @OA\Schema(type="string", format="email")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business force deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business and all associated data force deleted successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to force delete businesses")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Business is too old to force delete",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business can only be force deleted within 5 minutes of creation")
     *          )
     *      )
     * )
     */
    public function deleteBusinessByRestaurantIdForceDelete($email, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to force delete businesses"
            ], 403);
        }

        // FIND BUSINESS BY EMAIL
        $business = Business::where("EmailAddress", $email)->first();

        // CHECK IF BUSINESS EXISTS
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // CHECK IF BUSINESS WAS CREATED WITHIN LAST 5 MINUTES
        if ($business->created_at < Carbon::now()->subMinutes(5)) {
            return response()->json([
                "success" => false,
                "message" => "Business can only be force deleted within 5 minutes of creation"
            ], 422);
        }

        // GET BUSINESS ID FOR RELATED DELETIONS
        $businessId = $business->id;
        $ownerId = $business->OwnerID;

        // EXECUTE FORCE DELETION WITHIN DATABASE TRANSACTION
        DB::transaction(function () use ($business, $businessId, $ownerId) {
            // FORCE DELETE BUSINESS
            $business->forceDelete();

            // DELETE ASSOCIATED OWNER USER
            User::where("id", $ownerId)->delete();

            // DELETE ASSOCIATED DAILY VIEWS
            DailyView::where("business_id", $businessId)->delete();

            // DELETE ASSOCIATED NOTIFICATIONS
            Notification::where("business_id", $businessId)->delete();

            // DELETE ASSOCIATED QUESTIONS
            Question::where("business_id", $businessId)->delete();

            // DELETE ASSOCIATED REVIEWS
            Review::where("business_id", $businessId)->delete();
            ReviewNew::where("business_id", $businessId)->delete();
            ReviewValue::where("business_id", $businessId)->delete();

            // DELETE ASSOCIATED TAGS
            Tag::where("business_id", $businessId)->delete();
        });

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business and all associated data force deleted successfully"
        ], 200);
    }


    // ##################################################
    // This method is to upload business image
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/v1.0/business/upload-image/{businessId}",
     *      operationId="uploadRestaurantImage",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Upload business logo image",
     *      description="Upload and update the logo image for a specific business. Only business owners or super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="logo",
     *                      type="string",
     *                      format="binary",
     *                      description="Image file to upload (jpeg, png, jpg, gif, svg - max 2MB)"
     *                  ),
     *                  required={"logo"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business logo uploaded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business logo uploaded successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Logo", type="string", example="img/business/1234567890_abc123.png")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to update this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
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
    public function uploadRestaurantImage($businessId, Request $request)
    {
        // VALIDATE REQUEST
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // CHECK OWNERSHIP OR SUPER ADMIN PERMISSION
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "success" => false,
                "message" => "You do not have permission to update this business"
            ], 403);
        }

        // GENERATE UNIQUE FILENAME
        $imageName = time() . '_' . uniqid() . '.' . $request->logo->extension();

        // MOVE FILE TO PUBLIC DIRECTORY
        $destinationPath = public_path('img/business');
        $request->logo->move($destinationPath, $imageName);

        // BUILD FULL IMAGE PATH
        $imagePath = "img/business/" . $imageName;

        // UPDATE BUSINESS LOGO
        $business->update([
            "Logo" => $imagePath
        ]);

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business logo uploaded successfully",
            "data" => [
                "id" => $business->id,
                "Name" => $business->Name,
                "Logo" => $business->Logo
            ]
        ], 200);
    }
    // ##################################################
    // This method is to update business details
    // ##################################################
    /**
     * @OA\Patch(
     *     path="/v1.0/business/{businessId}",
     *     operationId="UpdateBusiness",
     *     tags={"business"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="This method is to update business",
     *     description="This method is to update business",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example="1"
     *     ),
     *
     *     @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="HTTP method override",
     *         required=true,
     *         example="PATCH"
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"Name","Layout","Address","PostCode"},
     *
     *             @OA\Property(property="GoogleMapApi", type="string", format="string", example="business name"),
     *             @OA\Property(property="Name", type="string", format="string", example="business name"),
     *
     *             @OA\Property(property="EmailAddress", type="string", format="string", example="test@example.com"),
     *             @OA\Property(property="homeText", type="string", format="string", example="How was this?"),
     *             @OA\Property(property="AdditionalInformation", type="string", format="string", example="How was this?"),
     *
     *             @OA\Property(property="PostCode", type="string", format="string", example="12345"),
     *             @OA\Property(property="Webpage", type="string", format="string", example="https://example.com"),
     *             @OA\Property(property="PhoneNumber", type="string", format="string", example="+44123456789"),
     *
     *             @OA\Property(property="About", type="string", format="string", example="How was this?"),
     *             @OA\Property(property="Layout", type="string", format="string", example="How was this?"),
     *             @OA\Property(property="Address", type="string", format="string", example="Street 1, City"),
     *

     *             @OA\Property(property="show_image", type="string", format="string", example="0"),
     *
     *             @OA\Property(property="Key_ID", type="string", format="string", example="0"),
     *             @OA\Property(property="review_type", type="string", format="string", example="0"),
     *             @OA\Property(property="google_map_iframe", type="string", format="string", example="test"),
     *
     *             @OA\Property(property="Is_guest_user", type="boolean", format="boolean", example="false"),
     *             @OA\Property(property="is_review_slider", type="boolean", format="boolean", example="false"),
     *
     *             @OA\Property(property="header_image", type="string", format="string", example="/header_image/default.png"),
     *
     *             @OA\Property(property="primary_color", type="string", format="string", example="red"),
     *             @OA\Property(property="secondary_color", type="string", format="string", example="red"),
     *             @OA\Property(property="client_primary_color", type="string", format="string", example="red"),
     *             @OA\Property(property="client_secondary_color", type="string", format="string", example="red"),
     *             @OA\Property(property="client_tertiary_color", type="string", format="string", example="red"),
     *
     *             @OA\Property(property="user_review_report", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="guest_user_review_report", type="boolean", format="boolean", example="1"),
     *
     *             @OA\Property(property="is_report_email_enabled", type="boolean", format="boolean", example="1"),
     *
     *             @OA\Property(property="pin", type="string", format="string", example="1"),
     *
     *             @OA\Property(property="is_registered_user_overall_review", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_registered_user_survey", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_registered_user_survey_required", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_registered_user_show_stuffs", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_registered_user_show_stuff_image", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_registered_user_show_stuff_name", type="boolean", format="boolean", example="1"),
     *
     *             @OA\Property(property="enable_ip_check", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="enable_location_check", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="latitude", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="longitude", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="review_distance_limit", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="review_labels", type="string", format="string", example="['a','b']"),
     *             @OA\Property(property="threshold_rating", type="boolean", format="boolean", example="1"),
     *
     *             @OA\Property(property="is_guest_user_overall_review", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_guest_user_survey", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_guest_user_survey_required", type="boolean", format="boolean", example="1"),
      *             @OA\Property(property="is_branch", type="boolean", format="boolean", example="1"), 
     * 
     * 
     * 
     *             @OA\Property(property="is_guest_user_show_stuffs", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_guest_user_show_stuff_image", type="boolean", format="boolean", example="1"),
     *             @OA\Property(property="is_guest_user_show_stuff_name", type="boolean", format="boolean", example="1")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Business updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business updated successfully"),
     *             @OA\Property(property="business", type="object", description="Updated business object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent()
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="This is not your business",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This is not your business")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No Business Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No Business Found")
     *         )
     *     )
     * )
     */


    public function UpdateBusiness($businessId, UpdateBusinessRequest $request)
    {

        // Validate
        $request_payload = $request->validated();

        // Check Business
        $business =    Business::find($businessId);

        if (!$business) {
            return response()->json([
                "status" => false,
                "message" => "No Business Found"
            ], 404);
        }

        // Check Ownership or Super admin
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            return response()->json([
                "status" => false,
                "message" => "This is not your business"
            ], 403);
        }

        // Update
        $business->update($request_payload);



        // Return
        return response()->json([
            "status" => true,
            "message" => "Business updated successfully",
            "business" => $business
        ], 200);
    }
    // ##################################################
    // This method is to get business by id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/v1.0/business/{businessId}",
     *      operationId="getBusinessById",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get business details by ID",
     *      description="Retrieve detailed information about a specific business including owner information",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business found successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business found successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                  @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                  @OA\Property(property="EmailAddress", type="string", example="business@example.com"),
     *                  @OA\Property(property="PhoneNumber", type="string", example="+44123456789"),
     *                  @OA\Property(property="Webpage", type="string", example="https://example.com"),
     *                  @OA\Property(property="About", type="string", example="About the business"),
     *                  @OA\Property(property="Logo", type="string", example="img/business/logo.png"),
     *                  @OA\Property(property="Status", type="string", example="Active"),
     *                  @OA\Property(property="Key_ID", type="string", example="abc123"),
     *                  @OA\Property(property="OwnerID", type="integer", example=1),

     *                  @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025"),
     *                  @OA\Property(property="created_at", type="string", format="datetime", example="2025-01-01T12:00:00.000000Z"),
     *                  @OA\Property(property="updated_at", type="string", format="datetime", example="2025-01-15T12:00:00.000000Z"),
     *                  @OA\Property(
     *                      property="owner",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="email", type="string", example="owner@example.com")
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function getBusinessById($businessId)
    {

        // GET BUSINESS WITH OWNER
        $business = Business::with("owner")
            ->where("id", $businessId)
            ->first();

        // CHECK IF BUSINESS EXISTS
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business found successfully",
            "data" => $business
        ], 200);
    }



    // ##################################################
    // This method is to get all businesses
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/v1.0/business",
     *      operationId="getAllBusinesses",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get all businesses with optional filters and pagination",
     *      description="Retrieve a list of all businesses with optional search and filtering capabilities. Supports both paginated and non-paginated responses.",
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term to filter businesses by name",
     *          required=false,
     *          example="Restaurant",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="Status",
     *          in="query",
     *          description="Filter by business status (Active, Inactive)",
     *          required=false,
     *          example="Active",
     *          @OA\Schema(type="string", enum={"Active", "Inactive"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="owner_id",
     *          in="query",
     *          description="Filter by owner ID",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page (if pagination is required)",
     *          required=false,
     *          example="15",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="response_type",
     *          in="query",
     *          description="Export format (PDF, CSV, or JSON by default)",
     *          required=false,
     *          example="json",
     *          @OA\Schema(type="string", enum={"json", "pdf", "csv"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="file_name",
     *          in="query",
     *          description="Custom filename for PDF/CSV export (without extension)",
     *          required=false,
     *          example="businesses_export",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Businesses retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Businesses retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  description="Array of business objects",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street"),
     *                      @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                      @OA\Property(property="Status", type="string", example="Active"),
     *                      @OA\Property(property="OwnerID", type="integer", example=1),

     *                      @OA\Property(
     *                          property="owner",
     *                          type="object",
     *                          @OA\Property(property="id", type="integer", example=1),
     *                          @OA\Property(property="name", type="string", example="John Doe"),
     *                          @OA\Property(property="email", type="string", example="owner@example.com")
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="to", type="integer", example=15),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="last_page", type="integer", example=5),
     *                  @OA\Property(property="total", type="integer", example=75),
     *                  @OA\Property(property="path", type="string", example="http://api.example.com/v1.0/business"),
     *                  @OA\Property(property="first_page_url", type="string", example="http://api.example.com/v1.0/business?page=1"),
     *                  @OA\Property(property="last_page_url", type="string", example="http://api.example.com/v1.0/business?page=5"),
     *                  @OA\Property(property="next_page_url", type="string", nullable=true, example="http://api.example.com/v1.0/business?page=2"),
     *                  @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      )
     * )
     */
    public function getAllBusinesses(Request $request)
    {
        // BUILD QUERY WITH FILTER SCOPE
        $businessQuery = Business::with("owner")->filter();

        // HANDLE PDF/CSV EXPORT
        if ($request->filled('response_type') && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
            $businesses = $businessQuery->get();

            if (strtoupper($request->response_type) == 'PDF') {
                if ($businesses->isEmpty()) {
                    $pdf = PDF::loadView('pdf.no_data', []);
                } else {
                    $pdf = PDF::loadView('pdf.businesses', ["businesses" => $businesses]);
                }
                return $pdf->download(($request->input('file_name', 'businesses') . '.pdf'));
            } elseif (strtoupper($request->response_type) === 'CSV') {
                return Excel::download(
                    new BusinessExport($businesses),
                    ($request->input('file_name', 'businesses') . '.csv')
                );
            }
        }

        // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
        $result = retrieve_data($businessQuery);

        return response()->json([
            "success" => true,
            "message" => "Businesses retrieved successfully",
            "data" => $result['data'],
            "meta" => $result['meta']
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/businesses",
     *      operationId="getBusinessesClients",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get businesses for client view with ratings and timing",
     *      description="Retrieve businesses with calculated ratings, timing information, and optional pagination",
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page (if pagination is required)",
     *          required=false,
     *          example="15",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="Field to sort by (e.g., Name, Address, created_at)",
     *          required=false,
     *          example="Name",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_type",
     *          in="query",
     *          description="Sort direction (asc or desc)",
     *          required=false,
     *          example="asc",
     *          @OA\Schema(type="string", enum={"asc", "desc"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term to filter businesses by name",
     *          required=false,
     *          example="Restaurant",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date for filtering reviews (Y-m-d format)",
     *          required=false,
     *          example="2025-01-01",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date for filtering reviews (Y-m-d format)",
     *          required=false,
     *          example="2025-12-31",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Businesses retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Businesses retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="header_image", type="string", example="/header_image/default.png"),
     *                      @OA\Property(property="rating_page_image", type="string", example="/rating_image/default.png"),
     *                      @OA\Property(property="placeholder_image", type="string", example="/placeholder/default.png"),
     *                      @OA\Property(property="Logo", type="string", example="img/business/logo.png"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street"),
     *                      @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                      @OA\Property(property="total_rating_count", type="integer", example=120),
     *                      @OA\Property(property="out_of", type="integer", example=5),
     *                      @OA\Property(
     *                          property="timing",
     *                          type="object",
     *                          description="Business timing for current day",
     *                          @OA\Property(property="day", type="integer", example=1),
     *                          @OA\Property(
     *                              property="timeSlots",
     *                              type="array",
     *                              @OA\Items(
     *                                  type="object",
     *                                  @OA\Property(property="start_time", type="string", example="09:00"),
     *                                  @OA\Property(property="end_time", type="string", example="17:00")
     *                              )
     *                          )
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="to", type="integer", example=15),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="last_page", type="integer", example=5),
     *                  @OA\Property(property="total", type="integer", example=75),
     *                  @OA\Property(property="path", type="string", example="http://api.example.com/client/businesses"),
     *                  @OA\Property(property="first_page_url", type="string", example="http://api.example.com/client/businesses?page=1"),
     *                  @OA\Property(property="last_page_url", type="string", example="http://api.example.com/client/businesses?page=5"),
     *                  @OA\Property(property="next_page_url", type="string", nullable=true, example="http://api.example.com/client/businesses?page=2"),
     *                  @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      )
     * )
     */
    public function getBusinessesClients(Request $request)
    {
        // Get today's day (0 for Sunday, 1 for Monday, ..., 6 for Saturday)
        $today = Carbon::now()->dayOfWeek;

        // BUILD QUERY WITH FILTER SCOPE
        $businessQuery = Business::filterClients();

        // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
        $result = retrieve_data($businessQuery);

        // Transform collection with ratings and timing
        $result['data'] = collect($result['data'])->map(function ($business) use ($today, $request) {
            return $this->businessService->enrichBusinessWithRatingsAndTiming($business, $today, $request);
        })->toArray();

        return response()->json([
            "success" => true,
            "message" => "Businesses retrieved successfully",
            "data" => $result['data'],
            "meta" => $result['meta']
        ], 200);
    }

    // ##################################################
    // This method is to get business table by business id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/v1.0/restaurants/tables/{businessId}",
     *      operationId="getRestaurantTableByBusinessId",
     *      tags={"business"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get business with tables by business ID",
     *      description="Retrieve business information including associated tables and owner details",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business with tables retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business with tables retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street"),
     *                  @OA\Property(
     *                      property="owner",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="email", type="string", example="owner@example.com")
     *                  ),
     *                  @OA\Property(
     *                      property="table",
     *                      type="array",
     *                      description="Associated tables",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(property="id", type="integer", example=1),
     *                          @OA\Property(property="table_number", type="string", example="T1")
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function getRestaurantTableByBusinessId($businessId)
    {
        // GET BUSINESS WITH OWNER AND TABLES
        $business = Business::with("owner", "table")
            ->where("id", $businessId)
            ->first();

        // CHECK IF BUSINESS EXISTS
        if (!$business) {
            return response()->json([
                "success" => false,
                "message" => "Business not found"
            ], 404);
        }

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business with tables retrieved successfully",
            "data" => $business
        ], 200);
    }
}
