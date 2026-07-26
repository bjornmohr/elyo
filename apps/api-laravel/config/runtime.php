<?php

use App\Runtime\RuntimeProfile;

return [

    /*
    |--------------------------------------------------------------------------
    | Runtime Profile
    |--------------------------------------------------------------------------
    |
    | ELYO_RUNTIME decides which route files register and which database
    | connections exist (ADR-001 §2.4, ADR-003 D2). Resolution is fail-safe:
    | an unknown or missing profile aborts the boot outside local/testing, and
    | `full` is rejected unless APP_ENV is local or testing.
    |
    */

    'profile' => RuntimeProfile::resolve(),

];
