<?php

namespace App\Services\Privacy;

interface MappingServiceContract
{
    public function provisionOwnSubject(int $userId, PurposeCode $purpose): string;

    public function resolveOwnSubject(int $userId, PurposeCode $purpose): string;

    public function revokeSubjectLink(int $userId, PurposeCode $purpose): void;

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, string>
     */
    public function resolveReportingCohort(array $userIds, PurposeCode $purpose): array;

    public function resolveForDataSubjectRequest(int $userId, PurposeCode $purpose): string;
}
