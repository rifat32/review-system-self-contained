<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
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
     * Create notifications for multiple receivers
     *
     * @param array $receiverIds
     * @param array $data
     * @return array
     */
    public function createForMultipleReceivers(array $receiverIds, array $data): array
    {
        $notifications = [];

        foreach ($receiverIds as $receiverId) {
            $data['receiver_id'] = $receiverId;
            $notifications[] = $this->send_notification($data);
        }

        return $notifications;
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
            'status' => 'read',
            'read_at' => now(),
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
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    /**
     * Get unread notifications count for a user
     *
     * @param int $userId
     * @return int
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('receiver_id', $userId)
            ->where('status', 'unread')
            ->count();
    }

    /**
     * Get notifications for a user
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByUser(int $userId, int $limit = 20)
    {
        return Notification::where('receiver_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete old notifications
     *
     * @param int $days
     * @return int
     */
    public function deleteOldNotifications(int $days = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($days))
            ->where('status', 'read')
            ->delete();
    }
}
