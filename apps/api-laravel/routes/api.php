<?php

$runtime = config('database.runtime');

require __DIR__.'/api/health.php';

foreach (match ($runtime) {
    'identity' => ['identity', 'identity-partner'],
    'employee' => ['employee'],
    'company' => ['company'],
    'full' => ['identity', 'company', 'employee', 'identity-partner'],
} as $profile) {
    require __DIR__."/api/{$profile}.php";
}
