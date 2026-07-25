<?php

namespace Tests\Feature\Privacy;

use App\Models\Health\HealthSubject;
use App\Models\Privacy\SubjectMapping;
use App\Models\User;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\DatabaseAuditLogger;
use App\Services\Privacy\Exceptions\MappingNotFoundException;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\NullAuditLogger;
use App\Services\Privacy\PurposeCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class DatabaseAuditLoggerTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('database-audit-logger-test');
        DB::connection('audit_migrator')->table('audit_events')->delete();
    }

    protected function tearDown(): void
    {
        DB::connection('audit_migrator')->table('audit_events')->delete();

        parent::tearDown();
    }

    public function test_container_uses_the_database_audit_logger(): void
    {
        $this->assertInstanceOf(DatabaseAuditLogger::class, app(AuditLoggerContract::class));
    }

    public function test_successful_resolve_creates_exactly_one_audit_event_without_plaintext_ids(): void
    {
        $service = app(MappingServiceContract::class);
        $subjectId = $service->provisionOwnSubject(8101, PurposeCode::PROVISIONING);

        $service->resolveOwnSubject(8101, PurposeCode::HEALTH_SELF_READ);

        $events = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'mapping.resolveOwnSubject')
            ->get();

        $this->assertCount(1, $events);

        $event = $events->first();
        $actorContext = json_decode($event->actor_context, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('HEALTH_SELF_READ', $event->purpose);
        $this->assertSame('success', $event->outcome);
        $this->assertSame([
            'type' => 'employee-self-service',
            'runtime' => 'employee-health-api',
            'role' => 'employee',
        ], $actorContext);
        $this->assertNull($event->subject_ref);
        $this->assertNotNull($event->user_ref);
        $this->assertSame(64, strlen($event->user_ref));
        $this->assertStringNotContainsString('8101', $event->user_ref);
        $this->assertStringNotContainsString($subjectId, $event->user_ref);
        $this->assertTrue(Str::isUlid($event->id));
        $this->assertTrue(Str::isUlid($event->correlation_id));
        $this->assertNotNull($event->occurred_at);
    }

    public function test_missing_resolve_creates_exactly_one_denied_event(): void
    {
        try {
            app(MappingServiceContract::class)->resolveOwnSubject(
                8102,
                PurposeCode::HEALTH_SELF_READ,
            );
            $this->fail('A missing mapping must not resolve.');
        } catch (MappingNotFoundException) {
        }

        $events = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'mapping.resolveOwnSubject')
            ->get();

        $this->assertCount(1, $events);
        $this->assertSame('denied', $events->first()->outcome);
    }

    public function test_database_rejects_an_event_containing_both_reference_types(): void
    {
        $this->expectException(QueryException::class);

        DB::connection('audit_migrator')->table('audit_events')->insert([
            'id' => (string) Str::ulid(),
            'event_type' => 'mapping.resolveOwnSubject',
            'purpose' => 'HEALTH_SELF_READ',
            'actor_context' => json_encode([
                'type' => 'employee-self-service',
                'runtime' => 'employee-health-api',
                'role' => 'employee',
            ], JSON_THROW_ON_ERROR),
            'subject_ref' => str_repeat('a', 64),
            'user_ref' => str_repeat('b', 64),
            'outcome' => 'success',
            'correlation_id' => (string) Str::ulid(),
            'occurred_at' => now(),
        ]);
    }

    public function test_audit_database_write_failure_blocks_a_resolve(): void
    {
        app()->instance(AuditLoggerContract::class, new NullAuditLogger);
        $service = app(MappingServiceContract::class);
        $service->provisionOwnSubject(8103, PurposeCode::PROVISIONING);

        app()->instance(AuditLoggerContract::class, new DatabaseAuditLogger);
        $service = app(MappingServiceContract::class);
        $originalAuditConfiguration = config('database.connections.audit');

        config()->set('database.connections.audit', config('database.connections.identity'));
        DB::purge('audit');

        try {
            $service->resolveOwnSubject(8103, PurposeCode::HEALTH_SELF_READ);
            $this->fail('Resolve must fail when its audit event cannot be written.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_events', $exception->getMessage());
        } finally {
            DB::purge('audit');
            config()->set('database.connections.audit', $originalAuditConfiguration);
        }
    }

    public function test_backfill_writes_one_run_summary_in_addition_to_per_subject_events(): void
    {
        User::factory()->create();

        $this->assertSame(0, Artisan::call('elyo:provision-subjects'));

        $summaryEvents = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'provisioning.backfill')
            ->get();

        $this->assertCount(1, $summaryEvents);

        $summary = $summaryEvents->first();
        $actorContext = json_decode($summary->actor_context, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('PROVISIONING', $summary->purpose);
        $this->assertSame('success', $summary->outcome);
        $this->assertNull($summary->subject_ref);
        $this->assertNull($summary->user_ref);
        $this->assertSame([
            'scanned' => 1,
            'missing' => 1,
            'active' => 0,
            'revoked' => 0,
            'provisioned' => 1,
            'failed' => 0,
            'dry_run' => false,
        ], $actorContext['summary']);
    }

    public function test_provisioning_state_has_its_own_event_type(): void
    {
        app(MappingServiceContract::class)->provisioningStateForUser(
            8104,
            PurposeCode::PROVISIONING,
        );

        $events = DB::connection('audit_migrator')->table('audit_events')->get();

        $this->assertCount(1, $events);
        $this->assertSame('mapping.provisioningStateForUser', $events->first()->event_type);
        $this->assertSame('success', $events->first()->outcome);
    }

    public function test_audit_write_failure_rolls_back_a_new_provision(): void
    {
        app()->instance(AuditLoggerContract::class, new DatabaseAuditLogger);
        $service = app(MappingServiceContract::class);
        $originalAuditConfiguration = config('database.connections.audit');

        config()->set('database.connections.audit', config('database.connections.identity'));
        DB::purge('audit');

        try {
            $service->provisionOwnSubject(8105, PurposeCode::PROVISIONING);
            $this->fail('Provision must fail when its audit event cannot be written.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_events', $exception->getMessage());
        } finally {
            DB::purge('audit');
            config()->set('database.connections.audit', $originalAuditConfiguration);
        }

        $this->assertSame(0, HealthSubject::query()->count());
        $this->assertSame(0, SubjectMapping::query()->count());
    }

    public function test_audit_write_failure_rolls_back_a_revocation(): void
    {
        app()->instance(AuditLoggerContract::class, new NullAuditLogger);
        app(MappingServiceContract::class)->provisionOwnSubject(8106, PurposeCode::PROVISIONING);

        app()->instance(AuditLoggerContract::class, new DatabaseAuditLogger);
        $service = app(MappingServiceContract::class);
        $originalAuditConfiguration = config('database.connections.audit');

        config()->set('database.connections.audit', config('database.connections.identity'));
        DB::purge('audit');

        try {
            $service->revokeSubjectLink(8106, PurposeCode::REVOCATION);
            $this->fail('Revocation must fail when its audit event cannot be written.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit_events', $exception->getMessage());
        } finally {
            DB::purge('audit');
            config()->set('database.connections.audit', $originalAuditConfiguration);
        }

        $mapping = SubjectMapping::query()->firstOrFail();
        $this->assertSame(SubjectMapping::STATUS_ACTIVE, $mapping->status);
        $this->assertNull($mapping->revoked_at);
    }
}
