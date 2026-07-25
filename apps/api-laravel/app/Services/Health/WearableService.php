<?php

namespace App\Services\Health;

use App\Models\Health\WearableConnection;
use App\Models\Health\WearableSync;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\Log;

/**
 * Wearable ingestion in the health domain (ELYO-91 prompt 08a, ADR-003 D8).
 *
 * Dormant: no route, job or controller calls this service today. It moved from
 * `App\Services\WearableService` into the health domain because its two models
 * are now health models — an identity-side service may not reference them
 * (deptrac `HealthModels`). Minimal treatment per prompt 08a: subject
 * resolution and compile/test parity only, no feature build-out.
 */
class WearableService
{
    use ResolvesOwnSubject;

    public function __construct(private readonly MappingServiceContract $mappingService) {}

    protected function mappingService(): MappingServiceContract
    {
        return $this->mappingService;
    }

    /**
     * Handle Terra Webhook logic.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleTerraWebhook(array $payload): void
    {
        $type = $payload['type'] ?? null;
        $user = $payload['user'] ?? null;
        $terraUserId = $user['user_id'] ?? null;

        if ($type === 'auth') {
            $userId = $user['reference_id'] ?? null;
            if ($userId && $terraUserId) {
                // `reference_id` is the identity user id Terra was handed at
                // connect time; it is resolved to a subject here and never
                // stored in the health domain.
                $subjectId = $this->resolveSubjectId((int) $userId, PurposeCode::HEALTH_SELF_WRITE);

                WearableConnection::updateOrCreate(
                    ['health_subject_id' => $subjectId, 'source' => 'terra'],
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

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function processTerraData(string $terraUserId, string $type, array $payload): void
    {
        $connection = WearableConnection::where('access_token', $terraUserId)
            ->where('source', 'terra')
            ->first();

        if (! $connection) {
            return;
        }

        // Simplified mapping logic
        if ($type === 'daily') {
            foreach ($payload['data'] ?? [] as $day) {
                $date = substr($day['metadata']['start_time'], 0, 10);
                WearableSync::updateOrCreate(
                    ['health_subject_id' => $connection->health_subject_id, 'source' => 'terra', 'date' => $date],
                    ['steps' => $day['distance_data']['steps'] ?? null]
                );
            }
        }
    }

    /**
     * Google Health Sync (Placeholder for OAuth logic).
     *
     * Takes an identity user id instead of a `User` model: the health domain
     * must not reference identity models.
     */
    public function syncGoogleHealth(int $userId): void
    {
        // Implement OAuth token refresh and fitness API calls
        Log::info("Syncing Google Health for user: {$userId}");
    }
}
