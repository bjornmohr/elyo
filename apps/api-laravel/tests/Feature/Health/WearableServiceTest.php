<?php

namespace Tests\Feature\Health;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Health\WearableConnection;
use App\Models\User;
use App\Services\Health\WearableService;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

/**
 * The wearable feature is dormant — no route reaches it — but its data moved
 * into the health domain with the rest (ELYO-91 prompt 08a, ADR-003 D8). These
 * tests pin the subject scoping of the ingestion path; no feature build-out.
 */
class WearableServiceTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    private User $employee;

    private WearableService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('wearable-service-test');
        $this->employee = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => Role::EMPLOYEE,
        ]);
        $this->service = app(WearableService::class);
    }

    public function test_terra_auth_webhook_stores_the_connection_on_the_health_subject(): void
    {
        $this->service->handleTerraWebhook([
            'type' => 'auth',
            'user' => ['user_id' => 'terra-user-1', 'reference_id' => (string) $this->employee->id],
        ]);

        $connection = WearableConnection::query()->sole();

        $this->assertSame($this->subjectIdFor($this->employee), $connection->health_subject_id);
        $this->assertTrue(Str::isUlid($connection->id), 'Connection ids must be opaque ULIDs.');
        $this->assertTrue($connection->is_active);
        $this->assertSame('terra', $connection->source);

        $row = (array) DB::connection('health')->table('wearable_connections')->first();
        $this->assertArrayNotHasKey('user_id', $row);
        $this->assertNotSame('terra-user-1', $row['access_token'], 'The provider token must be stored encrypted.');
    }

    public function test_repeated_terra_auth_webhooks_keep_one_connection_per_subject_and_source(): void
    {
        foreach (['terra-user-1', 'terra-user-2'] as $terraUserId) {
            $this->service->handleTerraWebhook([
                'type' => 'auth',
                'user' => ['user_id' => $terraUserId, 'reference_id' => (string) $this->employee->id],
            ]);
        }

        $this->assertSame(1, WearableConnection::query()->count());
        $this->assertSame('terra-user-2', WearableConnection::query()->sole()->access_token);
    }

    public function test_auth_webhook_without_a_reference_id_writes_nothing(): void
    {
        $this->service->handleTerraWebhook([
            'type' => 'auth',
            'user' => ['user_id' => 'terra-user-1'],
        ]);

        $this->assertSame(0, WearableConnection::query()->count());
    }

    private function subjectIdFor(User $user): string
    {
        return app(MappingServiceContract::class)->provisionOwnSubject(
            $user->id,
            PurposeCode::PROVISIONING,
        );
    }
}
