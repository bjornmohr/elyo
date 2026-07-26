<?php

namespace App\Services\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseAuditLogger implements AuditLoggerContract
{
    private ?string $correlationId = null;

    public function logMappingOperation(
        MappingOperation $operation,
        PurposeCode $purpose,
        AuditActorContext $actorContext,
        string $userReference,
        AuditOutcome $outcome,
    ): void {
        $this->insertEvent(
            'mapping.'.$operation->value,
            $purpose,
            $actorContext->toArray(),
            null,
            $userReference,
            $outcome,
        );
    }

    public function logProvisioningBackfill(array $summary, AuditOutcome $outcome): void
    {
        $this->insertEvent(
            'provisioning.backfill',
            PurposeCode::PROVISIONING,
            [
                ...AuditActorContext::registrationWorkflow()->toArray(),
                'summary' => $summary,
            ],
            null,
            null,
            $outcome,
        );
    }

    public function logAccountDeletion(array $summary, AuditOutcome $outcome): void
    {
        $this->insertEvent(
            'account.deletion',
            PurposeCode::REVOCATION,
            [
                ...AuditActorContext::privacyAdmin()->toArray(),
                'summary' => $summary,
            ],
            null,
            null,
            $outcome,
        );
    }

    /**
     * @param  array<string, mixed>  $actorContext
     */
    private function insertEvent(
        string $eventType,
        PurposeCode $purpose,
        array $actorContext,
        ?string $subjectReference,
        ?string $userReference,
        AuditOutcome $outcome,
    ): void {
        DB::connection('audit')->table('audit_events')->insert([
            'id' => (string) Str::ulid(),
            'event_type' => $eventType,
            'purpose' => $purpose->value,
            'actor_context' => json_encode($actorContext, JSON_THROW_ON_ERROR),
            'subject_ref' => $subjectReference,
            'user_ref' => $userReference,
            'outcome' => $outcome->value,
            'correlation_id' => $this->correlationId(),
            'occurred_at' => now(),
        ]);
    }

    private function correlationId(): string
    {
        if ($this->correlationId !== null) {
            return $this->correlationId;
        }

        if (app()->bound('request')) {
            $requestCorrelationId = request()->header('X-Correlation-ID');

            if (
                is_string($requestCorrelationId)
                && (Str::isUlid($requestCorrelationId) || Str::isUuid($requestCorrelationId))
            ) {
                return $this->correlationId = $requestCorrelationId;
            }
        }

        return $this->correlationId = (string) Str::ulid();
    }
}
