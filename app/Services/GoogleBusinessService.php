<?php

namespace App\Services;

use App\Models\GoogleBusinessAccount;
use App\Models\GoogleBusinessLocation;
use App\Models\GoogleBusinessReview;
use Google\Client as GoogleClient;
use Google\Service\MyBusiness;
use Google\Service\MyBusinessAccountManagement;
use Google\Service\MyBusinessBusinessInformation;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleBusinessService
{
    protected $client;

    /**
     * Initialize Google Client
     */
    public function __construct()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId(config('services.google_business.client_id'));
        $this->client->setClientSecret(config('services.google_business.client_secret'));
        $this->client->setRedirectUri(config('services.google_business.redirect'));
        $this->client->setScopes([
            'https://www.googleapis.com/auth/business.manage'
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        
        // TEMPORARY FIX: Disable SSL verification for local development
        // WARNING: Remove this in production!
        $this->client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false
        ]));
    }

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Handle OAuth callback and exchange code for tokens
     */
    public function handleCallback(string $code, int $userId): GoogleBusinessAccount
    {
        try {
            // Exchange authorization code for access token
            $token = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new Exception('Error fetching access token: ' . $token['error']);
            }

            $this->client->setAccessToken($token);

            // Fetch account information
            $accountManagement = new MyBusinessAccountManagement($this->client);
            $accounts = $accountManagement->accounts->listAccounts();

            if (empty($accounts->getAccounts())) {
                throw new Exception('No Google Business accounts found');
            }

            // Get the first account (user can have multiple, but we'll use the first one)
            $accountData = $accounts->getAccounts()[0];
            
            // Store account and tokens
            $account = $this->storeAccount($userId, $accountData, $token);

            // Fetch and store locations
            $this->fetchAndStoreLocations($account);

            return $account;

        } catch (Exception $e) {
            Log::error('Google Business OAuth callback error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store account information in database
     */
    protected function storeAccount(int $userId, $accountData, array $token): GoogleBusinessAccount
    {
        $accountId = $this->extractAccountId($accountData->getName());
        
        $expiresAt = isset($token['expires_in']) 
            ? now()->addSeconds($token['expires_in']) 
            : now()->addHour();

        return GoogleBusinessAccount::updateOrCreate(
            [
                'account_id' => $accountId,
                'user_id' => $userId,
            ],
            [
                'account_name' => $accountData->getAccountName() ?? 'Unknown',
                'type' => $accountData->getType() ?? 'PERSONAL',
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Fetch and store locations for an account
     */
    public function fetchAndStoreLocations(GoogleBusinessAccount $account): void
    {
        try {
            $this->ensureValidToken($account);
            
            $this->client->setAccessToken($account->getDecryptedAccessToken());
            $businessInfo = new MyBusinessBusinessInformation($this->client);

            $parent = "accounts/{$account->account_id}";
            $locations = $businessInfo->accounts_locations->listAccountsLocations($parent);

            foreach ($locations->getLocations() as $locationData) {
                $locationId = $this->extractLocationId($locationData->getName());

                GoogleBusinessLocation::updateOrCreate(
                    [
                        'location_id' => $locationId,
                        'google_business_account_id' => $account->id,
                    ],
                    [
                        'location_name' => $locationData->getTitle() ?? 'Unknown Location',
                        'address' => $this->formatAddress($locationData->getStorefrontAddress()),
                        'phone' => $locationData->getPhoneNumbers()->getPrimaryPhone() ?? null,
                        'website' => $locationData->getWebsiteUri() ?? null,
                    ]
                );
            }

        } catch (Exception $e) {
            Log::error('Error fetching locations: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch and sync reviews for a location
     */
    public function syncReviews(GoogleBusinessLocation $location): int
    {
        try {
            $account = $location->account;
            $this->ensureValidToken($account);

            $this->client->setAccessToken($account->getDecryptedAccessToken());

            // Use the MyBusiness service for reviews
            $parent = "accounts/{$account->account_id}/locations/{$location->location_id}";
            
            // Make HTTP request directly since the API structure varies
            $url = "https://mybusiness.googleapis.com/v4/{$parent}/reviews";
            $this->client->setUseBatch(false);
            
            $httpClient = $this->client->authorize();
            $response = $httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            $syncedCount = 0;

            if (isset($data['reviews']) && is_array($data['reviews'])) {
                foreach ($data['reviews'] as $reviewData) {
                    $this->storeReview($location, $reviewData);
                    $syncedCount++;
                }
            }

            $location->markAsSynced();

            return $syncedCount;

        } catch (Exception $e) {
            Log::error('Error syncing reviews for location ' . $location->id . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Store a single review
     */
    protected function storeReview(GoogleBusinessLocation $location, array $reviewData): void
    {
        $reviewId = $this->extractReviewId($reviewData['name'] ?? '');

        GoogleBusinessReview::updateOrCreate(
            [
                'review_id' => $reviewId,
                'google_business_location_id' => $location->id,
            ],
            [
                'reviewer_name' => $reviewData['reviewer']['displayName'] ?? 'Anonymous',
                'reviewer_photo_url' => $reviewData['reviewer']['profilePhotoUrl'] ?? null,
                'star_rating' => $reviewData['starRating'] ?? 'THREE',
                'comment' => $reviewData['comment'] ?? null,
                'review_reply' => $reviewData['reviewReply']['comment'] ?? null,
                'review_reply_updated_at' => isset($reviewData['reviewReply']['updateTime']) 
                    ? $reviewData['reviewReply']['updateTime'] 
                    : null,
                'review_created_at' => $reviewData['createTime'] ?? now(),
                'review_updated_at' => $reviewData['updateTime'] ?? now(),
            ]
        );
    }

    /**
     * Reply to a review
     */
    public function replyToReview(GoogleBusinessReview $review, string $replyText): void
    {
        try {
            $location = $review->location;
            $account = $location->account;
            $this->ensureValidToken($account);

            $this->client->setAccessToken($account->getDecryptedAccessToken());

            $parent = "accounts/{$account->account_id}/locations/{$location->location_id}/reviews/{$review->review_id}";
            $url = "https://mybusiness.googleapis.com/v4/{$parent}/reply";

            $httpClient = $this->client->authorize();
            $response = $httpClient->put($url, [
                'json' => [
                    'comment' => $replyText
                ]
            ]);

            // Update local record
            $review->update([
                'review_reply' => $replyText,
                'review_reply_updated_at' => now(),
            ]);

        } catch (Exception $e) {
            Log::error('Error replying to review: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure the access token is valid, refresh if needed
     */
    public function ensureValidToken(GoogleBusinessAccount $account): void
    {
        if (!$account->isTokenExpired()) {
            return;
        }

        $this->refreshToken($account);
    }

    /**
     * Refresh the access token
     */
    public function refreshToken(GoogleBusinessAccount $account): void
    {
        try {
            $refreshToken = $account->getDecryptedRefreshToken();

            if (!$refreshToken) {
                throw new Exception('No refresh token available');
            }

            $this->client->setAccessToken([
                'refresh_token' => $refreshToken
            ]);

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (isset($newToken['error'])) {
                throw new Exception('Error refreshing token: ' . $newToken['error']);
            }

            $expiresAt = isset($newToken['expires_in']) 
                ? now()->addSeconds($newToken['expires_in']) 
                : now()->addHour();

            $account->update([
                'access_token' => $newToken['access_token'],
                'token_expires_at' => $expiresAt,
            ]);

        } catch (Exception $e) {
            Log::error('Error refreshing token: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract account ID from resource name
     */
    protected function extractAccountId(string $name): string
    {
        // Format: accounts/{accountId}
        return str_replace('accounts/', '', $name);
    }

    /**
     * Extract location ID from resource name
     */
    protected function extractLocationId(string $name): string
    {
        // Format: accounts/{accountId}/locations/{locationId}
        $parts = explode('/', $name);
        return end($parts);
    }

    /**
     * Extract review ID from resource name
     */
    protected function extractReviewId(string $name): string
    {
        // Format: accounts/{accountId}/locations/{locationId}/reviews/{reviewId}
        $parts = explode('/', $name);
        return end($parts);
    }

    /**
     * Format address from Google's address object
     */
    protected function formatAddress($address): ?string
    {
        if (!$address) {
            return null;
        }

        $parts = array_filter([
            $address->getAddressLines() ? implode(', ', $address->getAddressLines()) : null,
            $address->getLocality(),
            $address->getAdministrativeArea(),
            $address->getPostalCode(),
        ]);

        return implode(', ', $parts);
    }
}
