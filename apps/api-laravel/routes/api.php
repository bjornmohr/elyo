<?php

use App\Runtime\RuntimeProfile;

$runtime = (string) config('runtime.profile');

require __DIR__.'/api/health.php';

foreach (RuntimeProfile::routeFiles($runtime) as $profile) {
    require __DIR__."/api/{$profile}.php";
}
