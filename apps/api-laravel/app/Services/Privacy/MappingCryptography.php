<?php

namespace App\Services\Privacy;

use Illuminate\Encryption\Encrypter;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;
use UnexpectedValueException;

class MappingCryptography
{
    private readonly Encrypter $encrypter;

    public function __construct(
        string $encryptionKey,
        private readonly string $hmacKey,
        private readonly string $subjectDerivationKey,
        string $applicationKey,
    ) {
        if ($this->hmacKey === '') {
            throw new InvalidArgumentException('MAPPING_HMAC_KEY must not be empty.');
        }

        if ($this->subjectDerivationKey === '') {
            throw new InvalidArgumentException('MAPPING_SUBJECT_DERIVATION_KEY must not be empty.');
        }

        $key = $this->normalizeSecret($encryptionKey);

        if (! Encrypter::supported($key, 'AES-256-CBC')) {
            throw new InvalidArgumentException('MAPPING_ENCRYPTION_KEY must be a valid 32-byte AES-256 key.');
        }

        $normalizedApplicationKey = $this->normalizeSecret($applicationKey);

        if ($normalizedApplicationKey === '') {
            throw new InvalidArgumentException('APP_KEY must not be empty.');
        }

        $this->requireIndependentKeys([
            $key,
            $this->normalizeSecret($this->hmacKey),
            $this->normalizeSecret($this->subjectDerivationKey),
            $normalizedApplicationKey,
        ]);

        $this->encrypter = new Encrypter($key, 'AES-256-CBC');
    }

    public function userIdHmac(int $userId): string
    {
        return hash_hmac('sha256', (string) $userId, $this->hmacKey);
    }

    public function encryptUserId(int $userId): string
    {
        return $this->encrypter->encryptString((string) $userId);
    }

    public function decryptUserId(string $ciphertext): int
    {
        $userId = $this->encrypter->decryptString($ciphertext);

        if ($userId === '' || ! ctype_digit($userId)) {
            throw new UnexpectedValueException('Decrypted mapping identifier is invalid.');
        }

        return (int) $userId;
    }

    /**
     * Stable derivation lets a retry find the subject created before a failed
     * mapping write without placing an identity recovery marker in Health.
     * Only the mapping service, which owns the independent derivation key, can
     * reproduce the ULID. The lookup HMAC key cannot derive Health identifiers.
     */
    public function healthSubjectIdForUserId(int $userId): string
    {
        $binaryId = substr(
            hash_hmac('sha256', 'health-subject:'.$userId, $this->subjectDerivationKey, true),
            0,
            16,
        );

        return Ulid::fromBinary($binaryId)->toBase32();
    }

    public function auditSubjectReferenceForUserId(int $userId): string
    {
        return hash_hmac('sha256', 'audit-subject:'.$userId, $this->hmacKey);
    }

    private function normalizeSecret(string $secret): string
    {
        if (! str_starts_with($secret, 'base64:')) {
            return $secret;
        }

        $decoded = base64_decode(substr($secret, 7), true);

        return is_string($decoded) ? $decoded : $secret;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function requireIndependentKeys(array $keys): void
    {
        foreach ($keys as $index => $key) {
            foreach (array_slice($keys, $index + 1) as $otherKey) {
                if (hash_equals($key, $otherKey)) {
                    throw new InvalidArgumentException(
                        'Mapping encryption, lookup, derivation, and application keys must be independent.',
                    );
                }
            }
        }
    }
}
