<?php

namespace App\Services\Privacy;

class NullAuditLogger implements AuditLoggerContract
{
    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $subjectReference,
    ): void {
        // Prompt 07 replaces this binding with the append-only audit writer.
    }
}
