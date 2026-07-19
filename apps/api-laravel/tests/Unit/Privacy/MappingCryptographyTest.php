<?php

namespace Tests\Unit\Privacy;

use App\Services\Privacy\MappingCryptography;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MappingCryptographyTest extends TestCase
{
    private const ENCRYPTION_KEY = 'base64:a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s=';

    private const APP_KEY = 'base64:bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=';

    public function test_user_id_hmac_is_deterministic_sha256_with_the_dedicated_key(): void
    {
        $cryptography = new MappingCryptography(
            self::ENCRYPTION_KEY,
            'test-hmac-key',
            'test-subject-derivation-key',
            self::APP_KEY,
        );

        $this->assertSame(
            'bb54483be090c0dbbe6d30c4c626e6dfc57f8e72f443f9683d20911ca92d2d6f',
            $cryptography->userIdHmac(42),
        );
        $this->assertSame($cryptography->userIdHmac(42), $cryptography->userIdHmac(42));
        $this->assertNotSame($cryptography->userIdHmac(42), $cryptography->userIdHmac(43));
    }

    public function test_user_id_encryption_round_trips_with_the_dedicated_key(): void
    {
        $cryptography = new MappingCryptography(
            self::ENCRYPTION_KEY,
            'test-hmac-key',
            'test-subject-derivation-key',
            self::APP_KEY,
        );

        $ciphertext = $cryptography->encryptUserId(424242);

        $this->assertSame(424242, $cryptography->decryptUserId($ciphertext));
        $this->assertNotSame('424242', $ciphertext);
    }

    public function test_subject_id_derivation_is_isolated_from_the_lookup_hmac_key(): void
    {
        $original = new MappingCryptography(self::ENCRYPTION_KEY, 'lookup-key-a', 'subject-key-a', self::APP_KEY);
        $rotatedLookup = new MappingCryptography(self::ENCRYPTION_KEY, 'lookup-key-b', 'subject-key-a', self::APP_KEY);
        $rotatedSubject = new MappingCryptography(self::ENCRYPTION_KEY, 'lookup-key-a', 'subject-key-b', self::APP_KEY);

        $this->assertSame(
            $original->healthSubjectIdForUserId(42),
            $rotatedLookup->healthSubjectIdForUserId(42),
        );
        $this->assertNotSame(
            $original->healthSubjectIdForUserId(42),
            $rotatedSubject->healthSubjectIdForUserId(42),
        );
    }

    #[DataProvider('reusedKeyProvider')]
    public function test_mapping_keys_must_be_independent_from_each_other_and_app_key(
        string $encryptionKey,
        string $hmacKey,
        string $subjectDerivationKey,
        string $appKey,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new MappingCryptography($encryptionKey, $hmacKey, $subjectDerivationKey, $appKey);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function reusedKeyProvider(): array
    {
        return [
            'encryption and lookup' => [self::ENCRYPTION_KEY, self::ENCRYPTION_KEY, 'subject-key', self::APP_KEY],
            'encryption and subject' => [self::ENCRYPTION_KEY, 'lookup-key', self::ENCRYPTION_KEY, self::APP_KEY],
            'lookup and subject' => [self::ENCRYPTION_KEY, 'shared-key', 'shared-key', self::APP_KEY],
            'encryption and app' => [self::ENCRYPTION_KEY, 'lookup-key', 'subject-key', self::ENCRYPTION_KEY],
            'lookup and app' => [self::ENCRYPTION_KEY, self::APP_KEY, 'subject-key', self::APP_KEY],
            'subject and app' => [self::ENCRYPTION_KEY, 'lookup-key', self::APP_KEY, self::APP_KEY],
        ];
    }
}
