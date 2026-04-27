<?php

namespace App\Services;

use App\Models\User;
use App\Models\WearableConnection;
use App\Models\WearableSync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WearableService
{
    /**
     * Handle Terra Webhook logic.
     */
    public function handleTerraWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $user = $payload['user'] ?? null;
        $terraUserId = $user['user_id'] ?? null;

        if ($type === 'auth') {
            $userId = $user['reference_id'] ?? null;
            if ($userId && $terraUserId) {
                WearableConnection::updateOrCreate(
                    ['user_id' => $userId, 'source' => 'terra'],
                    ['access_token' => $terraUserId, 'is_active' => true]
                );
            }
        } elseif ($type === 'deauth') {
            if ($terraUserId) {
                WearableConnection::where('access_token', $terraUserId)
                    ->where('source', 'terra')
                    ->update(['is_active' => false]);
            }
        } elseif (in_array($type, ['activity', 'sleep', 'body', 'daily'])) {
            // Process data payload
            $this->processTerraData($terraUserId, $type, $payload);
        }
    }

    protected function processTerraData(string $terraUserId, string $type, array $payload): void
    {
        $connection = WearableConnection::where('access_token', $terraUserId)
            ->where('source', 'terra')
            ->first();

        if (!$connection) return;

        // Simplified mapping logic
        if ($type === 'daily') {
            foreach ($payload['data'] ?? [] as $day) {
                $date = substr($day['metadata']['start_time'], 0, 10);
                WearableSync::updateOrCreate(
                    ['user_id' => $connection->user_id, 'source' => 'terra', 'date' => $date],
                    ['steps' => $day['distance_data']['steps'] ?? null]
                );
            }
        }
    }

    /**
     * Google Health Sync (Placeholder for OAuth logic).
     */
    public function syncGoogleHealth(User $user): void
    {
        // Implement OAuth token refresh and fitness API calls
        Log::info("Syncing Google Health for user: {$user->id}");
    }
}
