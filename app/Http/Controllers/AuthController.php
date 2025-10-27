<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\EmailVerifyTokenRequest;
use App\Mail\VerifyMail;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{

      /**
        *
     * @OA\Post(
     *      path="/resend-email-verify-mail",
     *      operationId="resendEmailVerifyToken",
     *      tags={"auth"},

     *      summary="This method is to resend email verify mail",
     *      description="This method is to resend email verify mail",

     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="string",* example="test@g.c"),
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

    public function resendEmailVerifyToken(EmailVerifyTokenRequest $request) {

        try {

            return DB::transaction(function () use (&$request) {
                $insertableData = $request->validated();

            $user = User::where(["email" => $insertableData["email"]])->first();
            if (!$user) {
                return response()->json(["message" => "no user found"], 404);
            }



            $email_token = Str::random(30);
            $user->email_verify_token = $email_token;
            $user->email_verify_token_expires = Carbon::now()->subDays(-1);

                Mail::to($user->email)->send(new VerifyMail($user));


            $user->save();


            return response()->json([
                "message" => "please check email"
            ]);
            });




        } catch (Exception $e) {

            return response()->json([
                "message" => $e->getMessage()
            ],500);
        }

    }


     /**
     * @OA\Post(
     *      path="/auth/register",
     *      operationId="register",
     *      tags={"auth"},
     *      summary="This method is to register a user",
     *      description="This method is to register a user",
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email", "password"},
     *            @OA\Property(property="email", type="string", format="string", example="test@gmail.com"),
     *            @OA\Property(property="password", type="string", format="string", example="yourPassword"),
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
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function register(AuthRegisterRequest $request)
    {


        $request['password'] = Hash::make($request['password']);
        $request['remember_token'] = Str::random(10);
        $user =  User::create($request->toArray());
        $user->token = $user->createToken('Laravel Password Grant Client')->accessToken;

        return response(["message" => "You have successfully registered",  $user], 201);
    }
    // ##################################################
    // This method is to login a user
    // ##################################################
     /**
     * @OA\Post(
     *      path="/auth",
     *      operationId="login",
     *      tags={"auth"},
     *      summary="This method is to login a user",
     *      description="This method is to login a user",
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"email", "password"},
     *            @OA\Property(property="email", type="string", format="string", example="test@gmail.com"),
     *            @OA\Property(property="password", type="string", format="string", example="yourPassword"),
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
     *@OA\JsonContent()
     *      )
     *     )
     */
    public function login(Request $request)
    {
        $loginData = $request->validate([
            'email' => 'email|required',
            'password' => 'required'
        ]);
        $user = User::where('email', $loginData['email'])->first();

        if ($user && $user->login_attempts >= 5) {
            $now = Carbon::now();
            $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
            $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

            if ($diffInMinutes < 15) {
                return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
            } else {
                $user->login_attempts = 0;
                $user->last_failed_login_attempt_at = null;
                $user->save();
            }
        }
        if (!auth()->attempt($loginData)) {
            if ($user) {
                $user->login_attempts++;
                $user->last_failed_login_attempt_at = Carbon::now();
                $user->save();

                if ($user->login_attempts >= 5) {
                    $now = Carbon::now();
                    $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
                    $diffInMinutes = $now->diffInMinutes($lastFailedAttempt);

                    if ($diffInMinutes < 15) {
                        return response(['message' => 'You have 5 failed attempts. Reset your password or wait for 15 minutes to access your account.'], 403);
                    } else {
                        $user->login_attempts = 0;
                        $user->last_failed_login_attempt_at = null;
                        $user->save();
                    }
                }
            }

            return response(['message' => 'Invalid Credentials'], 401);
        }


        $user = auth()->user();


        $now = time(); // or your date as well
        $user_created_date = strtotime($user->created_at);
        $datediff = $now - $user_created_date;

                    if(!$user->email_verified_at && (($datediff / (60 * 60 * 24))>1)){
                        return response(['message' => 'please activate your email first'], 409);
                    }


        $user = User::with("business","roles")
        ->where([
            "id" => $user->id
        ])
        ->first();

        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;

        $user->save();

        $user->token  = auth()->user()->createToken('authToken')->accessToken;



        return response()->json($user, 200);
    }
    // ##################################################
    // This method is to check pin
    // ##################################################



       /**
        *
     * @OA\Post(
     *      path="/auth/checkpin/{id}",
     *      operationId="checkPin",
     *      tags={"auth"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to check pin",
     *      description="This method is to check pin",
     *         @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="user Id",
     *         required=true,
     *      ),
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"pin"},
     *            @OA\Property(property="pin", type="string", format="string",example="1234"),
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
    public function checkPin($id, Request $request)
    {
        $pinData =    $request->validate([
            'pin' => 'required'
        ]);
        $user =  User::where(["id" => $id])->first();
        if (!$user) {
            return response()->json([
                "message" => "No User Found"
            ], 400);
        }

        if (!Hash::check($pinData["pin"], $user->pin)) {
            return response()->json([
                "message" => "Invalid Pin"
            ], 400);
        }

        return response()->json([
            "message" => "Pin Matched"
        ], 200);
    }
    // ##################################################
    // This method is to get user with business
    // ##################################################


        /**
        *
     * @OA\Get(
     *      path="/auth",
     *      operationId="getUserWithRestaurent",
     *      tags={"auth"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="This method is to get user with business",
     *      description="This method is to get user with business",

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
    public function getUserWithRestaurent(Request $request)
    {
        // @@@@@@@@@@ should connect with restaurent
        $user = $request->user();
        $user = User::with("business")
        ->where([
            "id" => $user->id
        ])
        ->first();


        $user->token = auth()->user()->createToken('authToken')->accessToken;
        $user->permissions = $user->getAllPermissions()->pluck('name');
        $user->roles = $user->roles->pluck('name');

        return response()->json(
            $user,
            200
        );

    }
     // ##################################################
    // This method is to get user with business
    // ##################################################




    /**
     *
     * @OA\Get(
     *      path="/v1.0/user",
     *      operationId="getUser",
     *      tags={"auth"},
     *       security={
     *           {"bearerAuth": {}}
     *       },


     *      summary="This method is to get  user ",
     *      description="This method is to get user",
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


     public function getUser(Request $request)
     {
         try {
             $this->storeActivity($request, "DUMMY activity", "DUMMY description");

             $user = $request->user();

             $user->token = auth()->user()->createToken('authToken')->accessToken;
             $user->permissions = $user->getAllPermissions()->pluck('name');
             $user->roles = $user->roles->pluck('name');
             $user->business = $user->business;



             return response()->json(
                 $user,
                 200
             );
         } catch (Exception $e) {
             return $this->sendError($e, 500, $request);
         }
     }



        /**
        *
     * @OA\Get(
     *      path="/auth/users/{perPage}",
     *      operationId="getUsers",
     *      tags={"auth"},
    *       security={
     *           {"bearerAuth": {}}
     *       },
     *               @OA\Parameter(
     *         name="perPage",
     *         in="path",
     *         description="perPage",
     *         required=true,
     *  example="6"
     *      ),
     *   *               @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="search_key",
     *         required=true,
     *  example="6"
     *      ),
     *
     *      summary="This method is to get users",
     *      description="This method is to get users",

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
    public function getUsers($perPage,Request $request)
    {
        // @@@@@@@@@@ should connect with restaurent

        $userQuery = User::with("business");
        if(!empty($request->search_key)) {
            $userQuery = $userQuery->where(function($query) use ($request){
                $term = $request->search_key;
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
        $users = $userQuery->orderByDesc("id")->paginate($perPage);
        return response()->json($users, 200);
    }
}
