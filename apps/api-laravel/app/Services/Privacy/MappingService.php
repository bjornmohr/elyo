<?php

namespace App\Services\Privacy;

use App\Models\Health\HealthSubject;
use App\Models\Privacy\SubjectMapping;
use App\Services\Privacy\Exceptions\InvalidPurposeCodeException;
use App\Services\Privacy\Exceptions\MappingNotFoundException;
use App\Services\Privacy\Exceptions\MappingRevokedException;
use App\Services\Privacy\Exceptions\OperationNotAvailableException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class MappingService implements MappingServiceContract
{
    public function __construct(
        private readonly MappingCryptography $cryptography,
        private readonly AuditLoggerContract $auditLogger,
    ) {}

    public function provisioningStateForUser(
        int $userId,
        PurposeCode $purpose,
    ): SubjectProvisioningState {
        return $this->performAuditedOperation(
            MappingOperation::PROVISIONING_STATE_FOR_USER,
            $purpose,
            AuditActorContext::registrationWorkflow(),
            $this->cryptography->auditSubjectReferenceForUserId($userId),
            function () use ($purpose, $userId): SubjectProvisioningState {
                $this->requirePurpose(
                    MappingOperation::PROVISIONING_STATE_FOR_USER,
                    $purpose,
                    [PurposeCode::PROVISIONING],
                );

                $mapping = $this->findMapping($userId);

                return match ($mapping?->status) {
                    null => SubjectProvisioningState::MISSING,
                    SubjectMapping::STATUS_ACTIVE => SubjectProvisioningState::ACTIVE,
                    SubjectMapping::STATUS_REVOKED => SubjectProvisioningState::REVOKED,
                };
            },
        );
    }

    public function provisionOwnSubject(int $userId, PurposeCode $purpose): string
    {
        return $this->performAuditedOperation(
            MappingOperation::PROVISION_OWN_SUBJECT,
            $purpose,
            AuditActorContext::registrationWorkflow(),
            $this->cryptography->auditSubjectReferenceForUserId($userId),
            function () use ($userId): string {
                $mapping = $this->findMapping($userId);

                if ($mapping !== null) {
                    $this->throwIfRevoked($mapping);

                    return $mapping->health_subject_id;
                }

                // Subject-first is deliberate. A deterministic, secret-derived ULID
                // makes a retry adopt any orphan left by a failed mapping write.
                $subjectId = $this->cryptography->healthSubjectIdForUserId($userId);
                $now = now();

                DB::connection('health')->table('health_subjects')->insertOrIgnore([
                    'id' => $subjectId,
                    'status' => HealthSubject::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $subject = HealthSubject::query()->findOrFail($subjectId);

                $mapping = new SubjectMapping;
                $mapping->user_id_hmac = $this->cryptography->userIdHmac($userId);
                $mapping->user_id_encrypted = $this->cryptography->encryptUserId($userId);
                $mapping->health_subject_id = $subject->getKey();
                $mapping->status = SubjectMapping::STATUS_ACTIVE;

                try {
                    // Nested transaction provides a savepoint in the test lane and
                    // keeps a unique-key race recoverable instead of aborting the
                    // caller's surrounding transaction.
                    DB::connection('mapping')->transaction(fn () => $mapping->save());
                } catch (UniqueConstraintViolationException $exception) {
                    $mapping = $this->findMapping($userId);

                    if ($mapping === null) {
                        throw $exception;
                    }

                    $this->throwIfRevoked($mapping);

                    return $mapping->health_subject_id;
                }

                return $subject->getKey();
            },
            ['mapping', 'health'],
            [PurposeCode::PROVISIONING],
        );
    }

    public function resolveOwnSubject(int $userId, PurposeCode $purpose): string
    {
        return $this->performAuditedOperation(
            MappingOperation::RESOLVE_OWN_SUBJECT,
            $purpose,
            AuditActorContext::employeeSelfService(),
            $this->cryptography->auditSubjectReferenceForUserId($userId),
            function () use ($purpose, $userId): string {
                $this->requirePurpose(MappingOperation::RESOLVE_OWN_SUBJECT, $purpose, [
                    PurposeCode::HEALTH_SELF_READ,
                    PurposeCode::HEALTH_SELF_WRITE,
                ]);

                $mapping = $this->requireMapping($userId);
                $this->throwIfRevoked($mapping);

                return $mapping->health_subject_id;
            },
        );
    }

    public function revokeSubjectLink(int $userId, PurposeCode $purpose): void
    {
        $this->performAuditedOperation(
            MappingOperation::REVOKE_SUBJECT_LINK,
            $purpose,
            AuditActorContext::privacyAdmin(),
            $this->cryptography->auditSubjectReferenceForUserId($userId),
            function () use ($userId): void {
                $mapping = $this->requireMapping($userId);

                if ($mapping->status === SubjectMapping::STATUS_REVOKED) {
                    return;
                }

                $mapping->status = SubjectMapping::STATUS_REVOKED;
                $mapping->revoked_at = now();
                $mapping->save();
            },
            ['mapping'],
            [PurposeCode::REVOCATION],
        );
    }

    public function resolveReportingCohort(array $userIds, PurposeCode $purpose): array
    {
        $userReference = hash('sha256', implode(':', array_map(
            fn (int $userId): string => $this->cryptography->auditSubjectReferenceForUserId($userId),
            $userIds,
        )));

        return $this->performAuditedOperation(
            MappingOperation::RESOLVE_REPORTING_COHORT,
            $purpose,
            AuditActorContext::reportingWorker(),
            $userReference,
            function () use ($purpose): never {
                $this->requirePurpose(
                    MappingOperation::RESOLVE_REPORTING_COHORT,
                    $purpose,
                    [PurposeCode::REPORTING],
                );

                throw new OperationNotAvailableException(
                    'resolveReportingCohort is unavailable in the pilot; see ADR-003 D5.',
                );
            },
        );
    }

    public function resolveForDataSubjectRequest(int $userId, PurposeCode $purpose): string
    {
        return $this->performAuditedOperation(
            MappingOperation::RESOLVE_FOR_DATA_SUBJECT_REQUEST,
            $purpose,
            AuditActorContext::privacyAdmin(),
            $this->cryptography->auditSubjectReferenceForUserId($userId),
            function () use ($purpose): never {
                $this->requirePurpose(
                    MappingOperation::RESOLVE_FOR_DATA_SUBJECT_REQUEST,
                    $purpose,
                    [PurposeCode::DSR],
                );

                throw new OperationNotAvailableException(
                    'resolveForDataSubjectRequest is unavailable in the pilot; see ADR-003 D5.',
                );
            },
        );
    }

    private function findMapping(int $userId): ?SubjectMapping
    {
        return SubjectMapping::query()
            ->where('user_id_hmac', $this->cryptography->userIdHmac($userId))
            ->first();
    }

    private function requireMapping(int $userId): SubjectMapping
    {
        return $this->findMapping($userId)
            ?? throw new MappingNotFoundException('Subject mapping was not found.');
    }

    private function throwIfRevoked(SubjectMapping $mapping): void
    {
        if ($mapping->status === SubjectMapping::STATUS_REVOKED) {
            throw new MappingRevokedException('Subject mapping has been revoked.');
        }
    }

    /**
     * @param  array<int, PurposeCode>  $allowedPurposes
     */
    private function requirePurpose(
        MappingOperation $operation,
        PurposeCode $purpose,
        array $allowedPurposes,
    ): void {
        if (! in_array($purpose, $allowedPurposes, true)) {
            throw new InvalidPurposeCodeException(
                "Purpose [{$purpose->value}] is not valid for [{$operation->value}].",
            );
        }
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $operationCallback
     * @param  list<string>  $transactionConnections
     * @param  array<int, PurposeCode>  $preTransactionPurposes
     * @return TReturn
     */
    private function performAuditedOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $userReference,
        callable $operationCallback,
        array $transactionConnections = [],
        array $preTransactionPurposes = [],
    ): mixed {
        if ($preTransactionPurposes !== []) {
            try {
                $this->requirePurpose($operation, $purpose, $preTransactionPurposes);
            } catch (InvalidPurposeCodeException $exception) {
                $this->auditLogger->logMappingOperation(
                    $operation,
                    $purpose,
                    $actorContext,
                    $userReference,
                    AuditLoggerContract::OUTCOME_DENIED,
                );

                throw $exception;
            }
        }

        $executeAndAudit = function () use (
            $operation,
            $purpose,
            $actorContext,
            $userReference,
            $operationCallback,
        ): mixed {
            $outcome = AuditLoggerContract::OUTCOME_SUCCESS;

            try {
                return $operationCallback();
            } catch (
                InvalidPurposeCodeException
                |MappingNotFoundException
                |MappingRevokedException
                |OperationNotAvailableException $exception
            ) {
                $outcome = AuditLoggerContract::OUTCOME_DENIED;

                throw $exception;
            } catch (Throwable $exception) {
                $outcome = AuditLoggerContract::OUTCOME_FAILED;

                throw $exception;
            } finally {
                // No catch surrounds the synchronous insert. For mutations, the
                // domain transactions remain open until this insert succeeds.
                $this->auditLogger->logMappingOperation(
                    $operation,
                    $purpose,
                    $actorContext,
                    $userReference,
                    $outcome,
                );
            }
        };

        return $this->runInTransactions($transactionConnections, $executeAndAudit);
    }

    /**
     * @template TReturn
     *
     * @param  list<string>  $connections
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function runInTransactions(array $connections, callable $callback): mixed
    {
        $connection = array_shift($connections);

        if ($connection === null) {
            return $callback();
        }

        return DB::connection($connection)->transaction(
            fn (): mixed => $this->runInTransactions($connections, $callback),
        );
    }
}
