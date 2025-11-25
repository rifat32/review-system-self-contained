<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\CheckPinRequest;
use App\Http\Requests\EmailVerifyTokenRequest;
use App\Mail\VerifyMail;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Authentication constants
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME_MINUTES = 15;
    private const EMAIL_VERIFICATION_DAYS = 1;
    private const TOKEN_LENGTH = 30;
    private const REMEMBER_TOKEN_LENGTH = 10;

    /**
     * @OA\Post(
     *      path="/resend-email-verify-mail",
     *      operationId="resendEmailVerifyToken",
     *      tags={"auth"},
     *      summary="Resend email verification token",
     *      description="Resend email verification token to user's email address",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email"},
     *              @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Verification email sent successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Verification email has been sent. Please check your inbox.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - Email already verified",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Email is already verified")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="No user found with this email address")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to send verification email. Please try again later.")
     *          )
     *      )
     * )
     */

    public function resendEmailVerifyToken(EmailVerifyTokenRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $payload_data = $request->validated();

                $user = User::where('email', $payload_data['email'])->first();

                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No user found with this email address'
                    ], 404);
                }

                if ($user->email_verified_at) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email is already verified'
                    ], 400);
                }

                // Generate and set email verification token
                $user->email_verify_token = Str::random(self::TOKEN_LENGTH);
                $user->email_verify_token_expires = Carbon::now()->addDay();
                $user->save();

                // Send verification email
                Mail::to($user->email)->send(new VerifyMail($user));

                return response()->json([
                    'success' => true,
                    'message' => 'Verification email has been sent. Please check your inbox.'
                ], 200);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email. Please try again later.'
            ], 500);
        }
    }


    /**
     * @OA\Post(
     *      path="/auth/register",
     *      operationId="register",
     *      tags={"auth"},
     *      summary="Register a new user",
     *      description="Register a new user with email and password",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "password"},
     *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="securePassword123"),
     *              @OA\Property(property="name", type="string", example="John Doe"),
     *              @OA\Property(property="phone", type="string", example="+1234567890")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="User registered successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User registered successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Registration failed. Please try again.")
     *          )
     *      )
     * )
     */


    public function register(AuthRegisterRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $payload_data = $request->validated();

                // Hash password and set remember token
                $payload_data['password'] = Hash::make($payload_data['password']);
                $payload_data['remember_token'] = Str::random(self::REMEMBER_TOKEN_LENGTH);

                // Create user
                $user = User::create($payload_data);

                // Load relationships for consistent response
                $user = User::with(['business', 'roles'])->find($user->id);

                // Generate access token
                $user->token = $user->createToken('authToken')->accessToken;

                return response()->json([
                    'success' => true,
                    'message' => 'User registered successfully',
                    'data' => $user
                ], 201);
            });
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ], 500);
        }
    }



    /**
     * @OA\Post(
     *      path="/auth",
     *      operationId="login",
     *      tags={"auth"},
     *      summary="Authenticate user login",
     *      description="Authenticate user with email and password, returns access token on success",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email", "password"},
     *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="securePassword123")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Login successful",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Login successful"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Invalid credentials",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid email or password")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Account locked or email not verified",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Account locked due to 5 failed login attempts. Please reset your password or wait 15 minutes.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Login failed. Please try again.")
     *          )
     *      )
     * )
     */
    public function login(AuthLoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            $user = User::where('email', $credentials['email'])->first();

            // Check if account is locked due to failed attempts
            if ($user && $this->isAccountLocked($user)) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'Account locked due to %d failed login attempts. Please reset your password or wait %d minutes.',
                        self::MAX_LOGIN_ATTEMPTS,
                        self::LOCKOUT_TIME_MINUTES
                    )
                ], 403);
            }

            // Attempt authentication
            if (!Auth::attempt($credentials)) {
                $this->handleFailedLogin($user);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ], 401);
            }

            // Check email verification
            $user = Auth::user();
            if (!$this->isEmailVerified($user)) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before logging in'
                ], 403);
            }

            // Successful login - reset failed attempts and generate token
            return $this->handleSuccessfulLogin($user);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Check if account is locked due to failed login attempts
     */
    private function isAccountLocked(?User $user): bool
    {
        if (!$user || $user->login_attempts < self::MAX_LOGIN_ATTEMPTS) {
            return false;
        }

        $lastFailedAttempt = Carbon::parse($user->last_failed_login_attempt_at);
        $minutesSinceLastAttempt = Carbon::now()->diffInMinutes($lastFailedAttempt);

        if ($minutesSinceLastAttempt >= self::LOCKOUT_TIME_MINUTES) {
            // Reset failed attempts if lockout period has passed
            $user->update([
                'login_attempts' => 0,
                'last_failed_login_attempt_at' => null
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle failed login attempt
     */
    private function handleFailedLogin(?User $user): void
    {
        if (!$user) {
            return;
        }

        $user->increment('login_attempts');
        $user->last_failed_login_attempt_at = Carbon::now();
        $user->save();
    }

    /**
     * Check if email is verified and within required timeframe
     */
    private function isEmailVerified(User $user): bool
    {
        if ($user->email_verified_at) {
            return true;
        }

        $daysSinceCreation = Carbon::parse($user->created_at)->diffInDays(Carbon::now());
        return $daysSinceCreation <= self::EMAIL_VERIFICATION_DAYS;
    }

    /**
     * Handle successful login - reset attempts and generate token
     */
    private function handleSuccessfulLogin(User $user): JsonResponse
    {
        $user->update([
            'login_attempts' => 0,
            'last_failed_login_attempt_at' => null
        ]);

        $user = User::with('business', 'roles')->find($user->id);
        $user->token = $user->createToken('authToken')->accessToken;
        $user->permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $user
        ], 200);
    }





    // ##################################################
    // This method is to check pin
    // ##################################################



    /**
     * @OA\Post(
     *      path="/auth/check-pin/{id}",
     *      operationId="checkPin",
     *      tags={"auth"},
     *      security={{"bearerAuth": {}}},
     *      summary="Verify user PIN",
     *      description="Verify the PIN for a specific user (users can only verify their own PIN)",
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="User ID",
     *          required=true,
     *          @OA\Schema(type="integer", example=1)
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"pin"},
     *              @OA\Property(property="pin", type="string", format="password", example="1234")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="PIN verified successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="PIN verified successfully")
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - No PIN set",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="No PIN set for this user")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Invalid PIN",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid PIN")
     *          )
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Unauthorized access",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthorized to check this PIN")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="PIN verification failed")
     *          )
     *      )
     * )
     */
    public function checkPin($id, CheckPinRequest $request): JsonResponse
    {
        try {
            $pinData = $request->validated();

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Verify the user has permission to check their own PIN
            if ($request->user() && $request->user()->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to check this PIN'
                ], 403);
            }

            if (!$user->pin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No PIN set for this user'
                ], 400);
            }

            if (!Hash::check($pinData['pin'], $user->pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PIN'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'PIN verified successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'PIN verification failed'
            ], 500);
        }
    }
    // ##################################################
    // This method is to get user with business
    // ##################################################


    /**
     * @OA\Get(
     *      path="/auth",
     *      operationId="getUserWithRestaurant",
     *      tags={"auth"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get authenticated user with business details",
     *      description="Retrieve the currently authenticated user's information including business and roles",
     *      @OA\Response(
     *          response=200,
     *          description="User data retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to retrieve user data")
     *          )
     *      )
     * )
     */
    public function getUserWithRestaurant(Request $request): JsonResponse
    {
        try {
            $user = User::with('business', 'roles')
                ->find($request->user()->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->token = $user->createToken('authToken')->accessToken;
            $user->permissions = $user->getAllPermissions()->pluck('name');
            $user->roles = $user->roles->pluck('name');

            return response()->json([
                'success' => true,
                'data' => $user
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user data'
            ], 500);
        }
    }
    // ##################################################
    // This method is to get user with business
    // ##################################################




    /**
     * @OA\Get(
     *      path="/v1.0/user",
     *      operationId="getUser",
     *      tags={"auth"},
     *      security={{"bearerAuth": {}}},
     *      summary="Get authenticated user details",
     *      description="Retrieve the currently authenticated user's information including business, roles, and permissions",
     *      @OA\Response(
     *          response=200,
     *          description="User data retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="User data retrieved successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/User")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="User not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="User not found")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to retrieve user data")
     *          )
     *      )
     * )
     */


    public function getUser(Request $request): JsonResponse
    {
        try {
            $user = User::with('business', 'roles')
                ->find($request->user()->id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->token = $user->createToken('authToken')->accessToken;
            $user->permissions = $user->getAllPermissions()->pluck('name');
            $user->roles = $user->roles->pluck('name');

            return response()->json([
                'success' => true,
                'message' => 'User data retrieved successfully',
                'data' => $user
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user data'
            ], 500);
        }
    }



    /**
     *
     * @OA\Get(
     *      path="/auth/users",
     *      operationId="getUsers",
     *      tags={"auth"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of users per page (optional, if not provided returns all users)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100),
     *         example=10
     *      ),
     *      @OA\Parameter(
     *         name="search_key",
     *         in="query",
     *         description="Search term to filter users",
     *         required=false,
     *         @OA\Schema(type="string"),
     *         example="john"
     *      ),
     *      summary="Get users with optional pagination and search",
     *      description="Retrieve users with optional pagination and search functionality",
     *      @OA\Response(
     *          response=200,
     *          description="Users retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *              @OA\Property(property="data", type="object",
     *                  oneOf={
     *                      @OA\Schema(
     *                          type="array",
     *                          @OA\Items(ref="#/components/schemas/User")
     *                      ),
     *                      @OA\Schema(
     *                          type="object",
     *                          @OA\Property(property="current_page", type="integer"),
     *                          @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *                          @OA\Property(property="first_page_url", type="string"),
     *                          @OA\Property(property="from", type="integer"),
     *                          @OA\Property(property="last_page", type="integer"),
     *                          @OA\Property(property="last_page_url", type="string"),
     *                          @OA\Property(property="next_page_url", type="string"),
     *                          @OA\Property(property="path", type="string"),
     *                          @OA\Property(property="per_page", type="integer"),
     *                          @OA\Property(property="prev_page_url", type="string"),
     *                          @OA\Property(property="to", type="integer"),
     *                          @OA\Property(property="total", type="integer")
     *                      )
     *                  }
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad Request - Invalid per_page value",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Invalid per_page value. Must be between 1 and 100")
     *          )
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Internal Server Error",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Failed to retrieve users")
     *          )
     *      )
     * )
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {
            $query = User::with('business', 'roles')->filter();

            // Check if pagination is requested
            if ($request->filled('per_page')) {
                $perPage = (int) $request->per_page;

                // Validate per_page parameter
                if ($perPage < 1 || $perPage > 100) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid per_page value. Must be between 1 and 100'
                    ], 400);
                }

                $users = $query->orderByDesc('id')->paginate($perPage);
            } else {
                // Return all users without pagination
                $users = $query->orderByDesc('id')->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users'
            ], 500);
        }
    }
}
