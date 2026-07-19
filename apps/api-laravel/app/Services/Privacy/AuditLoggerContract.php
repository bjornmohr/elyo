<?php

namespace App\Services\Privacy;

interface AuditLoggerContract
{
    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $subjectReference,
    ): void;
}
