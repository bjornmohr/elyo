<?php

namespace Tests\Feature\Privacy;

use App\Models\Health\HealthSubject;
use App\Models\Privacy\SubjectMapping;
use App\Services\Privacy\AuditActorContext;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\Exceptions\InvalidPurposeCodeException;
use App\Services\Privacy\Exceptions\MappingNotFoundException;
use App\Services\Privacy\Exceptions\MappingRevokedException;
use App\Services\Privacy\Exceptions\OperationNotAvailableException;
use App\Services\Privacy\MappingCryptography;
use App\Services\Privacy\MappingOperation;
use App\Services\Privacy\MappingService;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use App\Services\Privacy\SubjectProvisioningState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Uid\Ulid;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class MappingServiceTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('privacy-feature-test');
    }

    public function test_container_resolves_mapping_service_with_dedicated_keys(): void
    {
        $this->assertInstanceOf(MappingService::class, app(MappingServiceContract::class));
    }

    public function test_container_keeps_subject_derivation_separate_from_lookup_hmac(): void
    {
        $original = app(MappingCryptography::class)->healthSubjectIdForUserId(7000);

        config()->set('privacy.mapping.hmac_key', 'rotated-lookup-hmac-key');
        app()->forgetInstance(MappingCryptography::class);

        $afterLookupRotation = app(MappingCryptography::class)->healthSubjectIdForUserId(7000);

        $this->assertSame($original, $afterLookupRotation);
    }

    public function test_provisioning_is_idempotent_and_persists_only_an_encrypted_user_id(): void
    {
        $service = app(MappingServiceContract::class);

        $firstSubjectId = $service->provisionOwnSubject(7001, PurposeCode::PROVISIONING);
        $secondSubjectId = $service->provisionOwnSubject(7001, PurposeCode::PROVISIONING);

        $this->assertSame($firstSubjectId, $secondSubjectId);
        $this->assertTrue(Ulid::isValid($firstSubjectId));
        $this->assertSame(1, HealthSubject::query()->count());
        $this->assertSame(1, SubjectMapping::query()->count());

        $mapping = DB::connection('mapping')->table('subject_mappings')->first();
        $this->assertNotNull($mapping);
        $this->assertSame('ACTIVE', $mapping->status);
        $this->assertSame($firstSubjectId, $mapping->health_subject_id);
        $this->assertNotSame('7001', $mapping->user_id_encrypted);
        $this->assertSame(
            7001,
            app(MappingCryptography::class)->decryptUserId($mapping->user_id_encrypted),
        );
        $this->assertFalse(DB::connection('mapping')->getSchemaBuilder()->hasColumn('subject_mappings', 'user_id'));
    }

    public function test_provisioning_state_distinguishes_missing_active_and_revoked_without_returning_identifiers(): void
    {
        $service = app(MappingServiceContract::class);

        $this->assertSame(
            SubjectProvisioningState::MISSING,
            $service->provisioningStateForUser(7010, PurposeCode::PROVISIONING),
        );

        $service->provisionOwnSubject(7010, PurposeCode::PROVISIONING);

        $this->assertSame(
            SubjectProvisioningState::ACTIVE,
            $service->provisioningStateForUser(7010, PurposeCode::PROVISIONING),
        );

        $service->revokeSubjectLink(7010, PurposeCode::REVOCATION);

        $this->assertSame(
            SubjectProvisioningState::REVOKED,
            $service->provisioningStateForUser(7010, PurposeCode::PROVISIONING),
        );
    }

    public function test_provisioning_state_requires_the_provisioning_purpose(): void
    {
        $this->expectException(InvalidPurposeCodeException::class);

        app(MappingServiceContract::class)->provisioningStateForUser(
            7011,
            PurposeCode::HEALTH_SELF_READ,
        );
    }

    public function test_retry_after_mapping_failure_adopts_the_orphan_health_subject(): void
    {
        $service = app(MappingServiceContract::class);
        $failMappingOnce = true;

        SubjectMapping::creating(function () use (&$failMappingOnce): void {
            if ($failMappingOnce) {
                $failMappingOnce = false;

                throw new RuntimeException('Simulated mapping write failure.');
            }
        });

        try {
            $service->provisionOwnSubject(7002, PurposeCode::PROVISIONING);
            $this->fail('The simulated mapping failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated mapping write failure.', $exception->getMessage());
        } finally {
            SubjectMapping::flushEventListeners();
        }

        $this->assertSame(1, HealthSubject::query()->count());
        $this->assertSame(0, SubjectMapping::query()->count());
        $orphanSubjectId = HealthSubject::query()->value('id');

        $adoptedSubjectId = $service->provisionOwnSubject(7002, PurposeCode::PROVISIONING);

        $this->assertSame($orphanSubjectId, $adoptedSubjectId);
        $this->assertSame(1, HealthSubject::query()->count());
        $this->assertSame(1, SubjectMapping::query()->count());
    }

    public function test_concurrent_mapping_insert_returns_the_winning_active_mapping(): void
    {
        config()->set('database.connections.mapping_competitor', config('database.connections.mapping'));
        DB::purge('mapping_competitor');

        $service = app(MappingServiceContract::class);
        $competitorHmac = null;

        SubjectMapping::creating(function (SubjectMapping $mapping) use (&$competitorHmac): void {
            $competitorHmac = $mapping->user_id_hmac;

            DB::connection('mapping_competitor')->table('subject_mappings')->insert([
                'user_id_hmac' => $mapping->user_id_hmac,
                'user_id_encrypted' => $mapping->user_id_encrypted,
                'health_subject_id' => $mapping->health_subject_id,
                'status' => $mapping->status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $subjectId = $service->provisionOwnSubject(7006, PurposeCode::PROVISIONING);

            $this->assertSame($subjectId, SubjectMapping::query()->value('health_subject_id'));
            $this->assertSame(1, SubjectMapping::query()->count());
            $this->assertSame(1, HealthSubject::query()->count());
        } finally {
            SubjectMapping::flushEventListeners();

            if ($competitorHmac !== null) {
                DB::connection('mapping_competitor')
                    ->table('subject_mappings')
                    ->where('user_id_hmac', $competitorHmac)
                    ->delete();
            }

            DB::disconnect('mapping_competitor');
        }
    }

    public function test_resolve_and_revoke_keep_a_final_tombstone(): void
    {
        $service = app(MappingServiceContract::class);
        $subjectId = $service->provisionOwnSubject(7003, PurposeCode::PROVISIONING);

        $this->assertSame($subjectId, $service->resolveOwnSubject(7003, PurposeCode::HEALTH_SELF_READ));
        $this->assertSame($subjectId, $service->resolveOwnSubject(7003, PurposeCode::HEALTH_SELF_WRITE));

        $service->revokeSubjectLink(7003, PurposeCode::REVOCATION);

        $mapping = SubjectMapping::query()->firstOrFail();
        $this->assertSame('REVOKED', $mapping->status);
        $this->assertNotNull($mapping->revoked_at);

        try {
            $service->resolveOwnSubject(7003, PurposeCode::HEALTH_SELF_READ);
            $this->fail('A revoked mapping must not resolve.');
        } catch (MappingRevokedException) {
            $this->assertTrue(true);
        }

        $this->expectException(MappingRevokedException::class);
        $service->provisionOwnSubject(7003, PurposeCode::PROVISIONING);
    }

    public function test_missing_and_revoked_mappings_raise_distinct_exceptions(): void
    {
        $service = app(MappingServiceContract::class);

        try {
            $service->resolveOwnSubject(7999, PurposeCode::HEALTH_SELF_READ);
            $this->fail('An absent mapping must not resolve.');
        } catch (MappingNotFoundException) {
            $this->assertTrue(true);
        }

        $service->provisionOwnSubject(7004, PurposeCode::PROVISIONING);
        $service->revokeSubjectLink(7004, PurposeCode::REVOCATION);

        $this->expectException(MappingRevokedException::class);
        $service->resolveOwnSubject(7004, PurposeCode::HEALTH_SELF_READ);
    }

    public function test_deferred_operations_throw_the_dedicated_guard_exception(): void
    {
        $service = app(MappingServiceContract::class);

        try {
            $service->resolveReportingCohort([7005], PurposeCode::REPORTING);
            $this->fail('Reporting cohort resolution must remain unavailable.');
        } catch (OperationNotAvailableException $exception) {
            $this->assertStringContainsString('ADR-003 D5', $exception->getMessage());
        }

        $this->expectException(OperationNotAvailableException::class);
        $this->expectExceptionMessage('ADR-003 D5');
        $service->resolveForDataSubjectRequest(7005, PurposeCode::DSR);
    }

    public function test_every_operation_emits_an_audit_contract_without_raw_identifiers(): void
    {
        $auditLogger = new class implements AuditLoggerContract
        {
            /** @var array<int, array<string, mixed>> */
            public array $events = [];

            public function logMappingOperation(
                MappingOperation $operation,
                PurposeCode $purpose,
                AuditActorContext $actorContext,
                string $subjectReference,
            ): void {
                $operation = $operation->value;
                $actorContext = $actorContext->toArray();
                $this->events[] = compact('operation', 'purpose', 'actorContext', 'subjectReference');
            }
        };

        app()->instance(AuditLoggerContract::class, $auditLogger);
        $service = app(MappingServiceContract::class);

        $subjectId = $service->provisionOwnSubject(87654321, PurposeCode::PROVISIONING);
        $service->provisioningStateForUser(87654321, PurposeCode::PROVISIONING);
        $service->resolveOwnSubject(87654321, PurposeCode::HEALTH_SELF_READ);
        $service->revokeSubjectLink(87654321, PurposeCode::REVOCATION);

        try {
            $service->resolveReportingCohort([87654321], PurposeCode::REPORTING);
        } catch (OperationNotAvailableException) {
        }

        try {
            $service->resolveForDataSubjectRequest(87654321, PurposeCode::DSR);
        } catch (OperationNotAvailableException) {
        }

        $this->assertCount(6, $auditLogger->events);
        $this->assertSame([
            'provisionOwnSubject',
            'provisionOwnSubject',
            'resolveOwnSubject',
            'revokeSubjectLink',
            'resolveReportingCohort',
            'resolveForDataSubjectRequest',
        ], array_column($auditLogger->events, 'operation'));
        $this->assertSame([
            ['type' => 'registration-workflow', 'runtime' => 'identity-api'],
            ['type' => 'registration-workflow', 'runtime' => 'identity-api'],
            ['type' => 'employee-self-service', 'runtime' => 'employee-health-api'],
            ['type' => 'privacy-admin', 'runtime' => 'privacy-admin'],
            ['type' => 'reporting-worker', 'runtime' => 'reporting-worker'],
            ['type' => 'privacy-admin', 'runtime' => 'privacy-admin'],
        ], array_column($auditLogger->events, 'actorContext'));

        foreach ($auditLogger->events as $event) {
            $payload = json_encode($event, JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString('87654321', $payload);
            $this->assertStringNotContainsString($subjectId, $payload);
            $this->assertNotSame('', $event['subjectReference']);
            $this->assertNotSame([], $event['actorContext']);
        }
    }
}
