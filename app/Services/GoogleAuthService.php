<?php

namespace App\Services;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class GoogleAuthService
{
    /**
     * Get Google OAuth redirect URL
     */
    public function getAuthUrl(): string
    {
        return Socialite::driver('google')
            ->setHttpClient(new \GuzzleHttp\Client(['verify' => false])) // Disable SSL for local dev
            ->stateless()
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * Handle Google OAuth callback
     * Creates or finds user and returns user with token
     */
    public function handleCallback(string $code): array
    {
        try {
            // Get user info from Google
            $googleUser = Socialite::driver('google')
                ->setHttpClient(new \GuzzleHttp\Client(['verify' => false])) // Disable SSL for local dev
                ->stateless()
                ->user();

            // Check if user exists by google_id first
            $user = User::where('google_id', $googleUser->id)->first();

            if ($user) {
                // User already registered with Google OAuth
                $message = "Login successful";
                $isNewUser = false;
            } else {
                // Check if user exists by email
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    // User exists with this email but hasn't linked Google yet
                    // Link the Google account to existing user
                    $user->update([
                        'google_id' => $googleUser->id,
                    ]);
                    $message = "Google account linked successfully";
                    $isNewUser = false;
                } else {
                    // New user - create account
                    $user = $this->createUserFromGoogle($googleUser);
                    $message = "Account created successfully";
                    $isNewUser = true;
                }
            }

            // Generate authentication token
            $token = $user->createToken('google_auth_token')->accessToken;

            return [
                'success' => true,
                'message' => $message,
                'is_new_user' => $isNewUser,
                'user' => $user,
                'token' => $token,
            ];

        } catch (Exception $e) {
            throw new Exception('Google authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Create new user from Google data
     */
    protected function createUserFromGoogle($googleUser): User
    {
        // Split the name into first and last name
        $nameParts = explode(' ', $googleUser->name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        return User::create([
            'first_Name' => $firstName,
            'last_Name' => $lastName,
            'email' => $googleUser->email,
            'google_id' => $googleUser->id,
            'password' => Hash::make(Str::random(24)), // Random password for OAuth users
            'email_verified_at' => now(), // Google emails are verified
            'image' => $googleUser->avatar ?? null,
        ]);
    }

    /**
     * Get user by token
     */
    public function getUserByToken(string $token): ?User
    {
        $tokenModel = \Laravel\Passport\Token::where('id', $token)->first();
        
        if (!$tokenModel || $tokenModel->revoked) {
            return null;
        }

        return User::find($tokenModel->user_id);
    }
}
