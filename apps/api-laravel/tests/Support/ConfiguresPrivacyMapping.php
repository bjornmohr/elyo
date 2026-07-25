<?php

namespace Tests\Support;

use App\Services\Privacy\MappingCryptography;

trait ConfiguresPrivacyMapping
{
    protected function configurePrivacyMapping(string $scope): void
    {
        config()->set('privacy.mapping.encryption_key', 'base64:a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s=');
        config()->set('privacy.mapping.hmac_key', "{$scope}-hmac-key");
        config()->set('privacy.mapping.subject_derivation_key', "{$scope}-subject-key");
        config()->set('app.key', 'base64:bW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW1tbW0=');
        app()->forgetInstance(MappingCryptography::class);
    }
}
