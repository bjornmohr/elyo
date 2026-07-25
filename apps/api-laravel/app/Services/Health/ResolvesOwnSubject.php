<?php

namespace App\Services\Health;

use App\Services\Privacy\AuditActorContext;
use App\Services\Privacy\Exceptions\MappingNotFoundException;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\Log;

/**
 * Self-service subject resolution for health-domain services (ADR-003 D3/D8).
 *
 * Callers pass an identity user id; the health domain resolves it to a
 * `health_subject_id` through the mapping domain with an explicit purpose code,
 * and only the subject id ever reaches the health database. Extracted from
 * `WellbeingService` (prompt 08) when prompt 08a added anamnesis, documents and
 * wearables, so all health services share one resolution idiom instead of
 * growing parallel ones.
 */
trait ResolvesOwnSubject
{
    abstract protected function mappingService(): MappingServiceContract;

    /**
     * Resolves the caller's own subject. A missing mapping is repaired in place:
     * provisioning is idempotent by design (ADR-003 D5, prompt 05), so a subject
     * that was never provisioned — or whose provisioning failed after the
     * identity commit — must not turn into a failed request. Both the failed
     * resolve and the repair are audited by the mapping service.
     */
    protected function resolveSubjectId(int $userId, PurposeCode $purpose): string
    {
        try {
            return $this->mappingService()->resolveOwnSubject($userId, $purpose);
        } catch (MappingNotFoundException) {
            Log::warning('[HEALTH] Missing subject mapping repaired during self-service access.', [
                'purpose' => $purpose->value,
            ]);

            $this->mappingService()->provisionOwnSubject(
                $userId,
                PurposeCode::PROVISIONING,
                AuditActorContext::employeeSelfService(),
            );

            return $this->mappingService()->resolveOwnSubject($userId, $purpose);
        }
    }
}
