<?php

namespace App\Services\Privacy;

interface MappingServiceContract
{
    public function provisioningStateForUser(
        int $userId,
        PurposeCode $purpose,
    ): SubjectProvisioningState;

    public function provisionOwnSubject(
        int $userId,
        PurposeCode $purpose,
        ?AuditActorContext $actorContext = null,
    ): string;

    public function resolveOwnSubject(int $userId, PurposeCode $purpose): string;

    /**
     * @param  null|callable(string): void  $beforeRevocation
     * @param  null|callable(): void  $afterRevocationAudit
     */
    public function revokeSubjectLink(
        int $userId,
        PurposeCode $purpose,
        ?callable $beforeRevocation = null,
        ?callable $afterRevocationAudit = null,
    ): void;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function resolveReportingCohort(array $userIds, PurposeCode $purpose): array;

    public function resolveForDataSubjectRequest(int $userId, PurposeCode $purpose): string;
}
