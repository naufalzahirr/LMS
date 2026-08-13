<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationSummaryService
{
    private const LATEST_LIMIT = 8;

    /** @return array{unread_count: int, latest: array<int, array<string, mixed>>} */
    public function sharedPayload(User $user): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()
                ->latest()
                ->limit(self::LATEST_LIMIT)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => $this->mapForDisplay($notification))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function mapForDisplay(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? '',
            'message' => $notification->data['message'] ?? '',
            'action_label' => $notification->data['action_label'] ?? null,
            'action_url' => $notification->data['action_url'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at->toIso8601String(),
            'read_url' => route('notifications.read', $notification),
        ];
    }
}
