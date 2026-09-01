<?php

namespace App\Domain\Notification;

use App\Models\User;

/**
 * Critical transactional events cannot be disabled (§81).
 */
class NotificationService
{
    public const CRITICAL_EVENTS = [
        'payment.paid', 'payment.failed', 'order.status_changed', 'dispatch.offer',
        'order.completed', 'settlement.completed', 'withdrawal.status_changed',
        'refund.status_changed', 'dispute.opened', 'dispute.decision',
        'milestone.submitted', 'milestone.approved', 'security.suspicious_login',
        'field.arrived_otp',
    ];

    public function notify(User|int $user, string $event, string $title, ?string $body = null, array $data = [], array $channels = ['in_app']): void
    {
        $userModel = $user instanceof User ? $user : User::findOrFail($user);

        foreach ($channels as $channel) {
            if ($channel !== 'in_app' && $this->userOptedOut($userModel, $event, $channel)) {
                continue;
            }

            \DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'user_id' => $userModel->id,
                'event' => $event,
                'channel' => $channel,
                'title' => $title,
                'body' => $body,
                'data' => $data !== [] ? json_encode($data) : null,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function notifyMany(array $userIds, string $event, string $title, ?string $body = null, array $data = [], array $channels = ['in_app']): void
    {
        foreach ($userIds as $userId) {
            $this->notify($userId, $event, $title, $body, $data, $channels);
        }
    }

    private function userOptedOut(User $user, string $event, string $channel): bool
    {
        $prefs = $user->notification_preferences ?? [];

        return (bool) ($prefs[$event][$channel] ?? false);
    }
}
