<?php

namespace App\Http\Controllers;

use App\Models\GoogleBusinessAccount;
use App\Models\GoogleBusinessLocation;
use App\Models\GoogleBusinessReview;
use App\Services\GoogleBusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class GoogleBusinessController extends Controller
{
    protected $googleBusinessService;

    public function __construct(GoogleBusinessService $googleBusinessService)
    {
        $this->googleBusinessService = $googleBusinessService;
    }

    /**
     * Redirect to Google OAuth consent screen
     */
    public function redirectToGoogle()
    {
        try {
            $authUrl = $this->googleBusinessService->getAuthUrl();
            
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
     * Handle OAuth callback from Google
     */
    public function handleCallback(Request $request)
    {
        try {
            $code = $request->input('code');
            $error = $request->input('error');

            // Handle OAuth error
            if ($error) {
                return $this->showErrorPage('OAuth Error', $error);
            }

            if (!$code) {
                return $this->showErrorPage('Missing Code', 'Authorization code not provided');
            }

            // Get authenticated user ID
            // For testing: use user_id from query param or default to user ID 1
            $userId = Auth::id() ?? $request->input('user_id') ?? 1;

            // Process the OAuth callback
            $account = $this->googleBusinessService->handleCallback($code, $userId);

            // Return success page with account details
            return $this->showSuccessPage($account);

        } catch (Exception $e) {
            return $this->showErrorPage('Connection Failed', $e->getMessage());
        }
    }

    /**
     * Show success page after OAuth connection
     */
    private function showSuccessPage($account)
    {
        $locationsCount = $account->locations->count();
        
        return response()->view('google-business.success', [
            'account' => $account,
            'locationsCount' => $locationsCount,
        ])->header('Content-Type', 'text/html');
    }

    /**
     * Show error page if OAuth fails
     */
    private function showErrorPage($title, $message)
    {
        return response()->view('google-business.error', [
            'title' => $title,
            'message' => $message,
        ])->header('Content-Type', 'text/html');
    }

    /**
     * Get all connected Google Business accounts for authenticated user
     */
    public function getAccounts(Request $request)
    {
        try {
            // For testing: use authenticated user or default to user ID 1
            $userId = Auth::id() ?? $request->input('user_id') ?? 1;

            $accounts = GoogleBusinessAccount::where('user_id', $userId)
                ->with('locations')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Accounts fetched successfully',
                'data' => $accounts,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disconnect a Google Business account
     */
    public function disconnectAccount($accountId)
    {
        try {
            // For testing: use authenticated user or default to user ID 1
            $userId = Auth::id() ?? 1;

            $account = GoogleBusinessAccount::where('id', $accountId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account disconnected successfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all locations for a specific account
     */
    public function getLocations($accountId)
    {
        try {
            $userId = Auth::id();

            $account = GoogleBusinessAccount::where('id', $accountId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $locations = $account->locations()->with('reviews')->get();

            return response()->json([
                'success' => true,
                'data' => $locations,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle review syncing for a location
     */
    public function toggleLocationSync($locationId)
    {
        try {
            $userId = Auth::id();

            $location = GoogleBusinessLocation::whereHas('account', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->findOrFail($locationId);

            $location->update([
                'is_active' => !$location->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location sync status updated',
                'data' => $location,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reviews for a specific location
     */
    public function getReviews($locationId, Request $request)
    {
        try {
            $userId = Auth::id();

            $location = GoogleBusinessLocation::whereHas('account', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->findOrFail($locationId);

            $query = $location->reviews();

            // Filter by rating if provided
            if ($request->has('rating')) {
                $query->byRating($request->input('rating'));
            }

            // Filter by date range
            if ($request->has('days')) {
                $query->recent($request->input('days'));
            }

            // Pagination
            $perPage = $request->input('per_page', 15);
            $reviews = $query->orderBy('review_created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $reviews,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually trigger review sync for a location
     */
    public function syncReviews($locationId)
    {
        try {
            $userId = Auth::id();

            $location = GoogleBusinessLocation::whereHas('account', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->findOrFail($locationId);

            $syncedCount = $this->googleBusinessService->syncReviews($location);

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$syncedCount} reviews",
                'data' => [
                    'synced_count' => $syncedCount,
                    'last_synced_at' => $location->fresh()->last_synced_at,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync reviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reply to a review
     */
    public function replyToReview(Request $request, $reviewId)
    {
        try {
            $request->validate([
                'reply' => 'required|string|max:4000',
            ]);

            $userId = Auth::id();

            $review = GoogleBusinessReview::whereHas('location.account', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->findOrFail($reviewId);

            $this->googleBusinessService->replyToReview($review, $request->input('reply'));

            return response()->json([
                'success' => true,
                'message' => 'Reply posted successfully',
                'data' => $review->fresh(),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to post reply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
