<?php

namespace App\Services\Privacy;

class NullAuditLogger implements AuditLoggerContract
{
    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $userReference,
        AuditOutcome $outcome,
    ): void {
        // Prompt 07 replaces this binding with the append-only audit writer.
    }

    public function logProvisioningBackfill(array $summary, AuditOutcome $outcome): void
    {
        // Test-only no-op retained for isolated MappingService construction.
    }
}
