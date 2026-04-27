<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a push notification to all subscriptions of a user.
     */
    public function sendToUser(User $user, array $payload): array
    {
        $subscriptions = $user->pushSubscriptions;
        $sent = 0;
        $failed = 0;

        foreach ($subscriptions as $sub) {
            try {
                // In a real scenario, use minishlink/web-push or similar
                // For now, we simulate the logic
                Log::info("Sending push to {$sub->endpoint}", $payload);
                $sent++;
            } catch (\Exception $e) {
                $failed++;
                Log::error("Push failed for {$sub->endpoint}: " . $e->getMessage());
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Save a new push subscription for a user.
     */
    public function saveSubscription(User $user, array $data): void
    {
        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $user->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
            ]
        );
    }
}
