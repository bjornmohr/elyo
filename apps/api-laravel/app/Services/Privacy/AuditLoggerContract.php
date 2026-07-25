<?php

namespace App\Services\Privacy;

interface AuditLoggerContract
{
    public const string OUTCOME_SUCCESS = 'success';

    public const string OUTCOME_DENIED = 'denied';

    public const string OUTCOME_FAILED = 'failed';

    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $userReference,
        string $outcome,
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
    public function logProvisioningBackfill(array $summary, string $outcome): void;
}
