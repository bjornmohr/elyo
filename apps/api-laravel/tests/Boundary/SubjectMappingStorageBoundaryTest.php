<?php

namespace Tests\Boundary;

use App\Services\Privacy\MappingCryptography;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Support\Facades\DB;
use Tests\Support\ConfiguresPrivacyMapping;

class SubjectMappingStorageBoundaryTest extends BoundaryTestCase
{
    use ConfiguresPrivacyMapping;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePrivacyMapping('boundary-storage-test');
    }

    public function test_subject_mapping_stores_no_plaintext_user_id(): void
    {
        $userId = 81001;
        app(MappingServiceContract::class)->provisionOwnSubject($userId, PurposeCode::PROVISIONING);

        $this->assertFalse(
            DB::connection('mapping')->getSchemaBuilder()->hasColumn('subject_mappings', 'user_id'),
            'Mapping schema unexpectedly contains a plaintext user ID column.',
        );

        $storedHmac = DB::connection('mapping')
            ->table('subject_mappings')
            ->value('user_id_hmac');
        $storedCiphertext = DB::connection('mapping')
            ->table('subject_mappings')
            ->value('user_id_encrypted');
        $cryptography = app(MappingCryptography::class);

        $this->assertIsString($storedHmac);
        $this->assertIsString($storedCiphertext);
        $this->assertNotSame((string) $userId, $storedCiphertext);
        $this->assertSame($cryptography->userIdHmac($userId), $storedHmac);
        $this->assertSame($userId, $cryptography->decryptUserId($storedCiphertext));
    }
}
