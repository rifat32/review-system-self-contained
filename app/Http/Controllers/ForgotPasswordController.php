<?php

namespace App\Http\Controllers;

use App\Mail\ForgetPasswordMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        $user = User::where(["email" => $request_payload["email"]])->firstOrFail();


        // CREATE TOKEN AND SAVE TO DB
        $token = Str::random(30);
        DB::table('password_resets')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );


        // SEND EMAIL
        Mail::to($request_payload["email"])->send(new ForgetPasswordMail($user, $token));

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "please check email",
            "data" => [
                "email" => $user->email
            ]
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
        // Validate request
        $request->validate([
            'password' => 'required|string|min:6'
        ]);

        // Find the reset record
        $reset = DB::table('password_resets')
            ->where('created_at', '>', now()->subHours(1))
            ->get()
            ->first(function ($record) use ($token) {
                return Hash::check($token, $record->token);
            });

        if (!$reset) {
            throw new BadRequestHttpException('invalid token or token expired');
        }

        // Get user by email
        $user = User::where('email', $reset->email)->firstOrFail();

        $password = Hash::make($request->password);
        $user->password = $password;

        $user->login_attempts = 0;
        $user->last_failed_login_attempt_at = null;
        $user->save();

        // Delete the reset record
        DB::table('password_resets')->where('email', $reset->email)->delete();

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "password changed"
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
     *             required={"new_password"},
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 format="password",
     *                 nullable=true,
     *                 description="Required for non-superadmin users, optional for superadmin",
     *                 example="OldPassword123"
     *             ),
     *             @OA\Property(
     *                 property="new_password",
     *                 type="string",
     *                 format="password",
     *                 example="NewPassword123"
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

        // Conditional validation: current_password required only for non-superadmin
        $validationRules = [
            "new_password" => "required|string|min:6",
            "user_id" => "required|integer|exists:users,id"
        ];

        if (!$authUser->hasRole('superadmin')) {
            $validationRules["current_password"] = "required|string|min:6";
        } else {
            $validationRules["current_password"] = "nullable|string|min:6";
        }

        $requestPayload = $request->validate($validationRules);


        // Fetch target user
        $targetUser = User::find($requestPayload["user_id"]);
        if (!$targetUser) {
            return response()->json([
                "success" => false,
                "message" => "user not found"
            ], 404);
        }

        // NOTE: Skip current password check for superadmin, otherwise verify it
        if (!$authUser->hasRole('superadmin') && !Hash::check($request->current_password, $targetUser->password)) {
            return response()->json([
                "success" => false,
                "message" => "Invalid password"
            ], 400);
        }

        // Update password (re-hash provided value)
        $targetUser->password = Hash::make($request->new_password);

        // Reset login attempts metadata
        $targetUser->login_attempts = 0;
        $targetUser->last_failed_login_attempt_at = null;
        $targetUser->save();

        return response()->json([
            "success" => true,
            "message" => "password changed"
        ], 200);
    }
}
