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
     *     description="If user_id is omitted, the authenticated user's ID will be used.",
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
     *             ),
     *             @OA\Property(
     *                 property="user_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="Target user ID. If not provided, authenticated user is used."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Successful operation", @OA\JsonContent()),
     *     @OA\Response(response=400, description="Bad Request", @OA\JsonContent()),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent()),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent()),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent()),
     *     @OA\Response(response=422, description="Unprocessable Content", @OA\JsonContent())
     * )
     */
    public function changePassword(Request $request)
    {
        // Ensure request is authenticated
        $authUser = $request->user();
        if (!$authUser) {
            return response()->json([
                "success" => false,
                "message" => "Unauthenticated"
            ], 401);
        }

        $requestPayload = $request->validate([
            "password"          => "required|string|min:6",
            "confirm_password"  => "required|string|min:6|same:password",
            "user_id"           => "nullable|integer|exists:users,id"
        ]);

        // Determine target user id: provided user_id or fallback to authenticated user
        $targetUserId = $requestPayload["user_id"] ?? $authUser->id;

        // Fetch target user
        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            return response()->json([
                "success" => false,
                "message" => "user not found"
            ], 404);
        }

        // NOTE: Your existing logic checks the provided password against the stored hash.
        // Keeping that behavior intact:
        if (!Hash::check($request->password, $targetUser->password)) {
            return response()->json([
                "success" => false,
                "message" => "Invalid password"
            ], 400);
        }

        // Update password (re-hash provided value)
        $targetUser->password = Hash::make($request->password);

        // Reset login attempts metadata
        $targetUser->login_attempts = 0;
        $targetUser->last_failed_login_attempt_at = null;
        $targetUser->save();

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
