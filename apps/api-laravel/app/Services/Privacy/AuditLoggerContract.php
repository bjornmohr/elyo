<?php

namespace App\Services\Privacy;

interface AuditLoggerContract
{
    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $userReference,
        AuditOutcome $outcome,
    ): void;

    /**
     * @param array{
     *     scanned: int,
     *     missing: int,
     *     active: int,
     *     revoked: int,
     *     provisioned: int,
     *     failed: int,
     *     dry_run: bool
     * } $summary
     */
    public function logProvisioningBackfill(array $summary, AuditOutcome $outcome): void;

    /**
     * @param array{
     *     health_rows_deleted: int,
     *     health_files_deleted: int,
     *     identity_rows_deleted: int,
     *     mapping_revoked: int
     * } $summary
     */
    public function logAccountDeletion(array $summary, AuditOutcome $outcome): void;
}
