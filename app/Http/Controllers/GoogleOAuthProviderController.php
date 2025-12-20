<?php

namespace App\Http\Controllers;

use App\Services\GoogleAuthService;
use Illuminate\Http\Request;
use Exception;

class GoogleOAuthProviderController extends Controller
{
    protected $googleAuthService;

    public function __construct(GoogleAuthService $googleAuthService)
    {
        $this->googleAuthService = $googleAuthService;
    }

    /**
     * Redirect to Google OAuth provider
     */
    public function redirectToGoogleAuth()
    {
        try {
            $authUrl = $this->googleAuthService->getAuthUrl();
            
            // For API, return the URL
            // Frontend will redirect to this URL
            return response()->json([
                'success' => true,
                'auth_url' => $authUrl,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate authorization URL',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     * Handles both registered and unregistered users
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $code = $request->input('code');

            if (!$code) {
                return $this->redirectToFrontend([
                    'error' => 'Authorization code not provided'
                ]);
            }

            // Handle callback using service
            $result = $this->googleAuthService->handleCallback($code);

            // Redirect to frontend with token
            return $this->redirectToFrontend([
                'token' => $result['token'],
                'is_new' => $result['is_new_user'] ? 'true' : 'false',
            ]);

        } catch (Exception $e) {
            return $this->redirectToFrontend([
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Redirect to frontend with parameters
     */
    protected function redirectToFrontend(array $params)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $queryString = http_build_query($params);
        
        return redirect("{$frontendUrl}/auth/callback?{$queryString}");
    }
}
