<?php

namespace App\Http\Controllers;

use App\Mail\ForgetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{



        // ##################################################
        // This method is to store token
        // ##################################################

    /**
     *
     * @OA\Post(
     *      path="/v1.0/forgot-password",
     *      operationId="storeForgetPassword",
     *      tags={"forgot_password"},

     *      summary="This method is to store token",
     *      description="This method is to store token",

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






    public function storeForgetPassword(Request $request)
    {

        $user = User::where(["email" => $request->email])->first();
        if (!$user) {
            return response()->json(["message" => "no user found"], 404);
        }

        $token = Str::random(30);
        $user->resetPasswordToken = $token;
        $user->resetPasswordExpires = Carbon::now()->subDays(-1);
        $user->save();
        Mail::to($request->email)->send(new ForgetPasswordMail($user));

        // RETURN RESPONSE
        return response()->json([
            "message" => "please check email"
        ], 200);
    }
    // ##################################################
    // This method is to change password
    // ##################################################

    /**
     *
     * @OA\Patch(
     *      path="/auth/changepassword",
     *      operationId="changePassword",
     *      tags={"auth"},

     *      summary="This method is to change password",
     *      description="This method is to change password",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"password","cpassword"},
     *
     *     @OA\Property(property="password", type="string", format="string",* example="aaaaaaaa"),
     *     @OA\Property(property="password", type="string", format="string",* example="aaaaaaaa"),
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





    public function changePassword(Request $request)
    {

        $user = $request->user();
        if (!Hash::check($request->cpassword, $user->password)) {
            return response()->json([
                "message" => "Invalid password"
            ], 400);
        }
        $password = Hash::make($request->password);
        $user->password = $password;



        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();
        return response()->json([
            "message" => "password changed"
        ], 200);;
    }
    /**
     *
     * @OA\Patch(
     *      path="/superadmin/auth/changepassword",
     *      operationId="changePasswordBySuperAdmin",
     *      tags={"auth"},

     *      summary="This method is to change password by super admin",
     *      description="This method is to change password by super admin",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"password","cpassword"},
     *
     *     @OA\Property(property="password", type="string", format="string",* example="aaaaaaaa"),

     *    *     @OA\Property(property="user_id", type="number", format="number",* example="1"),
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





    public function changePasswordBySuperAdmin(Request $request)
    {

        $user = User::where([
            "id" => $request->user_id
        ])
            ->first();

        $password = Hash::make($request->password);
        $user->password = $password;



        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();
        return response()->json([
            "message" => "password changed"
        ], 200);;
    }


    /**
     *
     * @OA\Patch(
     *      path="/superadmin/auth/change-email",
     *      operationId="changeEmailBySuperAdmin",
     *      tags={"auth"},

     *      summary="This method is to change email by super admin",
     *      description="This method is to change email by super admin",
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"rifat@gmail.com","cpassword"},
     *
     *     @OA\Property(property="email", type="string", format="string",* example="rifat@gmail.com"),

     *    *     @OA\Property(property="user_id", type="number", format="number",* example="1"),
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





    public function changeEmailBySuperAdmin(Request $request)
    {

        $emailFound = User::where([
            "email" => $request->email
        ])->first();
        if ($emailFound) {
            return response()->json([
                "message" => "email already taken"
            ], 409);
        }



        $user = User::where([
            "id" => $request->user_id
        ])
            ->first();

        $user->update([
            "email" => $request->email
        ]);
        return response()->json([
            "message" => "email changed"
        ], 200);;
    }
    // ##################################################
    // This method is to change password by token
    // ##################################################


    /**
     *
     * @OA\Patch(
     *      path="/forgetpassword/reset/{token}",
     *      operationId="changePasswordByToken",
     *      tags={"forgot_password"},
     *  @OA\Parameter(
     * name="token",
     * in="path",
     * description="token",
     * required=true,
     * example="1"
     * ),
     *      summary="This method is to change password",
     *      description="This method is to change password",

     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"password"},
     *
     *     @OA\Property(property="password", type="string", format="string",* example="aaaaaaaa"),

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





    public function changePasswordByToken($token, Request $request)
    {

        $user = User::where([
            "resetPasswordToken" => $token,
        ])
            ->where("resetPasswordExpires", ">", now())
            ->first();
        if (!$user) {
            return response()->json([
                "message" => "Invalid Token Or Token Expired"
            ], 400);
        }

        $password = Hash::make($request->password);
        $user->password = $password;



        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();
        return response()->json([
            "message" => "password changed"
        ], 200);;
    }
}
