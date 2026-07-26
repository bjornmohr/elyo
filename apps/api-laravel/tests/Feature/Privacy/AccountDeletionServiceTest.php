<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\SubjectMapping;
use App\Models\User;
use App\Services\Privacy\AccountDeletionService;
use App\Services\Privacy\AuditActorContext;
use App\Services\Privacy\AuditLoggerContract;
use App\Services\Privacy\AuditOutcome;
use App\Services\Privacy\DatabaseAuditLogger;
use App\Services\Privacy\MappingOperation;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionClass;
use RuntimeException;
use Tests\Support\ConfiguresPrivacyMapping;
use Tests\TestCase;

class AccountDeletionServiceTest extends TestCase
{
    use ConfiguresPrivacyMapping;

    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    private const SUBJECT_TABLES = [
        'wellbeing_entries',
        'lab_marker_readings',
        'anamnesis_profiles',
        'health_documents',
        'user_documents',
        'wearable_connections',
        'wearable_syncs',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('account-deletion-service-test');
        DB::connection('audit_migrator')->table('audit_events')->delete();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        DB::connection('audit_migrator')->table('audit_events')->delete();

        parent::tearDown();
    }

    public function test_delete_user_removes_only_the_users_health_and_identity_data_then_revokes_mapping(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $subjectId = $this->provisionSubject($user);
        $otherSubjectId = $this->provisionSubject($otherUser);
        $blobKey = $this->seedHealthRows($subjectId, 'target');
        $otherBlobKey = $this->seedHealthRows($otherSubjectId, 'other');
        $this->seedIdentityBehaviorRows($user);
        $this->seedIdentityBehaviorRows($otherUser);
        $this->seedNonCascadingIdentityRows($user, 'target');
        $this->seedNonCascadingIdentityRows($otherUser, 'other');

        $summary = app(AccountDeletionService::class)->deleteUser($user->id);

        $this->assertSame([
            'health_rows_deleted' => 8,
            'health_files_deleted' => 1,
            'identity_rows_deleted' => 5,
            'mapping_revoked' => 1,
        ], $summary);
        $this->assertNull(User::query()->find($user->id));
        $this->assertNotNull(User::query()->find($otherUser->id));
        $this->assertSame(0, DB::connection('health')->table('health_subjects')->where('id', $subjectId)->count());
        $this->assertSame(1, DB::connection('health')->table('health_subjects')->where('id', $otherSubjectId)->count());

        foreach (self::SUBJECT_TABLES as $table) {
            $this->assertSame(
                0,
                DB::connection('health')->table($table)->where('health_subject_id', $subjectId)->count(),
                "{$table} still contains target-subject rows.",
            );
            $this->assertSame(
                1,
                DB::connection('health')->table($table)->where('health_subject_id', $otherSubjectId)->count(),
                "{$table} rows belonging to another subject were changed.",
            );
        }

        Storage::disk('public')->assertMissing($blobKey);
        Storage::disk('public')->assertExists($otherBlobKey);
        $this->assertSame(
            SubjectMapping::STATUS_REVOKED,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );
        $this->assertSame(
            SubjectMapping::STATUS_ACTIVE,
            SubjectMapping::query()->where('health_subject_id', $otherSubjectId)->value('status'),
        );
        $this->assertSame(0, DB::connection('identity')->table('user_points')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::connection('identity')->table('point_transactions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::connection('identity')->table('user_points')->where('user_id', $otherUser->id)->count());
        $this->assertSame(1, DB::connection('identity')->table('point_transactions')->where('user_id', $otherUser->id)->count());
        $this->assertNonCascadingIdentityRows(0, $user);
        $this->assertNonCascadingIdentityRows(1, $otherUser);

        $event = DB::connection('audit_migrator')
            ->table('audit_events')
            ->where('event_type', 'account.deletion')
            ->sole();
        $actorContext = json_decode($event->actor_context, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('REVOCATION', $event->purpose);
        $this->assertSame('success', $event->outcome);
        $this->assertNull($event->subject_ref);
        $this->assertNull($event->user_ref);
        $this->assertSame(
            ['type', 'runtime', 'role', 'summary'],
            array_keys($actorContext),
        );
        $this->assertSame($summary, $actorContext['summary']);
        $this->assertStringNotContainsString($subjectId, $event->actor_context);

        $this->assertSame([
            'health_rows_deleted' => 0,
            'health_files_deleted' => 0,
            'identity_rows_deleted' => 0,
            'mapping_revoked' => 0,
        ], app(AccountDeletionService::class)->deleteUser($user->id));
        $this->assertSame(
            SubjectMapping::STATUS_REVOKED,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );
    }

    public function test_delete_user_resumes_after_health_deletion_when_identity_deletion_failed(): void
    {
        $user = User::factory()->create();
        $subjectId = $this->provisionSubject($user);
        $this->seedHealthRows($subjectId, 'retry');
        $failOnce = true;

        User::deleting(function () use (&$failOnce): void {
            if ($failOnce) {
                $failOnce = false;

                throw new RuntimeException('Simulated identity deletion failure.');
            }
        });

        try {
            app(AccountDeletionService::class)->deleteUser($user->id);
            $this->fail('The simulated identity deletion failure was not raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated identity deletion failure.', $exception->getMessage());
        } finally {
            User::flushEventListeners();
            User::clearBootedModels();
        }

        $this->assertSame(0, DB::connection('health')->table('health_subjects')->where('id', $subjectId)->count());
        $this->assertNotNull(User::query()->find($user->id));
        $this->assertSame(
            SubjectMapping::STATUS_ACTIVE,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );
        $this->assertSame(
            'failed',
            DB::connection('audit_migrator')
                ->table('audit_events')
                ->where('event_type', 'account.deletion')
                ->value('outcome'),
        );

        $summary = app(AccountDeletionService::class)->deleteUser($user->id);

        $this->assertSame([
            'health_rows_deleted' => 0,
            'health_files_deleted' => 0,
            'identity_rows_deleted' => 1,
            'mapping_revoked' => 1,
        ], $summary);
        $this->assertNull(User::query()->find($user->id));
        $this->assertSame(
            SubjectMapping::STATUS_REVOKED,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );
    }

    public function test_delete_user_keeps_mapping_active_when_summary_audit_fails_then_retry_completes(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;
        $subjectId = $this->provisionSubject($user);
        $this->seedHealthRows($subjectId, 'audit-retry');
        $databaseAuditLogger = new DatabaseAuditLogger;
        $failingSummaryLogger = new class($databaseAuditLogger) implements AuditLoggerContract
        {
            public function __construct(private readonly DatabaseAuditLogger $databaseAuditLogger) {}

            public function logMappingOperation(
                MappingOperation $operation,
                PurposeCode $purpose,
                AuditActorContext $actorContext,
                string $userReference,
                AuditOutcome $outcome,
            ): void {
                $this->databaseAuditLogger->logMappingOperation(
                    $operation,
                    $purpose,
                    $actorContext,
                    $userReference,
                    $outcome,
                );
            }

            public function logProvisioningBackfill(array $summary, AuditOutcome $outcome): void
            {
                $this->databaseAuditLogger->logProvisioningBackfill($summary, $outcome);
            }

            public function logAccountDeletion(array $summary, AuditOutcome $outcome): void
            {
                throw new RuntimeException('Simulated account deletion summary audit failure.');
            }
        };

        app()->instance(AuditLoggerContract::class, $failingSummaryLogger);

        try {
            app(AccountDeletionService::class)->deleteUser($userId);
            $this->fail('Deletion must fail when its summary cannot be audited.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Simulated account deletion summary audit failure.',
                $exception->getMessage(),
            );
        }

        $this->assertNull(User::query()->find($userId));
        $this->assertSame(0, DB::connection('health')->table('health_subjects')->where('id', $subjectId)->count());
        $this->assertSame(
            SubjectMapping::STATUS_ACTIVE,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );

        app()->instance(AuditLoggerContract::class, new DatabaseAuditLogger);

        $this->assertSame([
            'health_rows_deleted' => 0,
            'health_files_deleted' => 0,
            'identity_rows_deleted' => 0,
            'mapping_revoked' => 1,
        ], app(AccountDeletionService::class)->deleteUser($userId));
        $this->assertSame(
            SubjectMapping::STATUS_REVOKED,
            SubjectMapping::query()->where('health_subject_id', $subjectId)->value('status'),
        );
    }

    /**
     * The deletion counts and the post-delete emptiness guard both iterate this
     * list, so a new subject-keyed table that is not listed would silently fall
     * outside both.
     */
    public function test_every_subject_keyed_health_table_is_covered_by_the_deletion_service(): void
    {
        $subjectKeyedTables = DB::connection('health')
            ->table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'health_subject_id')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $covered = self::SUBJECT_TABLES;
        sort($covered);

        $this->assertSame($covered, $subjectKeyedTables);
        $this->assertSame(
            $covered,
            $this->deletionServiceSubjectTables(),
            'AccountDeletionService::SUBJECT_TABLES drifted from this test fixture.',
        );
    }

    public function test_delete_user_fails_loudly_when_the_mapping_was_revoked_without_deleting_data(): void
    {
        $user = User::factory()->create();
        $subjectId = $this->provisionSubject($user);
        $this->seedHealthRows($subjectId, 'orphan-tombstone');
        app(MappingServiceContract::class)->revokeSubjectLink($user->id, PurposeCode::REVOCATION);

        try {
            app(AccountDeletionService::class)->deleteUser($user->id);
            $this->fail('A revoked mapping with surviving identity data must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Account deletion cannot proceed: the subject mapping is already revoked while identity data remains.',
                $exception->getMessage(),
            );
        }

        $this->assertNotNull(User::query()->find($user->id));
        $this->assertSame(1, DB::connection('health')->table('health_subjects')->where('id', $subjectId)->count());
        $this->assertSame(
            'failed',
            DB::connection('audit_migrator')
                ->table('audit_events')
                ->where('event_type', 'account.deletion')
                ->value('outcome'),
        );
    }

    /**
     * @return list<string>
     */
    private function deletionServiceSubjectTables(): array
    {
        $tables = (new ReflectionClass(AccountDeletionService::class))
            ->getConstant('SUBJECT_TABLES');
        sort($tables);

        return $tables;
    }

    private function provisionSubject(User $user): string
    {
        return app(MappingServiceContract::class)->provisionOwnSubject(
            $user->id,
            PurposeCode::PROVISIONING,
        );
    }

    private function seedHealthRows(string $subjectId, string $suffix): string
    {
        $now = now();
        $markerKey = "marker_{$suffix}";
        $blobKey = "employee-documents/{$subjectId}/{$suffix}.pdf";
        $health = DB::connection('health');

        $health->table('lab_markers')->insert([
            'marker_key' => $markerKey,
            'name' => "Marker {$suffix}",
            'unit' => 'ng/ml',
            'low' => 10,
            'high' => 20,
            'marker_group' => 'sonstige',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('wellbeing_entries')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'mood' => 3,
            'stress' => 3,
            'energy' => 3,
            'score' => 3,
            'period_key' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('lab_marker_readings')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'marker_key' => $markerKey,
            'value' => 15,
            'measured_at' => $now->toDateString(),
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('anamnesis_profiles')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'completion_pct' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('health_documents')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'type' => 'laboratory',
            'file_name' => "{$suffix}.pdf",
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('user_documents')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'file_name' => "{$suffix}.pdf",
            'blob_url' => "/storage/{$blobKey}",
            'blob_key' => $blobKey,
            'mime_type' => 'application/pdf',
            'size' => 4,
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('wearable_connections')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'source' => "source_{$suffix}",
            'is_active' => true,
            'connected_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $health->table('wearable_syncs')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'source' => "source_{$suffix}",
            'date' => $now,
            'steps' => 1000,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Storage::disk('public')->put($blobKey, 'test');

        return $blobKey;
    }

    private function seedIdentityBehaviorRows(User $user): void
    {
        DB::connection('identity')->table('user_points')->insert([
            'user_id' => $user->id,
            'total' => 10,
            'level' => 'STARTER',
            'streak' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('identity')->table('point_transactions')->insert([
            'user_id' => $user->id,
            'points' => 10,
            'reason' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedNonCascadingIdentityRows(User $user, string $suffix): void
    {
        $identity = DB::connection('identity');
        $now = now();

        $identity->table('sessions')->insert([
            'id' => "session-{$suffix}",
            'user_id' => $user->id,
            'payload' => 'test',
            'last_activity' => $now->timestamp,
        ]);
        $identity->table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $identity->table('personal_access_tokens')->insert([
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test',
            'token' => hash('sha256', "token-{$suffix}"),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $identity->table('invite_tokens')->insert([
            'company_id' => $user->company_id,
            'email' => $user->email,
            'role' => 'employee',
            'token_hash' => hash('sha256', "invite-{$suffix}"),
            'status' => 'accepted',
            'expires_at' => $now->addDay(),
            'accepted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assertNonCascadingIdentityRows(int $expected, User $user): void
    {
        $identity = DB::connection('identity');

        $this->assertSame($expected, $identity->table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(
            $expected,
            $identity->table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->count(),
        );
        $this->assertSame(
            $expected,
            $identity->table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $user->id)
                ->count(),
        );
        $this->assertSame($expected, $identity->table('invite_tokens')->where('email', $user->email)->count());
    }
}
