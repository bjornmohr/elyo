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
        try {
            $this->requirePurpose(MappingOperation::PROVISION_OWN_SUBJECT, $purpose, [PurposeCode::PROVISIONING]);

            $mapping = $this->findMapping($userId);

            return match ($mapping?->status) {
                null => SubjectProvisioningState::MISSING,
                SubjectMapping::STATUS_ACTIVE => SubjectProvisioningState::ACTIVE,
                SubjectMapping::STATUS_REVOKED => SubjectProvisioningState::REVOKED,
            };
        } finally {
            $this->auditOperation(
                MappingOperation::PROVISION_OWN_SUBJECT,
                $purpose,
                $userId,
                AuditActorContext::registrationWorkflow(),
            );
        }
    }

    public function provisionOwnSubject(int $userId, PurposeCode $purpose): string
    {
        try {
            $this->requirePurpose(MappingOperation::PROVISION_OWN_SUBJECT, $purpose, [PurposeCode::PROVISIONING]);

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
        } finally {
            $this->auditOperation(
                MappingOperation::PROVISION_OWN_SUBJECT,
                $purpose,
                $userId,
                AuditActorContext::registrationWorkflow(),
            );
        }
    }

    public function resolveOwnSubject(int $userId, PurposeCode $purpose): string
    {
        try {
            $this->requirePurpose(MappingOperation::RESOLVE_OWN_SUBJECT, $purpose, [
                PurposeCode::HEALTH_SELF_READ,
                PurposeCode::HEALTH_SELF_WRITE,
            ]);

            $mapping = $this->requireMapping($userId);
            $this->throwIfRevoked($mapping);

            return $mapping->health_subject_id;
        } finally {
            $this->auditOperation(
                MappingOperation::RESOLVE_OWN_SUBJECT,
                $purpose,
                $userId,
                AuditActorContext::employeeSelfService(),
            );
        }
    }

    public function revokeSubjectLink(int $userId, PurposeCode $purpose): void
    {
        try {
            $this->requirePurpose(MappingOperation::REVOKE_SUBJECT_LINK, $purpose, [PurposeCode::REVOCATION]);

            $mapping = $this->requireMapping($userId);

            if ($mapping->status === SubjectMapping::STATUS_REVOKED) {
                return;
            }

            $mapping->status = SubjectMapping::STATUS_REVOKED;
            $mapping->revoked_at = now();
            $mapping->save();
        } finally {
            $this->auditOperation(
                MappingOperation::REVOKE_SUBJECT_LINK,
                $purpose,
                $userId,
                AuditActorContext::privacyAdmin(),
            );
        }
    }

    public function resolveReportingCohort(array $userIds, PurposeCode $purpose): array
    {
        try {
            $this->requirePurpose(MappingOperation::RESOLVE_REPORTING_COHORT, $purpose, [PurposeCode::REPORTING]);

            throw new OperationNotAvailableException(
                'resolveReportingCohort is unavailable in the pilot; see ADR-003 D5.',
            );
        } finally {
            $subjectReference = hash('sha256', implode(':', array_map(
                fn (int $userId): string => $this->cryptography->auditSubjectReferenceForUserId($userId),
                $userIds,
            )));

            $this->auditLogger->logMappingOperation(
                MappingOperation::RESOLVE_REPORTING_COHORT,
                $purpose,
                AuditActorContext::reportingWorker(),
                $subjectReference,
            );
        }
    }

    public function resolveForDataSubjectRequest(int $userId, PurposeCode $purpose): string
    {
        try {
            $this->requirePurpose(MappingOperation::RESOLVE_FOR_DATA_SUBJECT_REQUEST, $purpose, [PurposeCode::DSR]);

            throw new OperationNotAvailableException(
                'resolveForDataSubjectRequest is unavailable in the pilot; see ADR-003 D5.',
            );
        } finally {
            $this->auditLogger->logMappingOperation(
                MappingOperation::RESOLVE_FOR_DATA_SUBJECT_REQUEST,
                $purpose,
                AuditActorContext::privacyAdmin(),
                $this->cryptography->auditSubjectReferenceForUserId($userId),
            );
        }
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

    private function auditOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        int $userId,
        AuditActorContext $actorContext,
    ): void {
        // Actor context carries only a typed workflow/runtime classification.
        // subjectReference is a domain-separated HMAC, never a raw user_id or
        // health_subject_id, so audit cannot become a re-identification join.
        $this->auditLogger->logMappingOperation(
            $operation,
            $purpose,
            $actorContext,
            $this->cryptography->auditSubjectReferenceForUserId($userId),
        );
    }
}
