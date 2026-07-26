<?php

require_once __DIR__.'/../vendor/autoload.php';

// D9 (Postgres-only): force the isolated database names and runtime roles before
// Laravel boots. Docker Compose injects development database values into the PHP
// process, and phpunit.xml alone does not override those values early enough for
// Laravel's configuration bootstrap. Keeping this guard here prevents
// RefreshDatabase from ever fresh-migrating a development database.
$testEnvironment = [
    'APP_ENV' => 'testing',
    'ELYO_RUNTIME' => 'full',
    'DB_URL' => '',
    'DB_CONNECTION' => 'identity',
    'DB_IDENTITY_DATABASE' => 'elyo_identity_test',
    'DB_IDENTITY_USERNAME' => 'elyo_identity_rt',
    'DB_MAPPING_DATABASE' => 'elyo_subject_mapping_test',
    'DB_MAPPING_USERNAME' => 'elyo_mapping_svc',
    'DB_HEALTH_DATABASE' => 'elyo_health_test',
    'DB_HEALTH_USERNAME' => 'elyo_employee_rt',
    'DB_AUDIT_DATABASE' => 'elyo_audit_test',
    'DB_AUDIT_USERNAME' => 'elyo_employee_rt',
    'DB_MIGRATOR_USERNAME' => 'elyo_migrator',
];

foreach ($testEnvironment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$configCachePath = __DIR__.'/../bootstrap/cache/config.php';

// Filtered PHPUnit runs must not reuse stale cached Laravel configuration.
if (is_file($configCachePath) && ! unlink($configCachePath)) {
    throw new RuntimeException('Unable to remove stale Laravel config cache before running tests: '.$configCachePath);
}
