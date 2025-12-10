<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserWithBusinessRequest;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\OwnerRequest;
use App\Http\Requests\PDFUploadRequest;
use App\Mail\NotifyMail;
use App\Models\BusinessDay;
use App\Models\Question;
use App\Models\QuestionStar;
use App\Models\Business;
use App\Models\StarTag;
use App\Models\Tag;
use App\Models\User;
use App\Services\BusinessService;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class OwnerController extends Controller
{


    protected $businessService;
    protected $userService;

    /**
     * Constructor to inject BusinessService
     *
     * @param BusinessService $businessService
     * @param UserService $userService
     */
    public function __construct(BusinessService $businessService, UserService $userService)
    {
        $this->businessService = $businessService;
        $this->userService = $userService;
    }

    // ##################################################
    // This method is to store user
    // ##################################################
    public function createUser(OwnerRequest $request)
    {
        // VALIDATE REQUEST
        $validatedData = $request->validated();

        // CREATE USER
        $validatedData['password'] = Hash::make($validatedData['password']);
        $validatedData['remember_token'] = Str::random(10);
        $user =  User::create($validatedData);

        // GENERATE ACCESS TOKEN
        $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;

        // RETURN RESPONSE
        return response([
            "success" => true,
            "message" => "You have successfully registered",
            "data" => $user,
        ], 200);
    }


    // ##################################################
    // This method is to store user2
    // ##################################################


    /**
     *
     * @OA\Post(
     *      path="/owner/user/registration",
     *      operationId="createUser2",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user",
     *      description="This method is to store user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","password","first_Name","phone"},
     *
     *             @OA\Property(property="email", type="string", format="string",example="test@g.c"),
     *            @OA\Property(property="password", type="string", format="string",example="12345678"),
     *            @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *               @OA\Property(property="phone", type="string", format="string",example="Rifat"),
     *                @OA\Property(property="type", type="string", format="string",example="customer")
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




    public function createUser2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email|required|unique:users,email',
            'password' => 'required|string|min:6',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'type' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();

        $validatedData['password'] = Hash::make($validatedData['password']);
        $validatedData['remember_token'] = Str::random(10);

        $user = User::create($validatedData);

        // email verification token
        $email_token = Str::random(30);
        $user->email_verify_token = $email_token;
        $user->email_verify_token_expires = Carbon::now()->addDay();
        $user->save();

        // send verification email
        if (env("SEND_EMAIL") == "TRUE") {
            Mail::to($validatedData["email"])->send(new NotifyMail($user));
        }

        // generate access token
        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        // load relationships if needed
        $user = User::with("business", "roles")->find($user->id);

        // attach token to response (same style as login)
        $user->token = $token;

        return response()->json($user, 200);
    }




    /**
     * @OA\Post(
     *      path="/v1.0/create-user-with-business",
     *      operationId="createUserWithBusiness",
     *      tags={"owner"},
     *      security={{"bearerAuth": {}}},
     *      summary="Create a new user with associated business",
     *      description="Register a new business owner user and create their business profile",
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","password","first_Name","last_Name","business_name","business_address","business_postcode","times"},
     *            @OA\Property(property="email", type="string", format="email", example="rifat@gmail.com"),
     *            @OA\Property(property="password", type="string", format="password", example="12345678"),
     *            @OA\Property(property="first_Name", type="string", example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", example="Khan"),
     *            @OA\Property(property="phone", type="string", example="+1234567890"),
     *            @OA\Property(property="business_name", type="string", example="Tech Solutions Ltd"),
     *            @OA\Property(property="business_address", type="string", example="123 Business St"),
     *            @OA\Property(property="business_postcode", type="string", example="12345"),

     *            @OA\Property(property="business_EmailAddress", type="string", format="email", example="contact@business.com"),
     *            @OA\Property(property="business_GoogleMapApi", type="string", example="AIzaSyXXXXXXXXX"),
     *            @OA\Property(property="business_homeText", type="string", example="Welcome to our business"),
     *            @OA\Property(property="business_AdditionalInformation", type="string", example="Additional info"),
     *            @OA\Property(property="business_Webpage", type="string", format="url", example="https://business.com"),
     *            @OA\Property(property="business_PhoneNumber", type="string", example="+1234567890"),
     *            @OA\Property(property="business_About", type="string", example="About our business"),
     *            @OA\Property(property="business_Layout", type="string", example="modern"),
     *            @OA\Property(property="review_type", type="string", enum={"emoji", "star"}, example="emoji"),
     *            @OA\Property(property="Is_guest_user", type="boolean", example=false),
     *            @OA\Property(property="is_review_slider", type="boolean", example=false),
     *            @OA\Property(property="review_only", type="boolean", example=true),
      *            @OA\Property(property="is_branch", type="boolean", example=true),
     * 
     *            @OA\Property(property="header_image", type="string", example="/header_image/default.png"),
     *            @OA\Property(property="primary_color", type="string", example="#FF0000"),
     *            @OA\Property(property="secondary_color", type="string", example="#00FF00"),
     *            @OA\Property(property="client_primary_color", type="string", example="#172c41"),
     *            @OA\Property(property="client_secondary_color", type="string", example="#ac8538"),
     *            @OA\Property(property="client_tertiary_color", type="string", example="#ffffff"),
     *            @OA\Property(property="user_review_report", type="boolean", example=true),
     *            @OA\Property(property="guest_user_review_report", type="boolean", example=true),
     *            @OA\Property(property="times", type="array",
     *                @OA\Items(
     *                    @OA\Property(property="day", type="integer", example=0),
     *                    @OA\Property(property="is_weekend", type="boolean", example=true),
     *                    @OA\Property(property="time_slots", type="array",
     *                        @OA\Items(
     *                            @OA\Property(property="start_at", type="string", format="time", example="10:00"),
     *                            @OA\Property(property="end_at", type="string", format="time", example="11:00")
     *                        )
     *                    )
     *                )
     *            )
     *         )
     *      ),
     *      @OA\Response(response=200, description="User and business created successfully"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=400, description="Bad Request"),
     *      @OA\Response(response=404, description="Not found")
     * )
     */
    public function createUserWithBusiness(CreateUserWithBusinessRequest $request,)
    {
        return DB::transaction(function () use ($request) {
            $validatedData = $request->validated();

            // Create user with verification email
            $user = $this->userService->createBusinessOwner($validatedData);

            // Create business with all configurations
            $business = $this->businessService->createBusinessWithSchedule($user, $validatedData);

            // Generate access token
            $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;


            return response()->json([
                'success' => true,
                'message' => 'You have successfully registered',
                'data' => [
                    'user' => $user,
                    'business' => $business
                ],
            ], 200);
        });
    }

    /**
     * @OA\Post(
     *      path="/v1.0/client/create-user-with-business",
     *      operationId="createUserWithBusinessClient",
     *      tags={"auth"},
     *      summary="Create a new user with associated business",
     *      description="Register a new business owner user and create their business profile",
     *      @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","password","first_Name","last_Name","business_name","business_address","business_postcode","times"},
     *            @OA\Property(property="email", type="string", format="email", example="rifat@gmail.com"),
     *            @OA\Property(property="password", type="string", format="password", example="12345678"),
     *            @OA\Property(property="first_Name", type="string", example="Rifat"),
     *            @OA\Property(property="last_Name", type="string", example="Khan"),
     *            @OA\Property(property="phone", type="string", example="+1234567890"),
     *            @OA\Property(property="business_name", type="string", example="Tech Solutions Ltd"),
     *            @OA\Property(property="business_address", type="string", example="123 Business St"),
     *            @OA\Property(property="business_postcode", type="string", example="12345"),

     *            @OA\Property(property="business_EmailAddress", type="string", format="email", example="contact@business.com"),
     *            @OA\Property(property="business_GoogleMapApi", type="string", example="AIzaSyXXXXXXXXX"),
     *            @OA\Property(property="business_homeText", type="string", example="Welcome to our business"),
     *            @OA\Property(property="business_AdditionalInformation", type="string", example="Additional info"),
     *            @OA\Property(property="business_Webpage", type="string", format="url", example="https://business.com"),
     *            @OA\Property(property="business_PhoneNumber", type="string", example="+1234567890"),
     *            @OA\Property(property="business_About", type="string", example="About our business"),
     *            @OA\Property(property="business_Layout", type="string", example="modern"),
     *            @OA\Property(property="review_type", type="string", enum={"emoji", "star"}, example="emoji"),
     *            @OA\Property(property="Is_guest_user", type="boolean", example=false),
     *            @OA\Property(property="is_review_slider", type="boolean", example=false),
     *            @OA\Property(property="review_only", type="boolean", example=true),
     *            @OA\Property(property="is_branch", type="boolean", example=true),
     *            @OA\Property(property="header_image", type="string", example="/header_image/default.png"),
     *            @OA\Property(property="primary_color", type="string", example="#FF0000"),
     *            @OA\Property(property="secondary_color", type="string", example="#00FF00"),
     *            @OA\Property(property="client_primary_color", type="string", example="#172c41"),
     *            @OA\Property(property="client_secondary_color", type="string", example="#ac8538"),
     *            @OA\Property(property="client_tertiary_color", type="string", example="#ffffff"),
     *            @OA\Property(property="user_review_report", type="boolean", example=true),
     *            @OA\Property(property="guest_user_review_report", type="boolean", example=true),
     *            @OA\Property(property="times", type="array",
     *                @OA\Items(
     *                    @OA\Property(property="day", type="integer", example=0),
     *                    @OA\Property(property="is_weekend", type="boolean", example=true),
     *                    @OA\Property(property="time_slots", type="array",
     *                        @OA\Items(
     *                            @OA\Property(property="start_at", type="string", format="time", example="10:00"),
     *                            @OA\Property(property="end_at", type="string", format="time", example="11:00")
     *                        )
     *                    )
     *                )
     *            )
     *         )
     *      ),
     *      @OA\Response(response=200, description="User and business created successfully"),
     *      @OA\Response(response=401, description="Unauthenticated"),
     *      @OA\Response(response=422, description="Validation error"),
     *      @OA\Response(response=403, description="Forbidden"),
     *      @OA\Response(response=400, description="Bad Request"),
     *      @OA\Response(response=404, description="Not found")
     * )
     */
    public function createUserWithBusinessClient(CreateUserWithBusinessRequest $request,)
    {
        return DB::transaction(function () use ($request) {
            $validatedData = $request->validated();

            // Create user with verification email
            $user = $this->userService->createBusinessOwner($validatedData);

            // Create business with all configurations
            $business = $this->businessService->createBusinessWithSchedule($user, $validatedData);

            // Generate access token
            $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;


            return response()->json([
                'success' => true,
                'message' => 'You have successfully registered',
                'data' => [
                    'user' => $user,
                    'business' => $business
                ],
            ], 200);
        });
    }





    // ##################################################
    // This method is to store guest user
    // ##################################################


    /**
     *
     * @OA\Post(
     *      path="/v1.0/register-guest-users",
     *      operationId="createGuestUser",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store guest user",
     *      description="This method is to store guest user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","first_Name"},
     *
     *             @OA\Property(property="email", type="string", format="string",example="test@g.c"),
     *            @OA\Property(property="type", type="string", format="string",example="12345678"),
     *            @OA\Property(property="first_Name", type="string", format="string",example=""),
     *               @OA\Property(property="phone", type="string", format="string",example="")
     *         ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="You have successfully registered"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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






    public function createGuestUser(Request $request)
    {

        // VALIDATE REQUEST
        $validator = Validator::make($request->all(), [
            'email' => 'email|required|unique:users,email',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'type' => 'nullable',
        ]);

        // CHECK VALIDATION FAILURES
        $validatedData = $validator->validated();

        // ADD PASSWORD AND REMEMBER TOKEN
        $validatedData['remember_token'] = Str::random(10);

        // CREATE USER
        $user =  User::create($validatedData);
        // GENERATE ACCESS TOKEN
        $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;

        // RETURN RESPONSE
        return response([
            "success" => true,
            "message" => "You have successfully registered",
            "data" => $user,
        ], 200);
    }



    // ##################################################
    // This method is to update pin
    // ##################################################


    /**
     *
     * @OA\Post(
     *      path="/owner/pin/{ownerId}",
     *      operationId="updatePin",
     *      tags={"owner"},

     *      summary="This method is to update pin",
     *      description="This method is to update pin",
     *
     *  @OA\Parameter(
     * name="ownerId",
     * in="path",
     * description="method",
     * required=true,
     * example="1"
     * ),
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"pin"},
     *
     *             @OA\Property(property="pin", type="string", format="string",example="test@g.c")
     *
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






    public function updatePin($id, Request $request)
    {
        // VALIDATE REQUEST
        $validator = Validator::make($request->all(), [
            'pin' => 'required',
        ]);

        // CHECK VALIDATION FAILURES
        $validatedData = $validator->validated();

        // UPDATE USER PIN
        $user = User::find($id);

        if (!$user) {
            return response([
                "success" => false,
                "message" => "No User Found"
            ], 404);
        }

        // SAVE NEW PIN
        $user->pin = $validatedData["pin"];
        $user->save();

        // RETURN RESPONSE
        return response([
            "success" => true,
            "message" => "Pin Updated Successfully."
        ], 200);
    }








    // ##################################################
    // This method is to update  user image
    // ##################################################

    /**
     *
     * @OA\Post(
     *      path="/v1.0/upload/profile-image",
     *      operationId="updateImage",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update  user image",
     *      description="This method is to update  user image",

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
     *          description="Unprocessable Content",
     *    @OA\JsonContent(),
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden",
     * @OA\JsonContent()
     * ),
     *   @OA\Response(
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
    public function updateImage(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $imageName = time() . '.' . $request->logo->extension();

        $request->logo->move(public_path('img/user'), $imageName);

        $imageName = "img/user/" . $imageName;

        // GET USER
        $user =    User::when(
            request()->filled("owner_id"),
            function ($query) {
                $query->where([
                    "id" => request()->input("owner_id")

                ]);
            },
            function ($query) {
                $query->where([
                    "id" => auth()->user()->id
                ]);
            }
        )
            ->first();

        // CHECK IF USER EXISTS
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "No User Found"
            ], 404);
        }

        $user->image = $imageName;

        $user->save();

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Image updated successfully",
            "data" => $user
        ], 200);
    }




    /**
     *
     * @OA\Post(
     *      path="/v1.0/header-image/{business_id}",
     *      operationId="createHeaderImage",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store header image ",
     *      description="This method is to store header image",
     *    @OA\Parameter(
     * name="business_id",
     * in="path",
     * description="method",
     * required=true,
     * example="1"
     * ),
     *  @OA\RequestBody(
     *   @OA\MediaType(
     *     mediaType="multipart/form-data",
     *     @OA\Schema(
     *         required={"image"},
     *         @OA\Property(
     *             description="image to upload",
     *             property="image",
     *             type="file",
     *             collectionFormat="multi",
     *         )
     *     )
     * )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Header image uploaded successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createHeaderImage($business_id, ImageUploadRequest $request)
    {
        try {

            // VALIDATE REQUEST
            $request_payload = $request->validated();

            // CHECK BUSINESS OWNERSHIP
            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json([
                    "success" => false,
                    "message" => "This is not your business"
                ], 401);
            }

            $location =  "header_image";

            $new_file_name = time() . '_' . $request_payload["image"]->getClientOriginalName();

            $request_payload["image"]->move(public_path($location), $new_file_name);

            // UPDATE BUSINESS HEADER IMAGE
            $checkBusiness->update([
                "header_image" => ("/" . $location . "/" . $new_file_name)
            ]);


            // RETURN RESPONSE
            return response()->json([
                "success" => true,
                "message" => "Header image uploaded successfully",
                "data" => $checkBusiness,

            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     *
     * @OA\Post(
     *      path="/v1.0/placeholder-image/{business_id}",
     *      operationId="createPlaceholderImage",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store placeholder image ",
     *      description="This method is to store placeholder image",
     *   @OA\Parameter(
     * name="business_id",
     * in="path",
     * description="method",
     * required=true,
     * example="1"
     * ),
     *  @OA\RequestBody(
     *    @OA\MediaType(
     *     mediaType="multipart/form-data",
     *     @OA\Schema(
     *         required={"image"},
     *         @OA\Property(
     *             description="image to upload",
     *             property="image",
     *             type="file",
     *             collectionFormat="multi",
     *         )
     *     )
     * )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Placeholder image uploaded successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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
     *   @OA\Response(
     *      response=400,
     *      description="Bad Request",
     *   @OA\JsonContent()
     *   ),
     * @OA\Response(
     *      response=404,
     *      description="not found",
     *   @OA\JsonContent()
     *   )
     *      )
     *     )
     */

    public function createPlaceholderImage($business_id, ImageUploadRequest $request)
    {
        try {

            //  VALIDATE REQUEST
            $request_payload = $request->validated();

            // CHECK BUSINESS OWNERSHIP
            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json([
                    "success" => false,
                    "message" => "This is not your business"
                ], 401);
            }

            $location =  "placeholder_image";

            $new_file_name = time() . '_' . $request_payload["image"]->getClientOriginalName();

            $request_payload["image"]->move(public_path($location), $new_file_name);

            $checkBusiness->update([
                "placeholder_image" => ("/" . $location . "/" . $new_file_name)
            ]);

            // RETURN RESPONSE
            return response()->json([
                "success" => true,
                "message" => "Placeholder image uploaded successfully",
                "data" => $checkBusiness,
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }







    /**
     *
     * @OA\Post(
     *      path="/v1.0/rating-page-image/{business_id}",
     *      operationId="createRatingPageImage",
     *      tags={"business"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store Rating Page image ",
     *      description="This method is to store Rating Page image",
     * * *  @OA\Parameter(
     * name="business_id",
     * in="path",
     * description="method",
     * required=true,
     * example="1"
     * ),
     *  @OA\RequestBody(
     *   * @OA\MediaType(
     *     mediaType="multipart/form-data",
     *     @OA\Schema(
     *         required={"image"},
     *         @OA\Property(
     *             description="image to upload",
     *             property="image",
     *             type="file",
     *             collectionFormat="multi",
     *         )
     *     )
     * )



     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Rating Page image uploaded successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
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

    public function createRatingPageImage($business_id, ImageUploadRequest $request)
    {
        try {

            // VALIDATE REQUEST
            $request_payload = $request->validated();

            // CHECK BUSINESS OWNERSHIP
            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json([
                    "success" => false,
                    "message" => "This is not your business"
                ], 401);
            }

            $location =  "rating_page_image";

            $new_file_name = time() . '_' . $request_payload["image"]->getClientOriginalName();

            $request_payload["image"]->move(public_path($location), $new_file_name);

            $checkBusiness->update([
                "rating_page_image" => ("/" . $location . "/" . $new_file_name)
            ]);


            // RETURN RESPONSE
            return response()->json([
                "success" => true,
                "message" => "Rating Page image uploaded successfully",
                "data" => $checkBusiness,
            ], 200);
        } catch (Exception $e) {
            throw $e;
        }
    }



    // ##################################################
    // This method is to get user by id
    // ##################################################

    /**
     *
     * @OA\Get(
     *      path="/owner/{ownerId}",
     *      operationId="getOwnerById",
     *      tags={"owner"},

     *      summary="This method is to get user by id",
     *      description="This method is to get user by id",
     *
     *  *            @OA\Parameter(
     *         name="ownerId",
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
    public function getOwnerById($id)
    {
        $user =   User::where(["id" => $id])->first();

        if (!$user) {
            return response(["message" => "No User Found"], 404);
        }
        return response([
            "success" => true,
            "message" => "User fetched successfully",
            "data" => $user
        ], 200);
    }
    // ##################################################
    // This method is to get user not havhing business
    // ##################################################

    /**
     *
     * @OA\Get(
     *      path="/owner/getAllowner/withourbusiness",
     *      operationId="getOwnerNotHaveRestaurent",
     *      tags={"owner"},

     *      summary="This method is to get user not havhing business",
     *      description="This method is to get user not havhing business",
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

    public function getOwnerNotHaveRestaurent()
    {


        // @@@@@@@@@@
        // where not in restaurent select id
        $userIdsToExclude = Business::pluck('OwnerID')->toArray();
        $user =      User::whereNotIn('id', $userIdsToExclude)->get();

        // foreach($data["user"] as $deletableUser) {
        //     $deletableUser->delete();
        // }
        return response([
            "success" => true,
            "message" => "Users fetched successfully",
            "data" => $user
        ], 200);
    }
    // ##################################################
    // This method is to get user by phone number
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/owner/loaduser/bynumber/{phoneNumber}",
     *      operationId="getOwnerByPhoneNumber",
     *      tags={"owner"},
     *      summary="This method is to get user by phone number",
     *      description="This method is to get user by phone number",
     *
     * * @OA\Parameter(
     * name="phoneNumber",
     * in="path",
     * description="method",
     * required=true,
     * example="1"
     * ),
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


    public function getOwnerByPhoneNumber($phoneNumber)
    {
        $user =   User::where(["phone" => $phoneNumber])->first();


        if (!$user) {
            return response(["message" => "No User Found"], 404);
        }
        return response([
            "success" => true,
            "message" => "User fetched successfully",
            "data" => $user
        ], 200);
    }
    // ##################################################
    // This method is to update user
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/owner/update-user",
     *      operationId="updateUser",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user",
     *      description="This method is to update user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"last_name","first_Name","phone","Address"},
     * *             @OA\Property(property="id", type="string", format="string",example="1"),
     *             @OA\Property(property="last_name", type="string", format="string",example="test@g.c"),
     *            @OA\Property(property="first_Name", type="string", format="string",example="12345678"),
     *            @OA\Property(property="phone", type="string", format="string",example="Rifat"),

     *    *            @OA\Property(property="Address", type="string", format="string",example="12345678"),
     *  *    *            @OA\Property(property="door_no", type="string", format="string",example="12345678"),
     *
     *            @OA\Property(property="password", type="string", format="string",example="Rifat"),
     *  *            @OA\Property(property="old_password", type="string", format="string",example="Rifat"),
     *

     *  *               @OA\Property(property="post_code", type="string", format="string",example="Rifat"),

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





    public function updateUser(Request $request)
    {

        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission", 401]);
        }

        $updatableData = [
            "first_Name" => $request->first_Name,
            "last_Name" => $request->last_Name,
            "phone" => $request->phone,
            "Address" => $request->Address,
            "door_no" => $request->door_no,

            "post_code" => $request->post_code,
        ];
        $previousUser = User::where([
            "id" => $request->id
        ])
            ->first();

        if (!empty($request->password)) {
            if (!Hash::check((!empty($request->old_password) ? $request->old_password : ""), $previousUser->password)) {
                return response()->json(["message" => "incorrect old password", 401]);
            }

            $updatableData["password"] = Hash::make($request->password);
        }



        $user =    tap(User::where(["id" => $request->id]))->update(
            $updatableData
        )
            // ->with("somthing")

            ->first();


        if (!$user) {
            return response()->json(["message" => "No User Found"], 404);
        }


        $data["message"] = "user updates successfully";
        return response()->json([
            "success" => true,
            "message" => "user updates successfully",
            "data" => $user
        ], 200);
    }







    /**
     *
     * @OA\Patch(
     *      path="/owner/update-user/by-user",
     *      operationId="updateUserByUser",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to update user by user",
     *      description="This method is to update user by user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"last_name","first_Name","phone","Address"},
     *             @OA\Property(property="last_name", type="string", format="string",example="test@g.c"),
     *            @OA\Property(property="first_Name", type="string", format="string",example="12345678"),
     *            @OA\Property(property="phone", type="string", format="string",example="Rifat"),

     *    *            @OA\Property(property="Address", type="string", format="string",example="12345678"),
     *   *    *            @OA\Property(property="door_no", type="string", format="string",example="12345678"),
     *
     *            @OA\Property(property="password", type="string", format="string",example="Rifat"),
     *  *            @OA\Property(property="old_password", type="string", format="string",example="Rifat"),
     *

     *  *               @OA\Property(property="post_code", type="string", format="string",example="Rifat"),

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





    public function updateUserByUser(Request $request)
    {



        $updatableData = [
            "first_Name" => $request->first_Name,
            "last_Name" => $request->last_Name,
            "phone" => $request->phone,
            "Address" => $request->Address,
            "door_no" => $request->door_no,

            "post_code" => $request->post_code,
        ];
        $previousUser = User::where([
            "id" => $request->user()->id
        ])
            ->first();

        if (!empty($request->password)) {
            if (!Hash::check((!empty($request->old_password) ? $request->old_password : ""), $previousUser->password)) {
                return response()->json(["message" => "incorrect old password", 401]);
            }

            $updatableData["password"] = Hash::make($request->password);
        }



        $user =    tap(User::where(["id" => $request->user()->id]))->update(
            $updatableData
        )
            // ->with("somthing")

            ->first();


        if (!$user) {
            return response()->json(["message" => "No User Found"], 404);
        }


        return response()->json([
            "success" => true,
            "message" => "user updates successfully",
            "data" => $user
        ], 200);
    }
}
