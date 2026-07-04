<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Mode
    |--------------------------------------------------------------------------
    |
    | Controls whether aggregated insight endpoints serve deterministic demo
    | data ('demo') or real database aggregation ('prod'). Feature flags for
    | concept modules are derived from this mode as well.
    |
    */

    'data_mode' => env('ELYO_DATA_MODE', 'prod'),

];
