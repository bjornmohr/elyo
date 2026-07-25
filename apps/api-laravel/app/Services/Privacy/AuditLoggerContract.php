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
}
