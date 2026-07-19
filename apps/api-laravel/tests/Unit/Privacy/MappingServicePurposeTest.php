<?php

namespace Tests\Unit\Privacy;

use App\Services\Privacy\Exceptions\InvalidPurposeCodeException;
use App\Services\Privacy\MappingCryptography;
use App\Services\Privacy\MappingService;
use App\Services\Privacy\NullAuditLogger;
use App\Services\Privacy\PurposeCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MappingServicePurposeTest extends TestCase
{
    private const ENCRYPTION_KEY = 'base64:a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s=';

    #[DataProvider('invalidPurposeProvider')]
    public function test_operations_reject_invalid_purpose_codes(
        string $operation,
        int|array $subjectInput,
        PurposeCode $purpose,
    ): void {
        $service = new MappingService(
            new MappingCryptography(
                self::ENCRYPTION_KEY,
                'test-hmac-key',
                'test-subject-derivation-key',
                'base64:bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=',
            ),
            new NullAuditLogger,
        );

        $this->expectException(InvalidPurposeCodeException::class);

        $service->{$operation}($subjectInput, $purpose);
    }

    /**
     * @return array<string, array{string, int|array<int, int>, PurposeCode}>
     */
    public static function invalidPurposeProvider(): array
    {
        return [
            'provision requires provisioning' => ['provisionOwnSubject', 42, PurposeCode::HEALTH_SELF_READ],
            'resolve rejects provisioning' => ['resolveOwnSubject', 42, PurposeCode::PROVISIONING],
            'revoke requires revocation' => ['revokeSubjectLink', 42, PurposeCode::HEALTH_SELF_WRITE],
            'reporting requires reporting' => ['resolveReportingCohort', [42], PurposeCode::HEALTH_SELF_READ],
            'data subject request requires dsr' => ['resolveForDataSubjectRequest', 42, PurposeCode::HEALTH_SELF_READ],
        ];
    }
}
