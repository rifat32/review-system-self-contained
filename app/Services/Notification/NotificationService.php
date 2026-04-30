<?php

namespace App\Services\Notification;

use App\Models\DeviceToken;
use App\Models\Notification;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Google_Client;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class NotificationService
{
    protected $client;
    protected $accessToken;

    public function __construct()
    {
        try {
            // CHECK IF FIREBASE CONFIG FILE EXISTS
            $firebaseConfigPath = storage_path('firebase/firebase.json');

            if (!file_exists($firebaseConfigPath)) {
                log_message([
                    'message' => 'Firebase credentials file not found',
                    'path' => $firebaseConfigPath
                ], 'firebaseConfig.log');
                $this->client = null;
                $this->accessToken = null;
                return;
            }

            // INITIALIZE GOOGLE CLIENT FOR FIREBASE
            $this->client = new Google_Client();
            $this->client->setAuthConfig($firebaseConfigPath);
            $this->client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $this->client->setSubject(config('services.firebase.client_email')); // optional if you need to specify subject
            $this->client->refreshTokenWithAssertion();
            $this->accessToken = $this->client->getAccessToken()['access_token'];
        } catch (Exception $e) {
            // GRACEFULLY HANDLE FIREBASE CREDENTIAL ERRORS
            // This prevents the entire app from crashing when Firebase credentials are invalid
            log_message([
                'message' => 'Failed to initialize Firebase/Google Client',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'firebaseConfig.log');

            $this->client = null;
            $this->accessToken = null;
        }
    }



    public function sendNotificationToDevice($deviceToken, $title, $body, $data = [])
    {
        try {
            // Validate required parameters
            if (empty($deviceToken)) {
                throw new BadRequestHttpException('Device token is required');
            }

            if (empty($title) && empty($body)) {
                throw new BadRequestHttpException('Either title or body is required');
            }

            $projectId = config('services.firebase.project_id');

            if (empty($projectId)) {
                throw new BadRequestHttpException('Firebase project ID is not configured');
            }

            if (empty($this->accessToken)) {
                throw new BadRequestHttpException('Access token is not available');
            }

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            // Convert all data values to strings
            $stringData = [];
            foreach ($data as $key => $value) {
                if ($value === null) {
                    $stringData[$key] = '';
                } else {
                    $stringData[$key] = (string) $value;
                }
            }

            $payload = [
                "message" => [
                    "token" => $deviceToken,
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                    "data" => $stringData,
                    "android" => [
                        "priority" => "HIGH"
                    ],
                    "apns" => [
                        "headers" => [
                            "apns-priority" => "10"
                        ]
                    ],
                    "webpush" => [
                        "headers" => [
                            "Urgency" => "high"
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, $payload);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();

                throw new BadRequestHttpException("FCM API Error (Status: {$statusCode}): " . $errorBody, $statusCode);
            }

            return [
                "success" => true,
                "debug" => [$url, substr($this->accessToken, 0, 20) . '...'], // Only log partial token for security
                "response" => $response->json()
            ];
        } catch (Exception $e) {
            // Log the error for debugging
            log_message([
                'message' => 'FCM Notification Failed',
                'device_token' => substr($deviceToken, 0, 20) . '...', // Partial token for security
                'title' => $title,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ], 'firebase.log');

            return [
                "success" => false,
                "error" => $e->getMessage(),
                "code" => $e->getCode()
            ];
        }
    }


    public function sendNotificationToFirebaseUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ) {
        $devices = DeviceToken::where('user_id', $userId)->get();
        $data['receiver_id'] = $userId;

        $responses = [];

        foreach ($devices as $device) {
            // ==================== ANDROID ONLY (FCM) ====================
            if ($device->device_type === 'android') {
                $responses[] = $this->sendNotificationToDevice(
                    $device->device_token,
                    $title,
                    $body,
                    $data
                );
            }
            // ==================== iOS TEMPORARILY DISABLED ====================
            elseif ($device->device_type === 'ios') {
                // TODO: iOS push notifications will be implemented later
                // For now, log that we're skipping iOS devices
                log_message([
                    'message' => 'iOS push notification skipped - APNs not configured yet',
                    'user_id' => $userId,
                    'device_token' => substr($device->device_token, 0, 20) . '...',
                ], 'firebase.log');

                $responses[] = [
                    'success' => false,
                    'error' => 'iOS push notifications not configured yet',
                    'device_type' => 'ios'
                ];
            }
        }

        return $responses;
    }




    /**
     * Create a new notification
     *
     * @param array $data
     * @return Notification
     */
    public function send_notification(array $data): Notification
    {
        $message = $data['message'] ?? null;

        if (empty($message)) {
            $message = $this->getMessageByType($data['type'] ?? '', $data);
        }

        return Notification::create([
            'receiver_id' => $data['receiver_id'],
            'business_id' => $data['business_id'] ?? null,
            'sender_type' => $data['sender_type'] ?? null,
            'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? $this->getTitleByType($data['type'] ?? ''),
            'message' => $message,
            'link' => $data['link'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'unread',
            'entity_id' => $data['entity_id'] ?? null,
            'entity_ids' => $data['entity_ids'] ?? null,
        ]);
    }

    /**
     * Get default message by notification type
     *
     * @param string $type
     * @param array $data
     * @return string
     */
    private function getMessageByType(string $type, array $data): string
    {
        return match ($type) {
            'new_review' => 'A new review has been submitted.',
            'low_rating_review' => 'A low rating review requires your attention.',
            'review_replied' => 'Your review has received a reply.',
            'staff_mentioned' => 'You have been mentioned in a review.',
            default => 'You have a new notification.',
        };
    }

    /**
     * Get default title by notification type
     *
     * @param string $type
     * @return string
     */
    private function getTitleByType(string $type): string
    {
        return match ($type) {
            'new_review' => 'New Review Received',
            'low_rating_review' => 'Low Rating Review Alert',
            'review_replied' => 'Review Reply',
            'staff_mentioned' => 'Staff Mentioned',
            default => 'Notification',
        };
    }



    /**
     * Mark notification as read
     *
     * @param Notification $notification
     * @return bool
     */
    public function markAsRead(Notification $notification): bool
    {
        return $notification->update([
            'read_at' => now(),
            'status' => 'read'
        ]);
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param int $userId
     * @return int
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('receiver_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'status' => 'read'
            ]);
    }
}
