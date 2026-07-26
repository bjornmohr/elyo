<?php

namespace App\Services\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AccountDeletionService
{
    private const SUBJECT_TABLES = [
        'wellbeing_entries',
        'lab_marker_readings',
        'anamnesis_profiles',
        'health_documents',
        'user_documents',
        'wearable_connections',
        'wearable_syncs',
    ];

    public function __construct(
        private readonly MappingServiceContract $mappingService,
        private readonly AuditLoggerContract $auditLogger,
    ) {}

    /**
     * Execute an approved account deletion immediately.
     *
     * The 30-day grace-period workflow schedules this method outside this
     * service. Each physical delete is safe to repeat, while MappingService
     * keeps the ACTIVE mapping available until every preceding step succeeds.
     *
     * @return array{
     *     health_rows_deleted: int,
     *     health_files_deleted: int,
     *     identity_rows_deleted: int,
     *     mapping_revoked: int
     * }
     */
    public function deleteUser(int $userId): array
    {
        $summary = [
            'health_rows_deleted' => 0,
            'health_files_deleted' => 0,
            'identity_rows_deleted' => 0,
            'mapping_revoked' => 0,
        ];

        try {
            $revocationPrepared = false;

            $this->mappingService->revokeSubjectLink(
                $userId,
                PurposeCode::REVOCATION,
                function (string $healthSubjectId) use (&$revocationPrepared, &$summary, $userId): void {
                    $this->deleteHealthData($healthSubjectId, $summary);
                    $summary['identity_rows_deleted'] = $this->deleteIdentityData($userId);
                    $revocationPrepared = true;
                },
                function () use (&$summary): void {
                    $summary['mapping_revoked'] = 1;
                    $this->auditLogger->logAccountDeletion($summary, AuditOutcome::SUCCESS);
                },
            );

            if (! $revocationPrepared) {
                $this->requireCompletedDeletion($userId);
                $this->auditLogger->logAccountDeletion($summary, AuditOutcome::SUCCESS);
            }

            return $summary;
        } catch (Throwable $exception) {
            $summary['mapping_revoked'] = 0;
            $this->auditLogger->logAccountDeletion($summary, AuditOutcome::FAILED);

            throw $exception;
        }
    }

    /**
     * A revoked mapping means no Health Subject can be resolved any more, so
     * this path can only report a completed deletion. Today only deleteUser
     * revokes, and it deletes identity data first; a surviving user therefore
     * means the tombstone exists without the physical deletes. Reporting
     * success there would leave retained data behind a clean audit trail.
     */
    private function requireCompletedDeletion(int $userId): void
    {
        if (User::query()->find($userId) === null) {
            return;
        }

        throw new RuntimeException(
            'Account deletion cannot proceed: the subject mapping is already revoked while identity data remains.',
        );
    }

    /**
     * @param array{
     *     health_rows_deleted: int,
     *     health_files_deleted: int,
     *     identity_rows_deleted: int,
     *     mapping_revoked: int
     * } $summary
     */
    private function deleteHealthData(string $healthSubjectId, array &$summary): void
    {
        $health = DB::connection('health');
        $healthRows = $health->table('health_subjects')
            ->where('id', $healthSubjectId)
            ->count();

        foreach (self::SUBJECT_TABLES as $table) {
            $healthRows += $health->table($table)
                ->where('health_subject_id', $healthSubjectId)
                ->count();
        }

        $blobKeys = $health->table('user_documents')
            ->where('health_subject_id', $healthSubjectId)
            ->pluck('blob_key');

        foreach ($blobKeys as $blobKey) {
            if (! Storage::disk('public')->exists($blobKey)) {
                continue;
            }

            if (! Storage::disk('public')->delete($blobKey)) {
                throw new RuntimeException('Health document file deletion failed.');
            }

            $summary['health_files_deleted']++;
        }

        $health->table('health_subjects')->where('id', $healthSubjectId)->delete();
        $this->requireNoSubjectRowsRemain($healthSubjectId);
        $summary['health_rows_deleted'] += $healthRows;
    }

    /**
     * The counted rows disappear through the Health Subject foreign keys, so the
     * audited number is only trustworthy once every subject-keyed table is
     * confirmed empty. A future table without cascadeOnDelete fails here instead
     * of being reported as deleted.
     */
    private function requireNoSubjectRowsRemain(string $healthSubjectId): void
    {
        $health = DB::connection('health');

        foreach ([...self::SUBJECT_TABLES, 'health_subjects'] as $table) {
            $column = $table === 'health_subjects' ? 'id' : 'health_subject_id';

            if ($health->table($table)->where($column, $healthSubjectId)->exists()) {
                throw new RuntimeException(
                    "Health deletion left rows in [{$table}]; the subject cascade is incomplete.",
                );
            }
        }
    }

    private function deleteIdentityData(int $userId): int
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return 0;
        }

        $identity = DB::connection('identity');
        $deleted = $identity->table('sessions')
            ->where('user_id', $userId)
            ->delete();
        $deleted += $identity->table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->delete();
        $deleted += $identity->table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $userId)
            ->delete();
        $deleted += $identity->table('invite_tokens')
            ->where('email', $user->email)
            ->delete();

        $user->delete();

        return $deleted + 1;
    }
}
