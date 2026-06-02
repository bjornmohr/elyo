<?php

require_once __DIR__.'/../vendor/autoload.php';

$testEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
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
