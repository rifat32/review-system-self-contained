<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\OwnerRequest;
use App\Http\Requests\PDFUploadRequest;
use App\Mail\NotifyMail;
use App\Models\BusinessDay;
use App\Models\Question;
use App\Models\QusetionStar;
use App\Models\Business;
use App\Models\StarTag;
use App\Models\Tag;
use App\Models\User;
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




    // ##################################################
    // This method is to store user
    // ##################################################
    public function createUser(OwnerRequest $request)
    {




        $validatedData = $request->validated();

        $validatedData['password'] = Hash::make($validatedData['password']);
        $validatedData['remember_token'] = Str::random(10);
        $user =  User::create($validatedData);
        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $data["user"] = $user;
        return response(["ok" => true, "message" => "You have successfully registered", "data" => $data, "token" => $token], 200);
    }
    // ##################################################
    // This method is to store super admin
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/owner/super/admin",
     *      operationId="createsuperAdmin",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store super admin",
     *      description="This method is to store super admin",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","password","first_Name"},
     *
     *             @OA\Property(property="email", type="string", format="string",example="test@g.c"),
     *            @OA\Property(property="password", type="string", format="string",example="12345678"),
     *            @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *               @OA\Property(property="phone", type="string", format="string",example="Rifat"),
     *               @OA\Property(property="last_Name", type="string", format="string",example="Rifat"),

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




    public function createsuperAdmin(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'email' => 'email|required|unique:users,email',
            'password' => 'required|string|min:6',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'last_Name' => 'nullable'
        ]);

        $validatedData = $validator->validated();

        $validatedData['password'] = Hash::make($validatedData['password']);
        $validatedData['remember_token'] = Str::random(10);
        $validatedData['email_verified_at']  = now();
        $user =  User::create($validatedData);
        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $data["user"] = $user;
        if (!Role::where(['name' => 'superadmin'])->exists()) {
            Role::create(['name' => 'superadmin']);
        }
        $user->assignRole('superadmin');
        return response(["ok" => true, "message" => "You have successfully registered", "data" => $data, "token" => $token], 200);
    }
    // ##################################################
    // This method is to get role
    // ##################################################


    /**
     *
     * @OA\Get(
     *      path="/owner/role/get-role",
     *      operationId="getRole",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get role",
     *      description="This method is to get role",
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


    public function getRole(Request $request)
    {


        return response()->json($request->user()->getRoleNames());
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
     *  *               @OA\Property(property="type", type="string", format="string",example="customer")
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
     *
     * @OA\Post(
     *      path="/owner/user/with/business",
     *      operationId="createUserWithBusiness",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to store user with business",
     *      description="This method is to store user with business",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email","password","first_Name","phone"},
     *
     *             @OA\Property(property="email", type="string", format="string",example="rifat@gmail.com"),
     *            @OA\Property(property="password", type="string", format="string",example="12345678"),
     *            @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *  *            @OA\Property(property="last_Name", type="string", format="string",example="Rifat"),
     *               @OA\Property(property="phone", type="string", format="string",example="Rifat"),
     *
     *       @OA\Property(property="business_name", type="string", format="string",example="business_name"),
     *            @OA\Property(property="business_address", type="string", format="string",example="business_address"),
     *            @OA\Property(property="business_postcode", type="string", format="string",example="business_postcode"),
     *               @OA\Property(property="business_enable_question", type="string", format="string",example="0"),
     *
     *  *   *               @OA\Property(property="business_EmailAddress", type="string", format="string",example="0"),
     *   *               @OA\Property(property="business_GoogleMapApi", type="string", format="string",example="0"),
    
     *  *  *   *               @OA\Property(property="business_homeText", type="string", format="string",example="0"),
     *  *  *   *               @OA\Property(property="business_AdditionalInformation", type="string", format="string",example="0"),
     *  *  *   *               @OA\Property(property="business_Webpage", type="string", format="string",example="0"),
     *  *  *   *               @OA\Property(property="business_PhoneNumber", type="string", format="string",example="0"),
     *  *  *   *               @OA\Property(property="business_About", type="string", format="string",example="0"),
     *  *  *   *               @OA\Property(property="business_Layout", type="string", format="string",example="0"),
   
    
     *
     *        @OA\Property(property="review_type", type="string", format="string",example="emoji"),
     *  *        @OA\Property(property="Is_guest_user", type="boolean", format="boolean",example="false"),
     *  *        @OA\Property(property="is_review_silder", type="boolean", format="boolean",example="false"),
     *   *  *        @OA\Property(property="review_only", type="boolean", format="boolean",example="true"),
     * 
     *
     *
     *     *   *   *    *   *  *        @OA\Property(property="header_image", type="string", format="string",example="/header_image/default.png"),
     *

     *
    
     *
     *    *  *    *   *  *        @OA\Property(property="primary_color", type="string", format="string",example="red"),
     *  *  *    *   *  *        @OA\Property(property="secondary_color", type="string", format="string",example="red"),
     *
     *  *  *  *    *   *  *        @OA\Property(property="client_primary_color", type="string", format="string",example="red"),
     *
     *  *  *  *    *   *  *        @OA\Property(property="client_secondary_color", type="string", format="string",example="red"),
     *
     *  *  *  *    *   *  *        @OA\Property(property="client_tertiary_color", type="string", format="string",example="red"),
     *
     *    *  *  *  *    *   *  *        @OA\Property(property="user_review_report", type="boolean", format="boolean",example="1"),
     *
     *    *    *  *  *  *    *   *  *        @OA\Property(property="guest_user_review_report", type="boolean", format="boolean",example="1"),
     *
     *            @OA\Property(property="times", type="array",
     *                @OA\Items(
     *                    @OA\Property(property="day", type="integer", example=0),
     *                    @OA\Property(property="start_at", type="string", format="time", example="10:00"),
     *                    @OA\Property(property="end_at", type="string", format="time", example="22:00"),
     *                    @OA\Property(property="is_weekend", type="boolean", example=true),
     *                    @OA\Property(property="time_slots", type="array",
     *                        @OA\Items(
     *                            @OA\Property(property="start_at", type="string", format="time", example="10:00"),
     *                            @OA\Property(property="end_at", type="string", format="time", example="11:00")
     *                        )
     *                    )
     *                )
     *            ),


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

    public function createUserWithBusiness(Request $request)
    {

        return   DB::transaction(function () use (&$request) {
            $validator = Validator::make($request->all(), [
                'email' => 'email|required|unique:users,email',
                'password' => 'required|string|min:6',
                'first_Name' => 'required',
                'last_Name' => 'required',
                'phone' => 'nullable',

                'business_name' => 'required',
                'business_address' => 'required|string',
                'business_postcode' => 'required',
                'business_enable_question' => 'required',
                'business_EmailAddress' => 'nullable',
                'business_GoogleMapApi' => 'nullable',

                'business_homeText' => 'nullable',
                'business_AdditionalInformation' => 'nullable',
                'business_Webpage' => 'nullable',
                'business_PhoneNumber' => 'nullable',
                'business_About' => 'nullable',
                'business_Layout' => 'nullable',
                "Is_guest_user" => "nullable",
                "is_review_silder" => "nullable",
                "review_only" => "nullable",

                'review_type' => 'nullable',
                "google_map_iframe" => "nullable",
                "show_image" => "nullable",




                "header_image" => "nullable",
                "rating_page_image" => "nullable",
                "placeholder_image" => "nullable",


                "primary_color" => "nullable",
                "secondary_color" => "nullable",
                "client_primary_color" => "nullable",
                "client_secondary_color" => "nullable",
                "client_tertiary_color" => "nullable",
                "user_review_report" => "nullable",
                "guest_user_review_report" => "nullable",
                'times' => 'required|array',
                'times.*.day' => 'required|numeric',
                'times.*.is_weekend' => 'required|boolean',
                'times.*.time_slots' => 'required|array',
                'times.*.time_slots.*.start_at' => [
                    'nullable',
                    'date_format:H:i',
                    function ($attribute, $value, $fail) {
                        $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
                        $isWeekend = request('times')[$index]['is_weekend'] ?? false;

                        if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
                            $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
                        }
                    },
                ],
                'times.*.time_slots.*.end_at' => [
                    'nullable',
                    'date_format:H:i',
                    function ($attribute, $value, $fail) {
                        $index = explode('.', $attribute)[1]; // Extract the index from the attribute name
                        $isWeekend = request('times')[$index]['is_weekend'] ?? false;

                        if (request('type') === 'scheduled' && $isWeekend == 0 && empty($value)) {
                            $fail("The $attribute field is required when type is scheduled and is_weekend is 0.");
                        }
                    },
                ],


            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            $validatedData = $validator->validated();

            $validatedData['password'] = Hash::make($validatedData['password']);
            $validatedData['type'] = "business_Owner";

            $validatedData['remember_token'] = Str::random(10);
            $user =  User::create($validatedData);
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;
            $data["user"] = $user;
            // email_verify_token
            if (env("SEND_EMAIL") == "TRUE") {
                $email_token = Str::random(30);
                $user->email_verify_token = $email_token;
                $user->email_verify_token_expires = Carbon::now()->subDays(-1);
                $user->save();
                Mail::to($validatedData["email"])->send(new NotifyMail($user));
            }

            $validatedData = $validator->validated();

            $business =  Business::create([
                "OwnerID" =>  $user->id,
                "Status" => "Inactive",
                "Key_ID" => Str::random(10),
                "expiry_date" => Date('y:m:d', strtotime('+15 days')),
                "Name" => $validatedData["business_name"],
                'Address' => $validatedData["business_address"],
                'PostCode' => $validatedData["business_postcode"],
                'enable_question' => $validatedData["business_enable_question"],
                'GoogleMapApi' => !empty($validatedData["business_GoogleMapApi"]) ? $validatedData["business_GoogleMapApi"] : "",

                'homeText' => $validatedData["business_homeText"],
                'AdditionalInformation' => $validatedData["business_AdditionalInformation"],
                'Webpage' => !empty($validatedData["business_Webpage"]) ? $validatedData["business_Webpage"] : "",
                'PhoneNumber' => !empty($validatedData["business_PhoneNumber"]) ? $validatedData["business_PhoneNumber"] : "",
                'About' => $validatedData["business_About"],
                'Layout' => $validatedData["business_Layout"],
                'EmailAddress' => !empty($validatedData["business_EmailAddress"]) ? $validatedData["business_EmailAddress"] : "",





                'header_image' => !empty($validatedData["header_image"]) ? $validatedData["header_image"] : "/header_image/default.webp",

                'rating_page_image' => !empty($validatedData["rating_page_image"]) ? $validatedData["rating_page_image"] : "/rating_page_image/default.webp",

                'placeholder_image' => !empty($validatedData["placeholder_image"]) ? $validatedData["placeholder_image"] : "/placeholder_image/default.webp",











                'primary_color' => !empty($validatedData["primary_color"]) ? $validatedData["primary_color"] : "",

                'secondary_color' => !empty($validatedData["secondary_color"]) ? $validatedData["secondary_color"] : "",

                "client_primary_color" => !empty($validatedData["client_primary_color"]) ? $validatedData["client_primary_color"] : "#172c41",

                "client_secondary_color" => !empty($validatedData["client_secondary_color"]) ? $validatedData["client_secondary_color"] : "#ac8538",

                "client_tertiary_color" => !empty($validatedData["client_tertiary_color"]) ? $validatedData["client_tertiary_color"] : "#fffffff",

                "user_review_report" => !empty($validatedData["user_review_report"]) ? $validatedData["user_review_report"] : 0,

                "guest_user_review_report" => !empty($validatedData["guest_user_review_report"]) ? $validatedData["guest_user_review_report"] : 0,



                'review_type' => !empty($validatedData["review_type"]) ? $validatedData["review_type"] : "star",
                'google_map_iframe' => !empty($validatedData["google_map_iframe"]) ? $validatedData["google_map_iframe"] : "",

                'show_image' => !empty($validatedData["show_image"]) ? $validatedData["show_image"] : "",





                'Is_guest_user' => !empty($validatedData["Is_guest_user"]) ? $validatedData["Is_guest_user"] : false,
                'is_review_silder' => !empty($validatedData["is_review_silder"]) ? $validatedData["is_review_silder"] : false,
                'review_only' => !empty($validatedData["review_only"]) ? $validatedData["review_only"] : true,



            ]);

            // Delete existing BusinessDay records for the given business ID
            BusinessDay::where('business_id', $business->id)->delete();

            // Process the validated times array
            foreach ($validatedData['times'] as $business_day_data) {
                // Create a BusinessDay record
                $businessDay = BusinessDay::create([
                    'business_id' => $business->id,
                    'day' => $business_day_data['day'],
                    'is_weekend' => $business_day_data['is_weekend'],
                ]);

                // Create multiple BusinessTimeSlot records for the BusinessDay
                foreach ($business_day_data['time_slots'] as $time_slot) {
                    $businessDay->timeSlots()->create([
                        'start_at' => $time_slot['start_at'],
                        'end_at' => $time_slot['end_at'],
                    ]);
                }
            }


            $data["business"] = $business;



            if (!((int)$validatedData["business_enable_question"])) {
                DB::transaction(function () use ($request, $business) {

                    $defaultQuestions = Question::where([
                        "business_id" => NULL,
                        "is_default" => 1
                    ])->get();

                    foreach ($defaultQuestions as $defaultQuestion) {
                        $questionData = [
                            'question' => $defaultQuestion->question,
                            'business_id' => $business->id,
                            'is_active' => 0
                        ];
                        $question  = Question::create($questionData);

                        //    foreach(QusetionStar::where([
                        //     "question_id"  => $defaultQuestion->id
                        // ])->get() as $defaultQuestionStars) {

                        //         QusetionStar::create([
                        //         "question_id"=>$question->id,
                        //         "star_id" => $defaultQuestionStars->star->id
                        //              ]);


                        //              foreach(StarTag::where([
                        //                 "question_id"  => $defaultQuestion->id,
                        //                 "star_id" => $defaultQuestionStars->star->id
                        //             ])->get() as $defaultStarTag){
                        //             //     $tag = Tag::create([
                        //             //         'tag' => $defaultStarTag->tag->tag,
                        //             // 'business_id' => $business->id
                        //             //     ]);


                        //                 StarTag::create([
                        //                  "question_id"=>$question->id,
                        //                  "tag_id"=>$defaultStarTag->tag->id,
                        //                  "star_id" => $defaultQuestionStars->star->id
                        //                       ]);

                        //                 }
                        // }












                    }
                });
            }


            return response(["ok" => true, "message" => "You have successfully registered", "data" => $data, "token" => $token], 200);
        });
    }



    /**
     *
     * @OA\Post(
     *      path="/owner/user/check/email",
     *      operationId="checkEmail",
     *      tags={"owner"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to check user",
     *      description="This method is to check user",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="string",example="test@g.c"),
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


    public function checkEmail(Request $request)
    {
        $user = User::where([
            "email" => $request->email
        ])->first();
        if ($user) {
            return response()->json(["data" => true], 200);
        }
        return response()->json(["data" => false], 200);
    }

    // ##################################################
    // This method is to store guest user
    // ##################################################


    /**
     *
     * @OA\Post(
     *      path="/owner/guestuserregister",
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
     *            @OA\Property(property="first_Name", type="string", format="string",example="Rifat"),
     *               @OA\Property(property="phone", type="string", format="string",example="Rifat")

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






    public function createGuestUser(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'email|required|unique:users,email',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'type' => 'nullable',
        ]);

        $validatedData = $validator->validated();
        // password is not need
        // $validatedData['password'] = Hash::make($request['password']);

        $validatedData['remember_token'] = Str::random(10);
        $user =  User::create($validatedData);
        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $data["user"] = $user;
        return response(["ok" => true, "message" => "You have successfully registered", "data" => $data, "token" => $token], 200);
    }


    /**
     * This method is to store staff
     *
     * @OA\Post(
     *     path="/owner/{businessId}/staff",
     *     operationId="createStaffUser",
     *     tags={"owner"},
     *     security={{"bearerAuth": {}}},
     *     summary="This method is to store staff",
     *     description="This method is to store staff",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","first_Name"},
     *             @OA\Property(property="email", type="string", example="test@g.c"),
     *             @OA\Property(property="type", type="string", example="12345678"),
     *             @OA\Property(property="first_Name", type="string", example="Rifat"),
     *             @OA\Property(property="phone", type="string", example="01700000000"),
     *             @OA\Property(property="password", type="string", example="Rifat123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found",
     *         @OA\JsonContent()
     *     )
     * )
     */


    public function createStaffUser($businessId, Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'email|required|unique:users,email',
            'first_Name' => 'required',
            'phone' => 'nullable',
            'type' => 'nullable',
            'password' => 'string|nullable',
        ]);

        $validatedData = $validator->validated();

        if (array_key_exists('password', $validatedData)) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        }

        $validatedData['remember_token'] = Str::random(10);
        $user =  User::create($validatedData);
        // $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $data["user"] = $user;



        // @@@@@@@@@@@@@@@@@@@@@@@@

        // insert into res_link (user_id,businessid)





        return response([
            "ok" => true,
            "message" => "Staff Added Successfully",
            "data" => $data,
            // "token" => $token
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






    public function updatePin($id, Request $request)
    {
        $validator = Validator::make($request->all(), [

            'pin' => 'required',

        ]);

        $validatedData = $validator->validated();
        User::where(["id" => $id])
            ->update([
                "pin" => $validatedData["pin"]
            ]);
        return response(["ok" => true, "message" => "Pin Updated Successfully."], 200);
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
        $data["user"] =   User::where(["id" => $id])->first();
        $data["ok"] = true;

        if (!$data["user"]) {
            return response(["message" => "No User Found"], 404);
        }
        return response($data, 200);
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
        $data["user"] =      User::whereNotIn('id', $userIdsToExclude)->get();
        $data["ok"] = true;

        // foreach($data["user"] as $deletableUser) {
        //     $deletableUser->delete();
        // }
        return response($data, 200);
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
        $data["user"] =   User::where(["phone" => $phoneNumber])->first();
        $data["ok"] = true;
        if (!$data["user"]) {
            return response(["message" => "No User Found"], 404);
        }
        return response($data, 200);
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



        $data["user"] =    tap(User::where(["id" => $request->id]))->update(
            $updatableData
        )
            // ->with("somthing")

            ->first();


        if (!$data["user"]) {
            return response()->json(["message" => "No User Found"], 404);
        }


        $data["message"] = "user updates successfully";
        return response()->json($data, 200);
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



        $data["user"] =    tap(User::where(["id" => $request->user()->id]))->update(
            $updatableData
        )
            // ->with("somthing")

            ->first();


        if (!$data["user"]) {
            return response()->json(["message" => "No User Found"], 404);
        }


        $data["message"] = "user updates successfully";
        return response()->json($data, 200);
    }
































    // ##################################################
    // This method is to update  user image
    // ##################################################

    /**
     *
     * @OA\Post(
     *      path="/owner/profileimage",
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
    public function updateImage(Request $request)
    {
        $request->validate([

            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

        ]);



        $imageName = time() . '.' . $request->logo->extension();



        $request->logo->move(public_path('img/user'), $imageName);

        $imageName = "img/user/" . $imageName;

        $data["user"] =    User::when(
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
        if (!$data["user"]) {
            return response()->json(["message" => "No User Found"], 404);
        }

        $data["user"]->image = $imageName;

        $data["user"]->save();




        $data["message"] = "image updates successfully";
        return response()->json($data, 200);
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

    public function createHeaderImage($business_id, ImageUploadRequest $request)
    {
        try {


            $insertableData = $request->validated();

            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json(["message" => "This is not your business", 401]);
            }

            $location =  "header_image";

            $new_file_name = time() . '_' . $insertableData["image"]->getClientOriginalName();

            $insertableData["image"]->move(public_path($location), $new_file_name);

            $business =   tap(Business::where(["id" => $business_id]))->update([
                "header_image" => ("/" . $location . "/" . $new_file_name)
            ])
                // ->with("somthing")

                ->first();
            return response()->json([
                "business" => $business,

            ], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return response()->json(["message" => $e->getMessage()], 500);
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

    public function createPlaceholderImage($business_id, ImageUploadRequest $request)
    {
        try {

            $insertableData = $request->validated();

            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json(["message" => "This is not your business", 401]);
            }

            $location =  "placeholder_image";

            $new_file_name = time() . '_' . $insertableData["image"]->getClientOriginalName();

            $insertableData["image"]->move(public_path($location), $new_file_name);

            $business =   tap(Business::where(["id" => $business_id]))->update([
                "placeholder_image" => ("/" . $location . "/" . $new_file_name)
            ])
                // ->with("somthing")

                ->first();
            return response()->json([
                "business" => $business,

            ], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return response()->json(["message" => $e->getMessage()], 500);
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

            $insertableData = $request->validated();

            $checkBusiness =    Business::where(["id" => $business_id])->first();

            if ($checkBusiness->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
                return response()->json(["message" => "This is not your business", 401]);
            }

            $location =  "rating_page_image";

            $new_file_name = time() . '_' . $insertableData["image"]->getClientOriginalName();

            $insertableData["image"]->move(public_path($location), $new_file_name);

            $business =   tap(Business::where(["id" => $business_id]))->update([
                "rating_page_image" => ("/" . $location . "/" . $new_file_name)
            ])
                // ->with("somthing")

                ->first();
            return response()->json([
                "business" => $business,

            ], 200);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }
}
