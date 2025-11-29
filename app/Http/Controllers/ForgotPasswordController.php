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



    /**
     * @OA\Post(
     *     path="/v1.0/forgot-password",
     *     operationId="storeForgetPassword",
     *     tags={"forgot_password"},
     *     summary="This method is to store token",
     *     description="This method is to store token",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="test@g.c"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     )
     * )
     */


    public function storeForgetPassword(Request $request)
    {
        // VALIDATE REQUEST
        $request_payload = $request->validate([
            "email" => "required|email"
        ]);

        // GET USER BY EMAIL
        $user = User::where(["email" => $request_payload["email"]])->first();

        // IF USER NOT FOUND
        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "no user found"
            ], 404);
        }

        // CREATE TOKEN AND SAVE TO DB
        $token = Str::random(30);
        $user->resetPasswordToken = $token;
        $user->resetPasswordExpires = Carbon::now()->subDays(-1);
        $user->save();


        // SEND EMAIL
        Mail::to($request_payload["email"])->send(new ForgetPasswordMail($user));

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "please check email"
        ], 200);
    }


    /**
     * @OA\Patch(
     *     path="/v1.0/auth/change-password",
     *     operationId="changePassword",
     *     tags={"auth"},
     *     summary="This method is to change password",
     *     description="This method is to change password",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","confirm_password"},
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 example="OldOrNewPassword123"
     *             ),
     *             @OA\Property(
     *                 property="confirm_password",
     *                 type="string",
     *                 format="password",
     *                 example="OldOrNewPassword123"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     )
     * )
     */



    public function changePassword(Request $request)
    {

        // GET AUTHENTICATED USER
        $user = $request->user();

        // CHECK PASSWORD
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                "success" => false,
                "message" => "Invalid password"
            ], 400);
        }

        // UPDATE PASSWORD
        $password = Hash::make($request->password);
        $user->password = $password;

        // RESET LOGIN ATTEMPTS AND LAST FAILED LOGIN ATTEMPT TIME
        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "password changed"
        ], 200);
    }


    /**
     * @OA\Patch(
     *     path="/v1.0/auth/change-password-by-superadmin",
     *     operationId="changePasswordBySuperAdmin",
     *     tags={"auth"},
     *     summary="This method is to change password by super admin",
     *     description="This method is to change password by super admin",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password","confirm_password","user_id"},
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 example="aaaaaaaa"
     *             ),
     *             @OA\Property(
     *                 property="confirm_password",
     *                 type="string",
     *                 format="password",
     *                 example="aaaaaaaa"
     *             ),
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 format="int64",
     *                 example=1
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     )
     * )
     */



    public function changePasswordBySuperAdmin(Request $request)
    {

        $request_payload = $request->validate([
            "password" => "required|string|min:6",
            "user_id" => "required|integer"
        ]);

        // GET USER
        $user = User::find($request_payload["user_id"]);

        if (!$user) {
            return response()->json([
                "success" => false,
                "message" => "user not found"
            ], 404);;
        }

        // UPDATE PASSWORD AND SAVE TO DB
        $password = Hash::make($request_payload["password"]);
        $user->password = $password;


        // RESET LOGIN ATTEMPTS AND LAST FAILED LOGIN ATTEMPT TIME
        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "password changed"
        ], 200);
    }


    // ##################################################
    // This method is to change password by token
    // ##################################################


    /**
     * @OA\Patch(
     *     path="/v1.0/forget-password/reset/{token}",
     *     operationId="changePasswordByToken",
     *     tags={"forgot_password"},
     *     summary="This method is to change password",
     *     description="This method is to change password",
     *
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Reset token",
     *         required=true,
     *         @OA\Schema(type="string", example="reset-token-123")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password"},
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 example="aaaaaaaa"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent()
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     )
     * )
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
                "success" => false,
                "message" => "Invalid Token Or Token Expired"
            ], 400);
        }

        $password = Hash::make($request->password);
        $user->password = $password;



        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "password changed"
        ], 200);;
    }
}
